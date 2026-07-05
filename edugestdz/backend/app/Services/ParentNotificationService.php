<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\NotificationParent;
use Illuminate\Support\Facades\Log;

class ParentNotificationService
{
    public function __construct(
        private FirebaseService $firebase,
        private Sms\SmsService      $sms,
    ) {}

    public function notifier(
        string $eleveId,
        string $type,
        string $titre,
        string $corps,
        array  $meta        = [],
        bool   $avecSMS     = false,
        bool   $forcerSMS   = false
    ): void {
        $eleve = Eleve::with('parents:id,nom,prenom,telephone_1,telephone_2')->find($eleveId);
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

            if ($avecSMS || $forcerSMS) {
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
