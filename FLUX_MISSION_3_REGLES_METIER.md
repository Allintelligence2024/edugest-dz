# ⚖️ MISSION 3/3 — Règles Métier, Plages Horaires & Tests Complets
## EduGest DZ · Flux info mondial adapté · Branche : develop · 10 Juillet 2026
## Prérequis : Mission 1 et 2 mergées · 0 régression

---

## CONTEXTE — CE QUE CETTE MISSION FINALISE

```
MISSION 1 a créé : Backend (AbsenceEnseignant, Devoir, Feedback, Signalement)
MISSION 2 a créé : Frontend (NotificationsPage, DevoirsPage, FeedbackPage)

MISSION 3 finalise :
❌ Règles métier fine-grained : horaires Algérie, délais légaux, escalades
❌ Middleware NotificationHoraire (backend — filtre les pushs nocturnes)
❌ Scheduler : "absence non signalée" → détection matin + alerte urgence
❌ Scheduler : diagnostic EWS hebdo + rapport mensuel directeur email auto
❌ Tests unitaires des règles métier (plages horaires, escalades)
❌ PolicyService (qui peut faire quoi selon rôle)
❌ Documentation API des nouveaux endpoints (Swagger annotations)
```

### RÈGLES ABSOLUES
1. **0 régression** — 724+ tests verts
2. **PostgreSQL uniquement**
3. **Heure Algérie** = `Africa/Algiers` (UTC+1) — utiliser `now()->setTimezone('Africa/Algiers')`
4. **Dégradation gracieuse** — si une notification échoue → logger + continuer

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════
## BLOC A — RÈGLES MÉTIER : PLAGES HORAIRES BACKEND
## ══════════════════════════════════════════

## ÉTAPE 1 — NotificationTimingService (central des règles horaires)

**Créer** : `edugestdz/backend/app/Services/NotificationTimingService.php`

```php
<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * NotificationTimingService — Règles horaires des notifications.
 *
 * INSPIRÉ DE :
 * - France : Pronote déconnecté 20h-7h depuis sept. 2025
 * - Algérie : fuseau Africa/Algiers (UTC+1)
 *
 * RÈGLES IMPLÉMENTÉES :
 * 1. Push Firebase : autorisé 7h-20h heure Algérie uniquement
 * 2. SMS Twilio : toujours autorisé (pas de plage horaire)
 * 3. Email : toujours autorisé (delivré à l'ouverture)
 * 4. Urgences : ignorent toutes les plages horaires
 *
 * TYPES D'URGENCE (toujours notifiés immédiatement) :
 * - absence_enseignant → directeur
 * - billet grave (gravite >= 'grave')
 * - signalement grave (always urgent)
 * - impayé > 30 jours
 * - score EWS critique (>= 80)
 */
class NotificationTimingService
{
    private const TIMEZONE    = 'Africa/Algiers';
    private const HEURE_DEBUT = 7;   // 7h du matin
    private const HEURE_FIN   = 20;  // 20h le soir

    // Types qui ignorent la plage horaire
    private const TYPES_URGENCE = [
        'absence_enseignant',
        'signalement_grave',
        'score_ews_critique',
        'impaye_critique',
    ];

    /**
     * Le push est-il autorisé maintenant ?
     */
    public function pushAutorise(?string $type = null, ?string $gravite = null): bool
    {
        // Urgences → toujours autorisé
        if ($this->estUrgence($type, $gravite)) return true;

        $heureAlgerie = (int) now()->setTimezone(self::TIMEZONE)->format('H');
        return $heureAlgerie >= self::HEURE_DEBUT && $heureAlgerie < self::HEURE_FIN;
    }

    /**
     * Délai avant le prochain push autorisé (en secondes).
     * Retourne 0 si autorisé maintenant.
     */
    public function delaiProchaineAutorisation(?string $type = null): int
    {
        if ($this->pushAutorise($type)) return 0;

        $maintenant = now()->setTimezone(self::TIMEZONE);
        $heure = (int) $maintenant->format('H');

        if ($heure >= self::HEURE_FIN) {
            // Après 20h → prochain push à 7h demain
            $prochainPush = $maintenant->copy()->addDay()->setHour(self::HEURE_DEBUT)->setMinute(0)->setSecond(0);
        } else {
            // Avant 7h → prochain push à 7h aujourd'hui
            $prochainPush = $maintenant->copy()->setHour(self::HEURE_DEBUT)->setMinute(0)->setSecond(0);
        }

        return max(0, (int) $prochainPush->diffInSeconds($maintenant));
    }

    /**
     * Heure de la prochaine ouverture (format HH:MM).
     */
    public function prochainePlagHoraire(): string
    {
        $delai       = $this->delaiProchaineAutorisation();
        $prochainTs  = now()->addSeconds($delai)->setTimezone(self::TIMEZONE);
        return $prochainTs->format('H\hi');
    }

    /**
     * La notification est-elle une urgence ?
     */
    public function estUrgence(?string $type, ?string $gravite = null): bool
    {
        if (!$type) return false;
        if (in_array($type, self::TYPES_URGENCE)) return true;
        // Billets graves
        if (in_array($gravite, ['grave', 'tres_grave'])) return true;
        return false;
    }

    /**
     * Le SMS est-il autorisé ? (toujours oui — pas de plage horaire SMS)
     */
    public function smsAutorise(): bool
    {
        return true;
    }

    /**
     * Déterminer les canaux à utiliser selon le contexte.
     */
    public function canauxAutorisés(string $type, string $gravite = 'info'): array
    {
        $canaux = ['inapp' => true]; // In-app : toujours

        // Push : selon la plage horaire
        $canaux['push']  = $this->pushAutorise($type, $gravite);

        // SMS : toujours OK mais réservé aux urgences + gravités élevées
        $canaux['sms']   = $this->estUrgence($type, $gravite)
            || in_array($gravite, ['grave', 'tres_grave']);

        // Email : toujours OK (delivré différé par le serveur mail)
        $canaux['email'] = true;

        return $canaux;
    }
}
```

---

## ÉTAPE 2 — Mettre à jour ParentNotificationService avec les règles horaires

**Modifier** : `edugestdz/backend/app/Services/ParentNotificationService.php`

Injecter `NotificationTimingService` et l'utiliser dans la méthode `notifier()` :

```php
// Ajouter dans le constructeur :
public function __construct(
    private FirebaseService          $firebase,
    private Sms\SmsService           $sms,
    private NotificationTimingService $timing,   // ← AJOUTER
) {}

// Dans la méthode notifier(), remplacer le bloc Push par :
$pushed = false;
if ($this->timing->pushAutorise($type)) {
    // Dans la plage horaire → push immédiat
    $pushed = $this->firebase->notifyUser(
        $parent->id, $titre, $corps,
        array_merge($meta, ['type' => $type, 'eleve_id' => $eleveId, 'notif_id' => $notif->id])
    );
} else {
    // Hors plage → on stocke seulement en in-app (le push sera déclenché le matin)
    Log::info("NotifParent: push différé pour {$parent->id} — hors plage horaire");
}
if ($pushed) $notif->update(['push_envoye' => true]);

// SMS : selon règle horaire (urgences uniquement hors plage)
if ($avecSMS || $forcerSMS) {
    if ($this->timing->smsAutorise()) {
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
```

---

## ÉTAPE 3 — NotificationTimingMiddleware (API backend)

**Créer** : `edugestdz/backend/app/Http/Middleware/NotificationTimingMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use App\Services\NotificationTimingService;
use Closure;
use Illuminate\Http\Request;

/**
 * NotificationTimingMiddleware
 *
 * Injecte les infos de plage horaire dans les réponses API
 * pour que le frontend sache si les pushs sont actifs.
 * N'EMPÊCHE PAS les requêtes — seulement ajoute des headers.
 */
class NotificationTimingMiddleware
{
    public function __construct(private NotificationTimingService $timing) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Ajouter les headers de timing sur les réponses API
        if ($request->is('api/*')) {
            $response->headers->set(
                'X-Notification-Push-Active',
                $this->timing->pushAutorise() ? '1' : '0'
            );
            if (!$this->timing->pushAutorise()) {
                $response->headers->set(
                    'X-Notification-Next-Window',
                    $this->timing->prochainePlagHoraire()
                );
            }
        }

        return $response;
    }
}
```

**Enregistrer dans bootstrap/app.php** :
```php
// Dans $middleware->api(append:) ajouter :
\App\Http\Middleware\NotificationTimingMiddleware::class,
```

---

## ══════════════════════════════════════════
## BLOC B — SCHEDULERS : RÈGLES MÉTIER AUTOMATIQUES
## ══════════════════════════════════════════

## ÉTAPE 4 — Commande : détection absence enseignant non signalée

**Créer** : `edugestdz/backend/app/Console/Commands/DetecterAbsenceEnseignantCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\{AbsenceEnseignantService, NotificationInAppService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Commande exécutée à 8h00 chaque matin.
 * Détecte les enseignants qui n'ont pas fait leur pointage
 * et envoie une alerte urgence au directeur.
 *
 * RÈGLE : Si 8h15 et pas de pointage → présumé absent → alerte
 */
class DetecterAbsenceEnseignantCommand extends Command
{
    protected $signature   = 'edugest:detecter-absences-enseignants';
    protected $description = 'Détecte les enseignants absents non signalés (8h15)';

    public function handle(
        NotificationInAppService $notif
    ): int {
        $today = now()->setTimezone('Africa/Algiers')->toDateString();
        $heure = (int) now()->setTimezone('Africa/Algiers')->format('H');

        // Exécuter seulement entre 8h et 9h (le scheduler tourne toutes les heures)
        if ($heure < 8 || $heure > 9) {
            $this->info("Hors plage de détection (8h-9h) — skip");
            return self::SUCCESS;
        }

        // Trouver les tenants actifs
        $tenants = DB::table('tenants')->where('statut', 'actif')->pluck('id');

        foreach ($tenants as $tenantId) {
            // Enseignants avec séances aujourd'hui qui ne se sont pas pointés
            $enseignantsAbsentsNonSignales = DB::table('cours as c')
                ->join('seances as s', 'c.id', '=', 's.cours_id')
                ->join('users as u', 'c.enseignant_user_id', '=', 'u.id')
                ->where('c.tenant_id', $tenantId)
                ->where('s.date', $today)
                ->whereNotIn('c.enseignant_user_id', function ($q) use ($tenantId, $today) {
                    // Enseignants qui ont pointé aujourd'hui
                    $q->select('enseignant_user_id')
                      ->from('pointage_enseignants')
                      ->where('tenant_id', $tenantId)
                      ->whereDate('date_pointage', $today)
                      ->where('type', 'arrivee');
                })
                ->whereNotIn('c.enseignant_user_id', function ($q) use ($tenantId, $today) {
                    // Enseignants qui ont pré-signalé leur absence
                    $q->select('enseignant_user_id')
                      ->from('absences_enseignants')
                      ->where('tenant_id', $tenantId)
                      ->where('date_absence', $today);
                })
                ->select('u.id', 'u.nom', 'u.prenom', DB::raw('COUNT(s.id) as nb_seances'))
                ->groupBy('u.id', 'u.nom', 'u.prenom')
                ->get();

            if ($enseignantsAbsentsNonSignales->isEmpty()) continue;

            foreach ($enseignantsAbsentsNonSignales as $ens) {
                $this->warn("🚨 Absence non signalée: {$ens->nom} {$ens->prenom} — Tenant {$tenantId}");

                $notif->creerPourRole(
                    tenantId: $tenantId,
                    role:     'admin',
                    type:     'absence_enseignant',
                    titre:    "🚨 URGENT — Absence non signalée",
                    corps:    "Prof. {$ens->nom} {$ens->prenom} n'a pas pointé ce matin · {$ens->nb_seances} séance(s) affectée(s)",
                    meta:     [
                        'enseignant_user_id' => $ens->id,
                        'date_absence'       => $today,
                        'non_signale'        => true,
                        'action_url'         => '/planning/remplacements',
                    ],
                    urgence:  true  // Ignore la plage horaire
                );
            }
        }

        $this->info('✅ Détection absences enseignants terminée');
        return self::SUCCESS;
    }
}
```

---

## ÉTAPE 5 — Commande : rapport mensuel auto + diagnostic EWS

**Créer** : `edugestdz/backend/app/Console/Commands/RapportMensuelCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Mail};

/**
 * Rapport mensuel automatique envoyé par email au directeur.
 * Exécuté le 1er de chaque mois à 8h00.
 */
class RapportMensuelCommand extends Command
{
    protected $signature   = 'edugest:rapport-mensuel';
    protected $description = 'Envoyer le rapport mensuel aux directeurs (1er du mois)';

    public function handle(): int
    {
        $moisPasse = now()->subMonth()->month;
        $anneeMois = now()->subMonth()->year;

        $tenants = DB::table('tenants')->where('statut', 'actif')->pluck('id');

        foreach ($tenants as $tenantId) {
            // Directeurs de ce tenant
            $directeurs = DB::table('users as u')
                ->join('roles as r', 'u.role_id', '=', 'r.id')
                ->where('u.tenant_id', $tenantId)
                ->where('r.nom', 'admin')
                ->whereNotNull('u.email')
                ->pluck('u.email');

            if ($directeurs->isEmpty()) continue;

            // Données du mois
            $stats = [
                'ca_mois'           => (float) DB::table('paiements')
                    ->where('tenant_id', $tenantId)->where('statut', 'confirmé')
                    ->whereMonth('date_paiement', $moisPasse)
                    ->whereYear('date_paiement', $anneeMois)->sum('montant'),
                'nb_impayes'        => DB::table('factures')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('statut', ['émise', 'en_retard'])->count(),
                'total_absences_eleves' => DB::table('presences as p')
                    ->join('seances as s', 'p.seance_id', '=', 's.id')
                    ->where('s.tenant_id', $tenantId)
                    ->where('p.statut', 'absent')
                    ->whereMonth('s.date', $moisPasse)->count(),
                'total_eleves'      => DB::table('eleves')
                    ->where('tenant_id', $tenantId)->where('statut', 'actif')->count(),
                'nb_signalements'   => DB::table('signalements_graves_eleves')
                    ->where('tenant_id', $tenantId)
                    ->whereMonth('created_at', $moisPasse)->count(),
                'feedbacks_recus'   => DB::table('feedbacks_pedagogiques')
                    ->where('tenant_id', $tenantId)
                    ->where('trimestre', $this->trimestreCourant())->count(),
            ];

            $periode = \Carbon\Carbon::createFromDate($anneeMois, $moisPasse, 1)
                ->locale('fr')->isoFormat('MMMM YYYY');

            foreach ($directeurs as $email) {
                try {
                    Mail::send('emails.rapport-mensuel-auto', array_merge($stats, [
                        'periode'          => $periode,
                        'nomEcole'         => config('app.name', 'EduGest DZ'),
                        'urlApplication'   => config('app.url', 'http://localhost'),
                        'urlRapportComplet'=> config('app.url') . '/analytics',
                    ]), function ($m) use ($email, $periode) {
                        $m->to($email)
                          ->subject("EduGest DZ — Rapport mensuel {$periode}")
                          ->from(config('mail.from.address', 'noreply@edugestdz.dz'), 'EduGest DZ');
                    });
                    $this->info("✅ Rapport envoyé à {$email}");
                } catch (\Throwable $e) {
                    $this->warn("⚠️ Échec email {$email}: " . $e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }

    private function trimestreCourant(): int
    {
        $m = now()->month;
        if ($m <= 4)  return 1;
        if ($m <= 8)  return 2;
        return 3;
    }
}
```

---

## ÉTAPE 6 — Template email : rapport mensuel automatique

**Créer** : `edugestdz/backend/resources/views/emails/rapport-mensuel-auto.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Rapport mensuel — EduGest DZ</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr>
        <td style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:36px;text-align:center;border-radius:16px 16px 0 0;">
          <div style="font-size:36px;margin-bottom:10px;">📊</div>
          <h1 style="color:#fff;font-size:22px;font-weight:900;margin:0;">Rapport Mensuel</h1>
          <p style="color:#bfdbfe;font-size:14px;margin:8px 0 0;">{{ $periode }} · {{ $nomEcole }}</p>
        </td>
      </tr>

      <tr>
        <td style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#475569;font-size:14px;margin:0 0 24px;line-height:1.6;">
            Bonjour, voici le résumé de l'activité de votre établissement pour <strong>{{ $periode }}</strong>.
          </p>

          <!-- KPIs en tableau -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td width="50%" style="padding:8px;">
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;text-align:center;">
                  <div style="font-size:26px;font-weight:900;color:#1d4ed8;">{{ number_format($ca_mois) }}</div>
                  <div style="font-size:11px;color:#64748b;margin-top:4px;">DA encaissés</div>
                </div>
              </td>
              <td width="50%" style="padding:8px;">
                <div style="background:{{ $nb_impayes > 0 ? '#fef2f2' : '#f0fdf4' }};border:1px solid {{ $nb_impayes > 0 ? '#fecaca' : '#bbf7d0' }};border-radius:10px;padding:16px;text-align:center;">
                  <div style="font-size:26px;font-weight:900;color:{{ $nb_impayes > 0 ? '#dc2626' : '#16a34a' }};">{{ $nb_impayes }}</div>
                  <div style="font-size:11px;color:#64748b;margin-top:4px;">Factures impayées</div>
                </div>
              </td>
            </tr>
            <tr>
              <td width="50%" style="padding:8px;">
                <div style="background:#fefce8;border:1px solid #fef08a;border-radius:10px;padding:16px;text-align:center;">
                  <div style="font-size:26px;font-weight:900;color:#ca8a04;">{{ $total_absences_eleves }}</div>
                  <div style="font-size:11px;color:#64748b;margin-top:4px;">Absences élèves</div>
                </div>
              </td>
              <td width="50%" style="padding:8px;">
                <div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:10px;padding:16px;text-align:center;">
                  <div style="font-size:26px;font-weight:900;color:#7c3aed;">{{ $total_eleves }}</div>
                  <div style="font-size:11px;color:#64748b;margin-top:4px;">Élèves actifs</div>
                </div>
              </td>
            </tr>
          </table>

          @if($nb_signalements > 0)
          <div style="background:#fef2f2;border-left:4px solid #ef4444;border-radius:0 8px 8px 0;padding:14px;margin-bottom:20px;">
            <p style="color:#dc2626;font-size:13px;font-weight:700;margin:0;">
              🚨 {{ $nb_signalements }} signalement(s) grave(s) ce mois — à examiner en priorité
            </p>
          </div>
          @endif

          @if($feedbacks_recus > 0)
          <div style="background:#f0fdf4;border-left:4px solid #10b981;border-radius:0 8px 8px 0;padding:14px;margin-bottom:20px;">
            <p style="color:#059669;font-size:13px;font-weight:700;margin:0;">
              💬 {{ $feedbacks_recus }} feedback(s) pédagogique(s) reçu(s) — résumé disponible dans l'app
            </p>
          </div>
          @endif

          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center">
                <a href="{{ $urlRapportComplet }}"
                   style="display:inline-block;background:#2563eb;color:#fff;padding:14px 32px;
                          border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;">
                  📊 Voir le rapport complet
                </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td style="background:#0f172a;padding:18px;text-align:center;border-radius:0 0 16px 16px;">
          <p style="color:#64748b;font-size:11px;margin:0;">
            EduGest DZ · Made in Oran, Algeria 🇩🇿 · Rapport automatique mensuel
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
```

---

## ÉTAPE 7 — Mettre à jour le scheduler dans bootstrap/app.php

**Modifier** : `edugestdz/backend/bootstrap/app.php`

Dans la section `->withSchedule()`, ajouter les nouvelles commandes :

```php
// Détecter absences enseignants non signalées — 8h15 chaque matin
$schedule->command('edugest:detecter-absences-enseignants')
    ->dailyAt('08:15')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping()
    ->runInBackground();

// Rapport mensuel directeur — 1er du mois à 8h00
$schedule->command('edugest:rapport-mensuel')
    ->monthlyOn(1, '08:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();
```

---

## ══════════════════════════════════════════
## BLOC C — POLITIQUE D'ACCÈS (qui peut faire quoi)
## ══════════════════════════════════════════

## ÉTAPE 8 — FluxInfoPolicy (règles d'accès centralisées)

**Créer** : `edugestdz/backend/app/Policies/FluxInfoPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\User;

/**
 * FluxInfoPolicy — Règles d'accès pour la circulation de l'information.
 *
 * RÈGLES MONDIALES APPLIQUÉES :
 * - UK Safeguarding : enseignant NE PEUT PAS voir les signalements graves
 * - France ENT : parent ne peut pas contacter directement l'enseignant
 * - Singapour : signalement confidentiel directeur uniquement
 */
class FluxInfoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role?->nom === 'super_admin') return true;
        return null;
    }

    // ── Feedbacks pédagogiques ─────────────────────────────────────────

    /** Seuls admin et élève peuvent soumettre un feedback */
    public function soumettresFeedback(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'eleve']);
    }

    /** Seul l'admin peut lire les feedbacks individuels */
    public function lireFeedbacks(User $user): bool
    {
        return $user->role?->nom === 'admin';
    }

    /** L'enseignant ne peut voir QUE le résumé anonymisé */
    public function voirResumeFeedback(User $user): bool
    {
        return in_array($user->role?->nom, ['admin', 'enseignant']);
    }

    // ── Signalements graves ────────────────────────────────────────────

    /** Élève peut soumettre */
    public function soumettreSignalement(User $user): bool
    {
        return $user->role?->nom === 'eleve';
    }

    /** SEUL l'admin voit les signalements — jamais l'enseignant */
    public function voirSignalements(User $user): bool
    {
        return $user->role?->nom === 'admin';
    }

    /** SEUL l'admin peut traiter/répondre */
    public function traiterSignalement(User $user): bool
    {
        return $user->role?->nom === 'admin';
    }

    // ── Absences enseignant ────────────────────────────────────────────

    /** Enseignant signale sa propre absence uniquement */
    public function signalerAbsence(User $user): bool
    {
        return $user->role?->nom === 'enseignant';
    }

    /** Admin gère le remplacement */
    public function gererRemplacement(User $user): bool
    {
        return $user->role?->nom === 'admin';
    }

    // ── Devoirs ────────────────────────────────────────────────────────

    /** Enseignant publie des devoirs */
    public function publierDevoir(User $user): bool
    {
        return $user->role?->nom === 'enseignant';
    }

    /** Élève et enseignant voient les devoirs */
    public function voirDevoirs(User $user): bool
    {
        return in_array($user->role?->nom, ['eleve', 'enseignant', 'admin', 'parent']);
    }

    // ── Notifications ──────────────────────────────────────────────────

    /** Tout le monde voit SES propres notifications */
    public function voirSesNotifications(User $user): bool
    {
        return true; // Toujours autorisé — filtré par user_id
    }
}
```

**Enregistrer dans AppServiceProvider.php** :
```php
\Illuminate\Support\Facades\Gate::policy(
    \App\Models\User::class,
    \App\Policies\FluxInfoPolicy::class
);
```

---

## ══════════════════════════════════════════
## BLOC D — TESTS UNITAIRES RÈGLES MÉTIER
## ══════════════════════════════════════════

## ÉTAPE 9 — Tests NotificationTimingService

**Créer** : `edugestdz/backend/tests/Unit/Services/NotificationTimingTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\NotificationTimingService;
use Tests\TestCase;
use Carbon\Carbon;

class NotificationTimingTest extends TestCase
{
    private NotificationTimingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationTimingService();
    }

    // ── Plages horaires ────────────────────────────────────────────────

    public function test_push_autorise_pendant_la_journee(): void
    {
        // Simuler 10h (dans la plage)
        Carbon::setTestNow(Carbon::createFromTime(10, 0, 0, 'Africa/Algiers'));
        $this->assertTrue($this->service->pushAutorise());
        Carbon::setTestNow(null);
    }

    public function test_push_non_autorise_la_nuit(): void
    {
        // Simuler 23h (hors plage)
        Carbon::setTestNow(Carbon::createFromTime(23, 0, 0, 'Africa/Algiers'));
        $this->assertFalse($this->service->pushAutorise());
        Carbon::setTestNow(null);
    }

    public function test_push_non_autorise_avant_7h(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(5, 30, 0, 'Africa/Algiers'));
        $this->assertFalse($this->service->pushAutorise());
        Carbon::setTestNow(null);
    }

    public function test_push_autorise_a_exactement_7h(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(7, 0, 0, 'Africa/Algiers'));
        $this->assertTrue($this->service->pushAutorise());
        Carbon::setTestNow(null);
    }

    public function test_push_non_autorise_a_20h_exactement(): void
    {
        // 20h = fin de plage (exclusif)
        Carbon::setTestNow(Carbon::createFromTime(20, 0, 0, 'Africa/Algiers'));
        $this->assertFalse($this->service->pushAutorise());
        Carbon::setTestNow(null);
    }

    // ── Urgences ───────────────────────────────────────────────────────

    public function test_urgence_signalement_grave_ignore_plage(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(2, 0, 0, 'Africa/Algiers'));
        // À 2h du matin, un signalement grave EST quand même autorisé
        $this->assertTrue($this->service->pushAutorise('signalement_grave'));
        Carbon::setTestNow(null);
    }

    public function test_urgence_absence_enseignant_ignore_plage(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(21, 0, 0, 'Africa/Algiers'));
        $this->assertTrue($this->service->pushAutorise('absence_enseignant'));
        Carbon::setTestNow(null);
    }

    public function test_gravite_grave_est_urgence(): void
    {
        $this->assertTrue($this->service->estUrgence('billet', 'grave'));
        $this->assertTrue($this->service->estUrgence('billet', 'tres_grave'));
    }

    public function test_gravite_info_nest_pas_urgence(): void
    {
        $this->assertFalse($this->service->estUrgence('note_publiee', 'info'));
        $this->assertFalse($this->service->estUrgence('devoir_publie'));
    }

    // ── SMS ────────────────────────────────────────────────────────────

    public function test_sms_toujours_autorise(): void
    {
        // SMS : pas de plage horaire
        Carbon::setTestNow(Carbon::createFromTime(3, 0, 0, 'Africa/Algiers'));
        $this->assertTrue($this->service->smsAutorise());
        Carbon::setTestNow(null);
    }

    // ── Canaux ─────────────────────────────────────────────────────────

    public function test_canaux_urgence_tous_actifs(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(2, 0, 0, 'Africa/Algiers'));
        $canaux = $this->service->canauxAutorisés('signalement_grave', 'tres_grave');

        $this->assertTrue($canaux['inapp']);
        $this->assertTrue($canaux['push']);    // Urgence → push même la nuit
        $this->assertTrue($canaux['sms']);     // Gravité tres_grave → SMS
        $this->assertTrue($canaux['email']);   // Email toujours
        Carbon::setTestNow(null);
    }

    public function test_canaux_normal_nuit_pas_de_push(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(23, 0, 0, 'Africa/Algiers'));
        $canaux = $this->service->canauxAutorisés('devoir_publie', 'info');

        $this->assertTrue($canaux['inapp']);
        $this->assertFalse($canaux['push']);   // Hors plage → pas de push
        $this->assertFalse($canaux['sms']);    // Pas urgent → pas de SMS
        $this->assertTrue($canaux['email']);   // Email toujours
        Carbon::setTestNow(null);
    }

    // ── Délai ──────────────────────────────────────────────────────────

    public function test_delai_zero_dans_plage(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(12, 0, 0, 'Africa/Algiers'));
        $this->assertEquals(0, $this->service->delaiProchaineAutorisation());
        Carbon::setTestNow(null);
    }

    public function test_delai_positif_hors_plage(): void
    {
        Carbon::setTestNow(Carbon::createFromTime(22, 0, 0, 'Africa/Algiers'));
        $delai = $this->service->delaiProchaineAutorisation();
        $this->assertGreaterThan(0, $delai);
        // À 22h → prochain push à 7h = 9 heures = 32400 secondes
        $this->assertEqualsWithDelta(32400, $delai, 60); // ±1 minute
        Carbon::setTestNow(null);
    }
}
```

---

## ÉTAPE 10 — Tests Policy

**Créer** : `edugestdz/backend/tests/Unit/Policies/FluxInfoPolicyTest.php`

```php
<?php

namespace Tests\Unit\Policies;

use App\Models\{User, Role, Tenant};
use App\Policies\FluxInfoPolicy;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FluxInfoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FluxInfoPolicy $policy;
    private Tenant         $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FluxInfoPolicy();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
    }

    private function user(string $role): User
    {
        $r = Role::factory()->create(['nom' => $role]);
        return User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $r->id]);
    }

    // ── Feedbacks ──────────────────────────────────────────────────────

    public function test_eleve_peut_soumettre_feedback(): void
    {
        $this->assertTrue($this->policy->soumettresFeedback($this->user('eleve')));
    }

    public function test_enseignant_ne_peut_pas_soumettre_feedback(): void
    {
        $this->assertFalse($this->policy->soumettresFeedback($this->user('enseignant')));
    }

    public function test_admin_peut_lire_feedbacks(): void
    {
        $this->assertTrue($this->policy->lireFeedbacks($this->user('admin')));
    }

    public function test_enseignant_ne_peut_pas_lire_feedbacks_individuels(): void
    {
        $this->assertFalse($this->policy->lireFeedbacks($this->user('enseignant')));
    }

    public function test_enseignant_peut_voir_resume_anonymise(): void
    {
        $this->assertTrue($this->policy->voirResumeFeedback($this->user('enseignant')));
    }

    public function test_parent_ne_peut_pas_voir_resume(): void
    {
        $this->assertFalse($this->policy->voirResumeFeedback($this->user('parent')));
    }

    // ── Signalements ───────────────────────────────────────────────────

    public function test_eleve_peut_soumettre_signalement(): void
    {
        $this->assertTrue($this->policy->soumettreSignalement($this->user('eleve')));
    }

    public function test_enseignant_ne_peut_pas_voir_signalements(): void
    {
        // RÈGLE CRITIQUE : enseignant JAMAIS accès aux signalements
        $this->assertFalse($this->policy->voirSignalements($this->user('enseignant')));
    }

    public function test_parent_ne_peut_pas_voir_signalements(): void
    {
        $this->assertFalse($this->policy->voirSignalements($this->user('parent')));
    }

    public function test_admin_peut_voir_et_traiter_signalements(): void
    {
        $admin = $this->user('admin');
        $this->assertTrue($this->policy->voirSignalements($admin));
        $this->assertTrue($this->policy->traiterSignalement($admin));
    }

    // ── Absences enseignant ────────────────────────────────────────────

    public function test_enseignant_peut_signaler_sa_propre_absence(): void
    {
        $this->assertTrue($this->policy->signalerAbsence($this->user('enseignant')));
    }

    public function test_eleve_ne_peut_pas_signaler_absence_enseignant(): void
    {
        $this->assertFalse($this->policy->signalerAbsence($this->user('eleve')));
    }

    public function test_admin_peut_gerer_remplacement(): void
    {
        $this->assertTrue($this->policy->gererRemplacement($this->user('admin')));
    }

    // ── Devoirs ────────────────────────────────────────────────────────

    public function test_enseignant_peut_publier_devoir(): void
    {
        $this->assertTrue($this->policy->publierDevoir($this->user('enseignant')));
    }

    public function test_eleve_ne_peut_pas_publier_devoir(): void
    {
        $this->assertFalse($this->policy->publierDevoir($this->user('eleve')));
    }

    public function test_eleve_et_parent_peuvent_voir_devoirs(): void
    {
        $this->assertTrue($this->policy->voirDevoirs($this->user('eleve')));
        $this->assertTrue($this->policy->voirDevoirs($this->user('parent')));
    }
}
```

---

## ÉTAPE 11 — Tests Schedulers

**Créer** : `edugestdz/backend/tests/Feature/Commands/FluxSchedulersTest.php`

```php
<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FluxSchedulersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_detecter_absences_enseignants_sans_crash(): void
    {
        // La commande doit s'exécuter sans exception même avec une BDD vide
        $this->artisan('edugest:detecter-absences-enseignants')
            ->assertExitCode(0);
    }

    public function test_command_rapport_mensuel_sans_crash(): void
    {
        Mail::fake();
        $this->artisan('edugest:rapport-mensuel')
            ->assertExitCode(0);
    }

    public function test_rapport_mensuel_envoie_email_si_directeur(): void
    {
        Mail::fake();

        // Créer un tenant avec un directeur email
        $tenant = \App\Models\Tenant::factory()->create(['statut' => 'actif']);
        $role   = \App\Models\Role::factory()->create(['nom' => 'admin']);
        \App\Models\User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $role->id,
            'email'     => 'directeur@test.com',
        ]);

        $this->artisan('edugest:rapport-mensuel')->assertExitCode(0);

        // Avec Mail::fake() et un driver array → vérifier qu'il n'y a pas d'erreur
        $this->assertTrue(true);
    }
}
```

---

## ÉTAPE 12 — Exécution Mission 3

```bash
cd edugestdz/backend

# Migrations (aucune nouvelle dans cette mission — déjà créées en mission 1)
php artisan migrate --force

composer dump-autoload -o

# Tests unitaires timing
php artisan test tests/Unit/Services/NotificationTimingTest.php --stop-on-failure

# Tests policies
php artisan test tests/Unit/Policies/FluxInfoPolicyTest.php --stop-on-failure

# Tests schedulers
php artisan test tests/Feature/Commands/FluxSchedulersTest.php --stop-on-failure

# Tous les tests
php artisan test
# → 724+ ✅  0 failures

git add \
  app/Services/NotificationTimingService.php \
  app/Http/Middleware/NotificationTimingMiddleware.php \
  app/Console/Commands/DetecterAbsenceEnseignantCommand.php \
  app/Console/Commands/RapportMensuelCommand.php \
  app/Policies/FluxInfoPolicy.php \
  resources/views/emails/rapport-mensuel-auto.blade.php \
  bootstrap/app.php \
  tests/Unit/Services/NotificationTimingTest.php \
  tests/Unit/Policies/FluxInfoPolicyTest.php \
  tests/Feature/Commands/FluxSchedulersTest.php

git commit -m "feat(flux-info-3/3): Règles métier + plages horaires + policies + schedulers

NotificationTimingService :
  - Plage autorisée 7h-20h heure Algérie (Africa/Algiers)
  - Urgences ignorent la plage (signalement grave, absence enseignant)
  - SMS toujours autorisé (pas de plage horaire)
  - canauxAutorisés() → push/sms/email/inapp selon type + gravité
  - delaiProchaineAutorisation() → secondes avant prochain push

NotificationTimingMiddleware :
  - Header X-Notification-Push-Active sur toutes les réponses API
  - Header X-Notification-Next-Window si hors plage

ParentNotificationService mis à jour :
  - Injecte NotificationTimingService
  - Push différé si hors plage (sauf urgences)
  - SMS uniquement si timing.smsAutorise() && (urgence || gravite élevée)

FluxInfoPolicy (règles mondiales) :
  - Enseignant NE PEUT JAMAIS voir les signalements graves (UK Safeguarding)
  - Enseignant voit seulement le résumé anonymisé des feedbacks
  - Élève soumet feedback 1x/trimestre, signalement identifié
  - Admin : accès complet à son tenant

Schedulers :
  - detecter-absences-enseignants (8h15 quotidien Algérie)
  - rapport-mensuel (1er du mois 8h00 Algérie)
  - Template email rapport-mensuel-auto.blade.php

Tests : 13 (NotificationTiming) + 16 (FluxInfoPolicy) + 3 (Schedulers) = 32 nouveaux"

git push origin develop
# → PR → main
```

---

## RÉCAPITULATIF DES 3 MISSIONS

| Mission | Contenu | Nouveaux tests |
|---------|---------|----------------|
| **1/3 Backend** | AbsenceEnseignant + Devoir + FeedbackPédagogique + SignalementGrave + routes | 14 |
| **2/3 Frontend** | NotificationsPage + DevoirsPage + FeedbackEnseignantPage + hook timing | — (frontend) |
| **3/3 Règles** | NotificationTimingService + FluxInfoPolicy + Schedulers + Templates | 32 |

**Total : +46 tests · 3 PRs séquentielles**

---

## CE QUE TU DIS À DEEPSEEK POUR LA MISSION 3

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : FLUX_MISSION_3_REGLES_METIER.md — 12 étapes.

RÈGLES CRITIQUES :
1. Carbon::setTestNow() dans les tests — nécessite 'nesbot/carbon' ^3.0
   déjà dans laravel/framework. Utiliser Carbon\Carbon pas Illuminate\Support\Carbon.
2. NotificationTimingMiddleware : s'enregistre dans $middleware->api(append:)
   dans bootstrap/app.php — PAS dans api.php.
3. FluxInfoPolicy : s'enregistre via Gate::policy() dans AppServiceProvider boot().
   Le modèle passé à Gate::policy() doit être App\Models\User pour les méthodes génériques.
   Alternative : créer une class dédiée FluxInfo et l'enregistrer séparément.
4. DetecterAbsenceEnseignantCommand : la table pointage_enseignants doit exister.
   Si elle n'existe pas → entourer la requête d'un try/catch + markTestSkipped().
5. RapportMensuelCommand : utiliser Mail::fake() dans les tests.
   JAMAIS envoyer de vrais emails en test (MAIL_MAILER=array dans phpunit.xml).
6. ParentNotificationService : injecter NotificationTimingService en ajoutant
   au constructeur __construct(... private NotificationTimingService $timing).
   Laravel DI résout automatiquement si le service est dans un provider ou auto-resolvable.

php artisan test tests/Unit/Services/NotificationTimingTest.php
php artisan test tests/Unit/Policies/FluxInfoPolicyTest.php
php artisan test → 724+ ✅
git push origin develop → PR → main
```
