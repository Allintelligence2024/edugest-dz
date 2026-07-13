<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\NotificationParent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ParentNotificationService
{
    public function __construct(
        private FirebaseService $firebase,
        private Sms\SmsService  $sms,
        private NotificationTimingService $timing,
    ) {}

    public function notifier(
        string $eleveId,
        string $type,
        string $titre,
        string $corps,
        array  $meta        = [],
        bool   $avecSMS     = false,
        bool   $forcerSMS   = false,
        bool   $urgence     = false
    ): void {
        $eleve = Eleve::with('parents:id,nom,prenom,telephone_1,telephone_2,email')->find($eleveId);
        if (!$eleve) return;

        foreach ($eleve->parents as $parent) {
            $notif = NotificationParent::create([
                'tenant_id' => $eleve->tenant_id,
                'parent_id' => $parent->id,
                'eleve_id'  => $eleveId,
                'type'      => $type,
                'titre'     => $titre,
                'corps'     => $corps,
                'meta'      => $meta,
            ]);

            if ($this->timing->doitEnvoyerPush($urgence)) {
                $pushed = $this->firebase->notifyUser(
                    $parent->id,
                    $titre,
                    $corps,
                    array_merge($meta, [
                        'type'     => $type,
                        'eleve_id' => $eleveId,
                        'notif_id' => $notif->id,
                    ])
                );
                if ($pushed) $notif->update(['push_envoye' => true]);
            }

            $envoyerSMS = $avecSMS || $forcerSMS;
            if ($envoyerSMS && $this->timing->doitEnvoyerSMS($urgence)) {
                $tel = $parent->telephone_1 ?? $parent->telephone_2 ?? null;
                if ($tel) {
                    try {
                        $this->sms->send($tel, "EduGest: {$titre}\n{$corps}");
                        $notif->update(['sms_envoye' => true]);
                    } catch (\Throwable $e) {
                        Log::warning("SMS parent échoué: " . $e->getMessage());
                    }
                }
            }

            if (!$this->timing->doitEnvoyerEmail($urgence)) continue;

            // ── Canal 3 : Email HTML ──────────────────────────────────────────────
            $emailParent = $parent->email ?? null;
            $emailActif  = config('mail.default') !== 'log'
                && !empty(config('services.smtp.host', config('mail.mailers.smtp.host', '')));

            if ($emailParent && $emailActif) {
                try {
                    $urlApp = config('app.url') . '/dashboard';

                    $templateMap = [
                        'absence'      => 'emails.absence-eleve',
                        'bulletin'     => 'emails.bulletin-disponible',
                        'note'         => 'emails.note-publiee',
                        'signalement'  => 'emails.absence-eleve',
                        'facture'      => 'emails.facture-relance',
                        'bienvenue'    => 'emails.bienvenue',
                        'resume_hebdo' => 'emails.resume-hebdo',
                    ];

                    $template = $templateMap[$type] ?? null;

                    if ($template) {
                        $viewData = array_merge($meta, [
                            'parentNom'     => $parent->nom ?? '',
                            'parentPrenom'  => $parent->prenom ?? '',
                            'eleveNom'      => $eleve->nom ?? '',
                            'elevePrenom'   => $eleve->prenom ?? '',
                            'nomEcole'      => config('app.name', 'EduGest DZ'),
                            'urlApplication'=> $urlApp,
                            'urlDesinscription' => null,
                            'dateAbsence'   => $meta['date'] ?? now()->format('d/m/Y'),
                            'motif'         => $meta['motif'] ?? null,
                            'trimestre'     => $meta['trimestre'] ?? 'Trimestre',
                            'moyenne'       => $meta['moyenne'] ?? 0,
                            'rang'          => $meta['rang'] ?? 0,
                            'effectif'      => $meta['effectif'] ?? 0,
                            'mention'       => $meta['mention'] ?? '',
                            'appreciation'  => $meta['appreciation'] ?? null,
                            'anneeScolaire' => now()->year . '-' . (now()->year + 1),
                            'urlBulletin'   => $urlApp . '/bulletins',
                            'note'          => $meta['note'] ?? 0,
                            'noteMax'       => $meta['note_sur'] ?? 20,
                            'noteSur20'     => $meta['note_sur_20'] ?? 0,
                            'matiere'       => $meta['matiere'] ?? '',
                            'emoji'         => $meta['emoji'] ?? '📝',
                            'noteColor'     => ($meta['note'] ?? 0) >= ($meta['note_sur'] ?? 20) * 0.5
                                ? '#16a34a' : '#dc2626',
                            'numeroFacture' => $meta['numero_facture'] ?? '',
                            'montant'       => $meta['montant'] ?? 0,
                            'dateEcheance'  => $meta['date_echeance'] ?? '',
                            'joursRetard'   => $meta['jours_retard'] ?? 0,
                            'periode'       => $meta['periode'] ?? '',
                            'urlPaiementEnLigne' => $urlApp . '/factures',
                            'telephoneEcole'=> $meta['telephone_ecole'] ?? '',
                            'numeroRelance' => $meta['numero_relance'] ?? 1,
                        ]);

                        Mail::send($template, $viewData, function ($message) use ($emailParent, $titre) {
                            $message
                                ->to($emailParent)
                                ->subject("EduGest DZ — {$titre}")
                                ->from(
                                    config('mail.from.address', 'noreply@edugestdz.dz'),
                                    config('mail.from.name', 'EduGest DZ')
                                );
                        });

                        $notif->update(['email_envoye' => true]);
                    }

                } catch (\Throwable $e) {
                    Log::warning("Email parent échoué ({$type}): " . $e->getMessage(), [
                        'parent_id' => $parent->id,
                        'eleve_id'  => $eleveId,
                    ]);
                }
            }
        }
    }

    public function notePubliee(string $eleveId, string $matiere, float $note, float $noteMax, ?string $appreciation): void
    {
        $noteSur20 = $noteMax > 0 ? round(($note / $noteMax) * 20, 2) : $note;
        $emoji     = $note >= ($noteMax * 0.75) ? '✅' : ($note < ($noteMax * 0.25) ? '⚠️' : '📝');

        $this->notifier(
            $eleveId,
            'note',
            "{$emoji} Nouvelle note — {$matiere}",
            "Note obtenue : {$note}/{$noteMax} ({$noteSur20}/20)" .
                ($appreciation ? " — {$appreciation}" : ''),
            ['matiere' => $matiere, 'note' => $note, 'note_sur' => $noteMax],
            $noteSur20 < 5
        );
    }

    public function bulletinGenere(string $eleveId, string $trimestre, float $moyenne, int $rang, int $effectif): void
    {
        $mention = match(true) {
            $moyenne >= 16 => 'Très Bien',
            $moyenne >= 14 => 'Bien',
            $moyenne >= 12 => 'Assez Bien',
            $moyenne >= 10 => 'Passable',
            default        => 'Insuffisant',
        };

        $this->notifier(
            $eleveId,
            'bulletin',
            "📄 Bulletin {$trimestre} disponible",
            "Moyenne générale : {$moyenne}/20 · Mention : {$mention} · Rang : {$rang}/{$effectif}",
            ['trimestre' => $trimestre, 'moyenne' => $moyenne, 'rang' => $rang],
            true
        );
    }

    public function absenceSignalee(string $eleveId, string $date, ?string $motif): void
    {
        $this->notifier(
            $eleveId,
            'absence',
            "⚠️ Absence signalée",
            "Votre enfant est absent le {$date}." .
                ($motif ? " Motif : {$motif}" : ' Aucun motif renseigné.'),
            ['date' => $date],
            true
        );
    }

    public function comportementSignale(
        string $eleveId,
        string $typeSignalement,
        string $gravite,
        string $description,
        string $auteurNom
    ): void {
        $typeInfo = \App\Models\SignalementComportement::TYPES[$typeSignalement]
            ?? ['label' => $typeSignalement, 'emoji' => '📝', 'positif' => false];

        $estPositif = $typeInfo['positif'];
        $emoji      = $estPositif ? '⭐' : ($gravite === 'très_grave' ? '🚨' : '⚠️');

        $titre = $estPositif
            ? "{$emoji} Félicitation — {$typeInfo['label']}"
            : "{$emoji} Signalement — {$typeInfo['label']}";

        $corps = $estPositif
            ? "Votre enfant a été félicité par {$auteurNom}. {$description}"
            : "Incident signalé par {$auteurNom} : {$description}";

        $avecSMS = $gravite === 'grave' || $gravite === 'très_grave';

        $this->notifier(
            $eleveId,
            'signalement',
            $titre,
            $corps,
            ['type' => $typeSignalement, 'gravite' => $gravite],
            $avecSMS
        );
    }

    public function niveauChange(string $eleveId, string $niveauActuel, float $moyenne): void
    {
        if ($niveauActuel === 'normal' || $niveauActuel === 'excellent') return;

        $messages = [
            'vigilance' => ['⚠️ Niveau en vigilance', "La moyenne de votre enfant ({$moyenne}/20) nécessite un suivi renforcé."],
            'danger'    => ['🔴 Niveau en danger',    "La moyenne de votre enfant ({$moyenne}/20) est préoccupante. Contactez l'établissement."],
            'critique'  => ['🚨 Niveau critique',     "La moyenne de votre enfant ({$moyenne}/20) est très insuffisante. Une convocation sera envoyée."],
        ];

        [$titre, $corps] = $messages[$niveauActuel] ?? ['📊 Niveau académique', "Moyenne : {$moyenne}/20"];

        $this->notifier(
            $eleveId,
            'diagnostic',
            $titre,
            $corps,
            ['niveau' => $niveauActuel, 'moyenne' => $moyenne],
            $niveauActuel === 'critique'
        );
    }

    public function convocationEmise(string $eleveId, string $motif, ?string $rdvDate): void
    {
        $this->notifier(
            $eleveId,
            'convocation',
            '📅 Convocation de vos parents',
            "Vous êtes convoqué(e) à l'établissement. Motif : {$motif}" .
                ($rdvDate ? ". Date : {$rdvDate}" : ''),
            ['motif' => $motif],
            true,
            true
        );
    }
}
