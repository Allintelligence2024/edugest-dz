# 🤖 MISSION DEEPSEEK — Intégration WhatsApp Business API + Google Classroom
## EduGest DZ · Branche : develop · 6 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 432 ✅ · 0 régression

---

## CONTEXTE — Pourquoi ces 2 intégrations

### WhatsApp Business API officielle (Meta Cloud API)
- Les parents algériens utilisent WhatsApp à 95%+ (SMS tombent en spam)
- L'API officielle Meta envoie des messages template sans risque de ban
- Différence vs l'existant : on a Twilio SMS + webhook entrant artisanal
  → Cette mission remplace/complète par l'API officielle Meta (gratuit jusqu'à 1000 conv/mois)
- Fonctionnalités nouvelles : templates approuvés Meta, boutons interactifs,
  media (PDF bulletin envoyé via WhatsApp), statuts de livraison (lu/reçu)

### Google Classroom
- Les centres de cours particuliers utilisent souvent Google Classroom en parallèle
- Synchronisation bidirectionnelle : notes saisies dans EduGest → Classroom,
  devoirs Classroom → EduGest
- Parents voient déjà les devoirs sur Classroom → notifications EduGest en plus

### RÈGLES ABSOLUES
1. 0 régression — les 418+ tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Les 2 intégrations sont **optionnelles** — si API key absente → fallback silencieux
4. Ne pas modifier les services existants (SmsService, ParentNotificationService)
   → Ajouter seulement de nouveaux services

---

## ÉTAPE 0 — Synchroniser + Installer packages

```bash
git checkout develop && git pull origin main
cd edugestdz/backend

# WhatsApp Business API (Meta Graph API) — pas de package, on utilise Http Laravel
# Google Classroom
composer require google/apiclient:^2.15

# Vérifier l'installation
composer show google/apiclient
```

---

## PARTIE A — WHATSAPP BUSINESS API OFFICIELLE
## ══════════════════════════════════════════════

## ÉTAPE 1 — Migration : table messages WhatsApp

**Créer :**
`edugestdz/backend/database/migrations/2026_07_06_300000_create_whatsapp_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historique des messages WhatsApp envoyés et reçus
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('wa_message_id')->nullable()->unique(); // ID retourné par Meta
            $table->string('telephone');          // numéro destinataire/expéditeur
            $table->string('direction');          // outbound | inbound
            $table->string('type')->default('text');
            // Valeurs : text | template | image | document | audio | interactive
            $table->string('template_name')->nullable(); // nom du template Meta utilisé
            $table->text('contenu');              // corps du message ou JSON template
            $table->string('statut')->default('pending');
            // Valeurs : pending | sent | delivered | read | failed | received
            $table->string('wa_status')->nullable(); // statut brut de Meta
            $table->timestamp('envoye_le')->nullable();
            $table->timestamp('livre_le')->nullable();
            $table->timestamp('lu_le')->nullable();
            $table->text('erreur')->nullable();   // message d'erreur si failed
            $table->uuid('reference_id')->nullable();   // lien vers l'entité (eleve_id, facture_id...)
            $table->string('reference_type')->nullable(); // type de l'entité
            $table->timestamps();

            $table->index(['tenant_id', 'telephone'],    'idx_wa_tenant_tel');
            $table->index(['tenant_id', 'statut'],       'idx_wa_tenant_statut');
            $table->index(['wa_message_id'],              'idx_wa_msg_id');
            $table->index(['reference_id', 'reference_type'], 'idx_wa_ref');
        });

        // Templates WhatsApp approuvés par Meta
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('nom');               // nom du template (ex: absence_notification)
            $table->string('langue')->default('fr'); // fr | ar | en
            $table->string('categorie');
            // Meta categories : MARKETING | UTILITY | AUTHENTICATION
            $table->text('contenu_exemple');     // exemple du template pour affichage
            $table->string('statut_meta')->default('PENDING');
            // Valeurs : PENDING | APPROVED | REJECTED
            $table->boolean('actif')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'nom', 'langue'], 'uniq_template_nom_lang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
    }
};
```

---

## ÉTAPE 2 — WhatsAppBusinessService

**Créer :**
`edugestdz/backend/app/Services/WhatsAppBusinessService.php`

```php
<?php

namespace App\Services;

use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service WhatsApp Business Cloud API (Meta officiel).
 *
 * Prérequis :
 *   1. Créer un compte Meta Business sur business.facebook.com
 *   2. Créer une app Meta → ajouter le produit "WhatsApp"
 *   3. Récupérer : Phone Number ID, Access Token permanent (System User)
 *   4. Ajouter dans .env :
 *      WHATSAPP_PHONE_ID=xxxxxxxxxxxxxxxx
 *      WHATSAPP_TOKEN=EAAxxxxx...
 *      WHATSAPP_VERIFY_TOKEN=mon_secret_webhook
 *   5. Configurer le webhook Meta → https://app.edugest.dz/api/v1/whatsapp/webhook
 *      avec le verify token
 *
 * Templates à créer dans Meta Business Manager :
 *   - absence_notification (UTILITY) : "Votre enfant {{1}} est absent le {{2}}"
 *   - facture_impayee (UTILITY)       : "Facture {{1}} de {{2}} DA impayée"
 *   - bulletin_disponible (UTILITY)   : "Bulletin de {{1}} disponible — Moy: {{2}}/20"
 *   - note_publiee (UTILITY)          : "Nouvelle note pour {{1}} : {{2}}/20 en {{3}}"
 *   - convocation_parents (UTILITY)   : "Convocation : {{1}} — Contacter l'établissement"
 */
class WhatsAppBusinessService
{
    private string $apiUrl;
    private string $phoneId;
    private string $token;
    private bool   $enabled;

    public function __construct()
    {
        $this->phoneId  = config('services.whatsapp.phone_id', '');
        $this->token    = config('services.whatsapp.token', '');
        $this->apiUrl   = 'https://graph.facebook.com/v20.0/' . $this->phoneId . '/messages';
        $this->enabled  = !empty($this->phoneId) && !empty($this->token);
    }

    // ══════════════════════════════════════════════════════════════
    // ENVOI — Message texte simple
    // ══════════════════════════════════════════════════════════════

    /**
     * Envoyer un message texte libre.
     * Note : après une conversation template, on peut envoyer du texte libre
     * dans les 24h suivant la réponse du client.
     */
    public function envoyerTexte(string $telephone, string $message, ?string $tenantId = null): ?WhatsappMessage
    {
        if (!$this->enabled) {
            Log::info("WhatsApp désactivé (WHATSAPP_PHONE_ID manquant) — message simulé : {$telephone}");
            return null;
        }

        $tel = $this->normaliserTelephone($telephone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $tel,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $message],
        ];

        return $this->envoyer($tel, 'text', $message, $payload, $tenantId);
    }

    // ══════════════════════════════════════════════════════════════
    // ENVOI — Templates approuvés Meta
    // ══════════════════════════════════════════════════════════════

    /**
     * Envoyer via un template approuvé par Meta.
     * Les templates permettent d'initier la conversation (pas de limite 24h).
     *
     * @param string $telephone
     * @param string $templateName  Nom du template dans Meta Business Manager
     * @param array  $params        Variables du template [['type'=>'text','text'=>'valeur'],...]
     * @param string $langue        fr | ar | en
     */
    public function envoyerTemplate(
        string  $telephone,
        string  $templateName,
        array   $params = [],
        string  $langue = 'fr',
        ?string $tenantId = null
    ): ?WhatsappMessage {
        if (!$this->enabled) {
            Log::info("WhatsApp template simulé : {$templateName} → {$telephone}");
            return null;
        }

        $tel = $this->normaliserTelephone($telephone);

        $components = [];
        if (!empty($params)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => $params,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $tel,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $this->codeLangue($langue)],
                'components' => $components,
            ],
        ];

        $contenu = json_encode(['template' => $templateName, 'params' => $params]);
        return $this->envoyer($tel, 'template', $contenu, $payload, $tenantId, $templateName);
    }

    // ══════════════════════════════════════════════════════════════
    // TEMPLATES MÉTIER — Méthodes spécialisées EduGest DZ
    // ══════════════════════════════════════════════════════════════

    /** Notification absence élève → parents */
    public function notifierAbsence(string $telephone, string $prenomEleve, string $date): ?WhatsappMessage
    {
        return $this->envoyerTemplate($telephone, 'absence_notification', [
            ['type' => 'text', 'text' => $prenomEleve],
            ['type' => 'text', 'text' => $date],
        ], 'fr', config('tenant.current_id'));
    }

    /** Notification facture impayée → parents */
    public function notifierFactureImpayee(string $telephone, string $numFacture, string $montant): ?WhatsappMessage
    {
        return $this->envoyerTemplate($telephone, 'facture_impayee', [
            ['type' => 'text', 'text' => $numFacture],
            ['type' => 'text', 'text' => $montant . ' DA'],
        ], 'fr', config('tenant.current_id'));
    }

    /** Notification bulletin disponible → parents */
    public function notifierBulletin(string $telephone, string $prenomEleve, string $moyenne): ?WhatsappMessage
    {
        return $this->envoyerTemplate($telephone, 'bulletin_disponible', [
            ['type' => 'text', 'text' => $prenomEleve],
            ['type' => 'text', 'text' => $moyenne],
        ], 'fr', config('tenant.current_id'));
    }

    /** Envoyer un document PDF (bulletin, facture) via WhatsApp */
    public function envoyerDocument(
        string $telephone,
        string $urlDocument,
        string $nomFichier,
        string $caption = '',
        ?string $tenantId = null
    ): ?WhatsappMessage {
        if (!$this->enabled) return null;

        $tel = $this->normaliserTelephone($telephone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'   => $tel,
            'type' => 'document',
            'document' => [
                'link'     => $urlDocument,
                'caption'  => $caption,
                'filename' => $nomFichier,
            ],
        ];

        return $this->envoyer($tel, 'document', $caption ?: $nomFichier, $payload, $tenantId);
    }

    /** Message avec boutons interactifs (OUI/NON pour justification absence) */
    public function envoyerAvecBoutons(
        string $telephone,
        string $message,
        array  $boutons,
        ?string $tenantId = null
    ): ?WhatsappMessage {
        if (!$this->enabled) return null;

        $tel = $this->normaliserTelephone($telephone);

        $rows = array_map(fn($b) => [
            'id'    => $b['id'],
            'title' => $b['label'],
        ], $boutons);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'   => $tel,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $message],
                'action' => [
                    'buttons' => array_map(fn($b) => [
                        'type'  => 'reply',
                        'reply' => ['id' => $b['id'], 'title' => $b['label']],
                    ], array_slice($boutons, 0, 3)), // Max 3 boutons Meta
                ],
            ],
        ];

        return $this->envoyer($tel, 'interactive', $message, $payload, $tenantId);
    }

    // ══════════════════════════════════════════════════════════════
    // WEBHOOK — Traitement messages entrants
    // ══════════════════════════════════════════════════════════════

    /** Vérifier le webhook Meta (GET) */
    public function verifierWebhook(string $mode, string $challenge, string $verifyToken): ?string
    {
        $expected = config('services.whatsapp.verify_token', '');
        if ($mode === 'subscribe' && $verifyToken === $expected) {
            return $challenge;
        }
        return null;
    }

    /**
     * Traiter un webhook entrant (POST de Meta).
     * Retourne le message traité ou null.
     */
    public function traiterWebhookEntrant(array $payload): ?array
    {
        try {
            $entry   = $payload['entry'][0] ?? null;
            $changes = $entry['changes'][0] ?? null;
            $value   = $changes['value'] ?? null;

            if (!$value) return null;

            // ── Message entrant ───────────────────────────────────
            if (isset($value['messages'])) {
                $message = $value['messages'][0];
                $from    = $message['from'] ?? '';
                $type    = $message['type'] ?? 'text';
                $body    = match ($type) {
                    'text'        => $message['text']['body'] ?? '',
                    'button'      => $message['button']['text'] ?? '',
                    'interactive' => $message['interactive']['button_reply']['title'] ?? '',
                    default       => '[media]',
                };

                // Enregistrer en BDD
                WhatsappMessage::create([
                    'tenant_id'    => config('tenant.current_id', '00000000-0000-0000-0000-000000000000'),
                    'wa_message_id'=> $message['id'],
                    'telephone'    => $from,
                    'direction'    => 'inbound',
                    'type'         => $type,
                    'contenu'      => $body,
                    'statut'       => 'received',
                    'envoye_le'    => now(),
                ]);

                Log::info("WhatsApp entrant: {$from} → \"{$body}\"");

                return [
                    'type'      => 'message',
                    'from'      => $from,
                    'body'      => $body,
                    'message_id'=> $message['id'],
                ];
            }

            // ── Mise à jour statut (delivered/read) ───────────────
            if (isset($value['statuses'])) {
                $status = $value['statuses'][0];
                WhatsappMessage::where('wa_message_id', $status['id'])
                    ->update([
                        'wa_status'  => $status['status'],
                        'livre_le'   => $status['status'] === 'delivered' ? now() : null,
                        'lu_le'      => $status['status'] === 'read'      ? now() : null,
                        'statut'     => match ($status['status']) {
                            'sent'      => 'sent',
                            'delivered' => 'delivered',
                            'read'      => 'read',
                            'failed'    => 'failed',
                            default     => 'sent',
                        },
                    ]);

                return ['type' => 'status', 'status' => $status['status']];
            }

        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage());
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVÉ — Envoi HTTP et enregistrement
    // ══════════════════════════════════════════════════════════════

    private function envoyer(
        string  $telephone,
        string  $type,
        string  $contenu,
        array   $payload,
        ?string $tenantId,
        ?string $templateName = null
    ): ?WhatsappMessage {
        $message = WhatsappMessage::create([
            'tenant_id'     => $tenantId ?? config('tenant.current_id', '00000000-0000-0000-0000-000000000000'),
            'telephone'     => $telephone,
            'direction'     => 'outbound',
            'type'          => $type,
            'template_name' => $templateName,
            'contenu'       => $contenu,
            'statut'        => 'pending',
            'envoye_le'     => now(),
        ]);

        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post($this->apiUrl, $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['messages'][0]['id'])) {
                $message->update([
                    'wa_message_id' => $data['messages'][0]['id'],
                    'statut'        => 'sent',
                ]);
            } else {
                $erreur = $data['error']['message'] ?? json_encode($data);
                $message->update(['statut' => 'failed', 'erreur' => $erreur]);
                Log::warning("WhatsApp envoi échoué [{$telephone}]: {$erreur}");
            }

        } catch (\Throwable $e) {
            $message->update(['statut' => 'failed', 'erreur' => $e->getMessage()]);
            Log::error("WhatsApp exception [{$telephone}]: " . $e->getMessage());
        }

        return $message->fresh();
    }

    /**
     * Normaliser le numéro algérien.
     * 0555123456 → 213555123456
     * +213555123456 → 213555123456
     */
    private function normaliserTelephone(string $tel): string
    {
        $tel = preg_replace('/\D/', '', $tel);
        if (str_starts_with($tel, '0'))    return '213' . substr($tel, 1);
        if (str_starts_with($tel, '213'))  return $tel;
        if (str_starts_with($tel, '+213')) return substr($tel, 1);
        return $tel;
    }

    private function codeLangue(string $lang): string
    {
        return match ($lang) {
            'ar'    => 'ar',
            'en'    => 'en_US',
            default => 'fr',
        };
    }
}
```

---

## ÉTAPE 3 — Model WhatsappMessage

**Créer :** `edugestdz/backend/app/Models/WhatsappMessage.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WhatsappMessage extends Model
{
    use HasUuids;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'tenant_id', 'wa_message_id', 'telephone', 'direction', 'type',
        'template_name', 'contenu', 'statut', 'wa_status',
        'envoye_le', 'livre_le', 'lu_le', 'erreur',
        'reference_id', 'reference_type',
    ];

    protected $casts = [
        'envoye_le' => 'datetime',
        'livre_le'  => 'datetime',
        'lu_le'     => 'datetime',
    ];

    public function scopeEntrant($q)  { return $q->where('direction', 'inbound'); }
    public function scopeSortant($q)  { return $q->where('direction', 'outbound'); }
    public function scopeEchoue($q)   { return $q->where('statut', 'failed'); }
}
```

---

## ÉTAPE 4 — WhatsAppController (webhook officiel + dashboard)

**Modifier :**
`edugestdz/backend/app/Http/Controllers/Api/V1/WhatsAppController.php`

Remplacer complètement le fichier :

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessage;
use App\Services\WhatsAppBusinessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppBusinessService $wa) {}

    // ══════════════════════════════════════════════════════════════
    // WEBHOOK META — Vérification (GET)
    // ══════════════════════════════════════════════════════════════

    /**
     * Meta appelle ce endpoint en GET pour vérifier le webhook.
     * NE PAS mettre de middleware auth dessus.
     */
    public function verifyWebhook(Request $request): \Illuminate\Http\Response
    {
        $challenge = $this->wa->verifierWebhook(
            $request->query('hub_mode', ''),
            $request->query('hub_challenge', ''),
            $request->query('hub_verify_token', '')
        );

        if ($challenge) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    // ══════════════════════════════════════════════════════════════
    // WEBHOOK META — Messages entrants (POST)
    // NE PAS mettre de middleware auth — Meta n'envoie pas de JWT
    // ══════════════════════════════════════════════════════════════

    public function webhook(Request $request): JsonResponse
    {
        // Vérifier la signature HMAC de Meta (sécurité)
        $appSecret   = config('services.whatsapp.app_secret', '');
        $signature   = $request->header('X-Hub-Signature-256', '');
        $payload     = $request->getContent();

        if ($appSecret && $signature) {
            $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
            if (!hash_equals($expected, $signature)) {
                Log::warning('WhatsApp webhook: signature HMAC invalide');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $data   = $request->all();
        $result = $this->wa->traiterWebhookEntrant($data);

        // Si message entrant → traiter les commandes
        if ($result && $result['type'] === 'message') {
            $this->traiterCommandeEntrant($result['from'], $result['body']);
        }

        // Meta attend toujours un 200 — sinon il réessaie en boucle
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Traiter les commandes WhatsApp entrants des parents.
     * OUI → justifier absence
     * INFO → résumé enfant
     * FACTURE → liste des factures impayées
     * BULLETIN → renvoyer le dernier bulletin
     */
    private function traiterCommandeEntrant(string $from, string $body): void
    {
        $body = strtoupper(trim($body));
        $tel  = ltrim($from, '+');

        // Trouver le parent par numéro
        $parent = \App\Models\User::where('role', 'parent')
            ->where(function ($q) use ($tel) {
                $q->where('telephone', 'like', '%' . substr($tel, -9) . '%');
            })
            ->first();

        if (!$parent) {
            Log::info("WhatsApp entrant: numéro inconnu {$from} — message ignoré");
            return;
        }

        $eleves = \App\Models\Eleve::whereHas('parents', fn($q) => $q->where('users.id', $parent->id))->get();
        if ($eleves->isEmpty()) return;

        $eleve = $eleves->first();
        $reponse = null;

        switch (true) {
            case in_array($body, ['OUI', 'YES', '1', 'JUSTIFIER']):
                // Justifier la dernière absence
                $absence = \App\Models\AbsenceJournaliere::where('eleve_id', $eleve->id)
                    ->where('statut', 'non_justifiée')
                    ->latest('date_absence')->first();

                if ($absence) {
                    $absence->update(['statut' => 'justifiée', 'motif' => 'Justifiée par parent via WhatsApp']);
                    $reponse = "✅ L'absence de {$eleve->prenom} du {$absence->date_absence} a été justifiée. Merci.";
                } else {
                    $reponse = "✅ Aucune absence en attente de justification pour {$eleve->prenom}.";
                }
                break;

            case in_array($body, ['INFO', '2']):
                $absences = \App\Models\AbsenceJournaliere::where('eleve_id', $eleve->id)
                    ->whereMonth('date_absence', now()->month)->count();
                $reponse = "📊 *{$eleve->prenom} {$eleve->nom}*\n"
                    . "Niveau : {$eleve->niveau_scolaire}\n"
                    . "Absences ce mois : {$absences}\n"
                    . "Pour plus d'infos, connectez-vous sur l'application EduGest DZ.";
                break;

            case in_array($body, ['AIDE', 'HELP', '?', '3']):
                $reponse = "🎓 *EduGest DZ — Commandes WhatsApp*\n\n"
                    . "📝 *OUI* — Justifier la dernière absence\n"
                    . "📊 *INFO* — Résumé de votre enfant\n"
                    . "❓ *AIDE* — Afficher ce message\n\n"
                    . "📱 Application mobile disponible sur Android/iOS.";
                break;

            default:
                // Message non reconnu → ignorer silencieusement
                Log::info("WhatsApp: commande non reconnue '{$body}' de {$from}");
                return;
        }

        if ($reponse) {
            $this->wa->envoyerTexte($from, $reponse, config('tenant.current_id'));
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DASHBOARD — Statistiques et historique
    // ══════════════════════════════════════════════════════════════

    /**
     * @OA\Get(path="/api/v1/whatsapp/stats", summary="Stats WhatsApp Business",
     *   tags={"WhatsApp"}, security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Statistiques messages"))
     */
    public function stats(): JsonResponse
    {
        $total     = WhatsappMessage::where('direction', 'outbound')->count();
        $envoyes   = WhatsappMessage::where('statut', 'sent')->count();
        $livres    = WhatsappMessage::where('statut', 'delivered')->count();
        $lus       = WhatsappMessage::where('statut', 'read')->count();
        $echoues   = WhatsappMessage::where('statut', 'failed')->count();
        $entrants  = WhatsappMessage::where('direction', 'inbound')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_envoyes'    => $total,
                'livres'           => $livres,
                'lus'              => $lus,
                'echoues'          => $echoues,
                'entrants'         => $entrants,
                'taux_livraison'   => $total > 0 ? round(($livres / $total) * 100, 1) : 0,
                'taux_lecture'     => $total > 0 ? round(($lus / $total) * 100, 1) : 0,
                'api_active'       => !empty(config('services.whatsapp.phone_id')),
                'ce_mois'          => WhatsappMessage::whereMonth('created_at', now()->month)
                    ->where('direction', 'outbound')->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(path="/api/v1/whatsapp/messages", summary="Historique messages WhatsApp",
     *   tags={"WhatsApp"}, security={{"bearerAuth":{}}})
     */
    public function historique(Request $request): JsonResponse
    {
        $messages = WhatsappMessage::orderByDesc('created_at')
            ->when($request->filled('direction'), fn($q) => $q->where('direction', $request->direction))
            ->when($request->filled('statut'),    fn($q) => $q->where('statut', $request->statut))
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * @OA\Post(path="/api/v1/whatsapp/envoyer", summary="Envoyer un message WhatsApp manuellement",
     *   tags={"WhatsApp"}, security={{"bearerAuth":{}}})
     */
    public function envoyerManuel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'telephone' => 'required|string',
            'message'   => 'required|string|max:1000',
        ]);

        $msg = $this->wa->envoyerTexte($validated['telephone'], $validated['message']);

        return response()->json([
            'success' => true,
            'data'    => $msg,
            'message' => $msg ? 'Message envoyé' : 'WhatsApp non configuré (vérifier WHATSAPP_PHONE_ID)',
        ]);
    }
}
```

---

## ÉTAPE 5 — Config + Variables .env pour WhatsApp

**Modifier :** `edugestdz/backend/config/services.php`

Ajouter dans le tableau `return [...]` :

```php
'whatsapp' => [
    'phone_id'     => env('WHATSAPP_PHONE_ID', ''),
    'token'        => env('WHATSAPP_TOKEN', ''),
    'app_secret'   => env('WHATSAPP_APP_SECRET', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'edugest_dz_webhook_2026'),
],
```

**Modifier :** `edugestdz/backend/.env.example`

Ajouter :
```dotenv
# ── WhatsApp Business API (Meta officiel) ────────────────────
# Obtenir sur : business.facebook.com → WhatsApp → API Setup
WHATSAPP_PHONE_ID=          # ID du numéro WhatsApp Business
WHATSAPP_TOKEN=             # Token permanent (System User)
WHATSAPP_APP_SECRET=        # App Secret (pour vérification HMAC webhook)
WHATSAPP_VERIFY_TOKEN=edugest_dz_webhook_2026
```

---

## ÉTAPE 6 — Routes WhatsApp

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\WhatsAppController;

// ── Webhook WhatsApp META — PUBLIC (pas d'auth JWT) ──────────────────
// Meta appelle ces routes pour vérifier et recevoir les messages
Route::get('/v1/whatsapp/webhook',  [WhatsAppController::class, 'verifyWebhook']);
Route::post('/v1/whatsapp/webhook', [WhatsAppController::class, 'webhook'])
    ->middleware('throttle:60,1');

// ── Dashboard WhatsApp — Admin ────────────────────────────────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/whatsapp')->group(function () {
    Route::get('/stats',      [WhatsAppController::class, 'stats']);
    Route::get('/messages',   [WhatsAppController::class, 'historique']);
    Route::post('/envoyer',   [WhatsAppController::class, 'envoyerManuel']);
});
```

---

## PARTIE B — GOOGLE CLASSROOM
## ══════════════════════════════

## ÉTAPE 7 — Migration Google Classroom

**Créer :**
`edugestdz/backend/database/migrations/2026_07_06_400000_create_google_classroom_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Connexions Google Classroom par utilisateur (enseignant)
        Schema::create('google_classroom_connexions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('user_id');             // enseignant EduGest DZ
            $table->string('google_id');          // ID Google de l'utilisateur
            $table->string('email_google');
            $table->text('access_token');         // token OAuth2 (chiffré)
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expire_le')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['user_id'], 'uniq_gc_user');
            $table->index(['tenant_id', 'actif'], 'idx_gc_tenant');
        });

        // Cours Google Classroom liés aux groupes EduGest DZ
        Schema::create('google_classroom_cours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('groupe_id');            // groupe EduGest DZ
            $table->uuid('connexion_id');          // connexion enseignant
            $table->string('gc_course_id');        // ID cours Google Classroom
            $table->string('gc_course_nom');
            $table->string('gc_course_section')->nullable();
            $table->boolean('sync_notes_actif')->default(true);
            $table->boolean('sync_devoirs_actif')->default(true);
            $table->timestamp('derniere_sync')->nullable();
            $table->timestamps();

            $table->unique(['groupe_id', 'gc_course_id'], 'uniq_gc_groupe_course');
        });

        // Log des synchronisations
        Schema::create('google_classroom_syncs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('cours_id');
            $table->string('type');               // notes_vers_gc | notes_depuis_gc | devoirs
            $table->integer('elements_traites')->default(0);
            $table->integer('elements_reussis')->default(0);
            $table->integer('elements_echoues')->default(0);
            $table->text('details')->nullable();   // JSON des erreurs
            $table->timestamp('debut');
            $table->timestamp('fin')->nullable();
            $table->timestamps();

            $table->index(['cours_id', 'type'], 'idx_gc_sync_cours');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_classroom_syncs');
        Schema::dropIfExists('google_classroom_cours');
        Schema::dropIfExists('google_classroom_connexions');
    }
};
```

---

## ÉTAPE 8 — GoogleClassroomService

**Créer :**
`edugestdz/backend/app/Services/GoogleClassroomService.php`

```php
<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Evaluation;
use App\Models\GoogleClassroomConnexion;
use App\Models\GoogleClassroomCours;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service Google Classroom API.
 *
 * Prérequis :
 *   1. Créer un projet Google Cloud Console
 *   2. Activer l'API Google Classroom
 *   3. Créer des credentials OAuth2 (Web Application)
 *   4. Ajouter dans .env :
 *      GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
 *      GOOGLE_CLIENT_SECRET=xxxxx
 *      GOOGLE_REDIRECT_URI=https://app.edugest.dz/api/v1/google/callback
 *
 * Scopes nécessaires :
 *   - https://www.googleapis.com/auth/classroom.courses.readonly
 *   - https://www.googleapis.com/auth/classroom.coursework.students
 *   - https://www.googleapis.com/auth/classroom.rosters.readonly
 *   - https://www.googleapis.com/auth/classroom.student-submissions.students.readonly
 */
class GoogleClassroomService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private bool   $enabled;
    private string $baseUrl = 'https://classroom.googleapis.com/v1';

    public function __construct()
    {
        $this->clientId     = config('services.google.client_id', '');
        $this->clientSecret = config('services.google.client_secret', '');
        $this->redirectUri  = config('services.google.redirect_uri', '');
        $this->enabled      = !empty($this->clientId) && !empty($this->clientSecret);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH OAuth2
    // ══════════════════════════════════════════════════════════════

    /** URL de connexion OAuth2 Google */
    public function getAuthUrl(string $userId): string
    {
        $state  = base64_encode(json_encode(['user_id' => $userId, 'ts' => time()]));
        $scopes = implode(' ', [
            'https://www.googleapis.com/auth/classroom.courses.readonly',
            'https://www.googleapis.com/auth/classroom.coursework.students',
            'https://www.googleapis.com/auth/classroom.rosters.readonly',
            'https://www.googleapis.com/auth/classroom.student-submissions.students.readonly',
            'https://www.googleapis.com/auth/userinfo.email',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => $scopes,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ]);
    }

    /** Échanger le code OAuth2 contre un token et l'enregistrer */
    public function handleCallback(string $code, string $state): GoogleClassroomConnexion
    {
        $stateData = json_decode(base64_decode($state), true);
        $userId    = $stateData['user_id'] ?? null;

        if (!$userId) throw new \RuntimeException('State OAuth2 invalide');

        // Échanger le code
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Erreur OAuth2 Google: ' . $response->body());
        }

        $tokens = $response->json();

        // Récupérer l'email Google
        $userInfo = Http::withToken($tokens['access_token'])
            ->get('https://www.googleapis.com/oauth2/v1/userinfo')
            ->json();

        // Enregistrer la connexion
        return GoogleClassroomConnexion::updateOrCreate(
            ['user_id' => $userId],
            [
                'tenant_id'       => config('tenant.current_id'),
                'google_id'       => $userInfo['id'],
                'email_google'    => $userInfo['email'],
                'access_token'    => Crypt::encryptString($tokens['access_token']),
                'refresh_token'   => isset($tokens['refresh_token'])
                    ? Crypt::encryptString($tokens['refresh_token'])
                    : null,
                'token_expire_le' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'actif'           => true,
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════
    // COURS — Liste et liaison
    // ══════════════════════════════════════════════════════════════

    /** Lister les cours Google Classroom de l'enseignant */
    public function listerCours(GoogleClassroomConnexion $connexion): array
    {
        $token = $this->getAccessToken($connexion);
        if (!$token) return [];

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/courses", [
                'teacherId' => 'me',
                'courseStates' => 'ACTIVE',
            ]);

        if (!$response->successful()) {
            Log::warning('Google Classroom listerCours erreur: ' . $response->body());
            return [];
        }

        return $response->json()['courses'] ?? [];
    }

    /** Lister les élèves d'un cours Google Classroom */
    public function listerEleves(GoogleClassroomConnexion $connexion, string $courseId): array
    {
        $token = $this->getAccessToken($connexion);
        if (!$token) return [];

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/courses/{$courseId}/students");

        return $response->json()['students'] ?? [];
    }

    // ══════════════════════════════════════════════════════════════
    // SYNCHRONISATION — Notes EduGest → Google Classroom
    // ══════════════════════════════════════════════════════════════

    /**
     * Exporter les notes EduGest DZ vers Google Classroom.
     * Crée un CourseWork dans Classroom avec les notes comme grades.
     */
    public function exporterNotesVersClassroom(
        GoogleClassroomCours $lien,
        Evaluation $evaluation
    ): array {
        $connexion = $lien->connexion;
        $token     = $this->getAccessToken($connexion);
        if (!$token) return ['reussies' => 0, 'echouees' => 0, 'erreur' => 'Token invalide'];

        $notes     = Note::where('evaluation_id', $evaluation->id)->with('eleve')->get();
        $reussies  = 0;
        $echouees  = 0;
        $erreurs   = [];

        // Créer le devoir dans Classroom si pas encore fait
        $courseWork = $this->creerOuMajCourseWork($token, $lien->gc_course_id, $evaluation);
        if (!$courseWork) {
            return ['reussies' => 0, 'echouees' => 0, 'erreur' => 'Impossible de créer le devoir dans Classroom'];
        }

        // Récupérer la liste des élèves Classroom pour matcher avec EduGest
        $elevesClassroom = $this->listerEleves($connexion, $lien->gc_course_id);
        $emailIndex = collect($elevesClassroom)
            ->keyBy(fn($e) => strtolower($e['profile']['emailAddress'] ?? ''));

        foreach ($notes as $note) {
            if (!$note->note) continue;

            // Trouver l'élève dans Classroom par email
            $emailEleve = strtolower($note->eleve->email ?? '');
            if (!isset($emailIndex[$emailEleve])) {
                $erreurs[] = "Élève {$note->eleve->nom_complet} non trouvé dans Classroom";
                $echouees++;
                continue;
            }

            $studentId    = $emailIndex[$emailEleve]['userId'];
            $noteNormalisee = ($note->note / $evaluation->note_sur) * 100; // Sur 100 pour Classroom

            $response = Http::withToken($token)
                ->patch("{$this->baseUrl}/courses/{$lien->gc_course_id}/courseWork/{$courseWork['id']}/studentSubmissions/{$studentId}", [
                    'assignedGrade' => $noteNormalisee,
                    'draftGrade'    => $noteNormalisee,
                ]);

            if ($response->successful()) $reussies++;
            else { $echouees++; $erreurs[] = "Erreur pour {$note->eleve->nom_complet}"; }
        }

        // Enregistrer le log
        \App\Models\GoogleClassroomSync::create([
            'cours_id'         => $lien->id,
            'type'             => 'notes_vers_gc',
            'elements_traites' => $notes->count(),
            'elements_reussis' => $reussies,
            'elements_echoues' => $echouees,
            'details'          => json_encode($erreurs),
            'debut'            => now(),
            'fin'              => now(),
        ]);

        $lien->update(['derniere_sync' => now()]);

        return ['reussies' => $reussies, 'echouees' => $echouees, 'erreurs' => $erreurs];
    }

    /**
     * Importer les notes depuis Google Classroom vers EduGest.
     * Récupère les grades des devoirs Classroom et les crée/met à jour dans EduGest.
     */
    public function importerNotesDepuisClassroom(
        GoogleClassroomCours $lien,
        string $gcCourseWorkId,
        string $groupeId
    ): array {
        $connexion = $lien->connexion;
        $token     = $this->getAccessToken($connexion);
        if (!$token) return ['importees' => 0, 'erreur' => 'Token invalide'];

        // Récupérer les soumissions du devoir
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/courses/{$lien->gc_course_id}/courseWork/{$gcCourseWorkId}/studentSubmissions");

        $soumissions = $response->json()['studentSubmissions'] ?? [];
        $importees = 0;

        // Récupérer le devoir pour les infos
        $devoir = Http::withToken($token)
            ->get("{$this->baseUrl}/courses/{$lien->gc_course_id}/courseWork/{$gcCourseWorkId}")
            ->json();

        // Créer l'évaluation dans EduGest si elle n'existe pas
        $evaluation = Evaluation::firstOrCreate(
            ['groupe_id' => $groupeId, 'gc_coursework_id' => $gcCourseWorkId],
            [
                'tenant_id'       => config('tenant.current_id'),
                'titre'           => $devoir['title'] ?? 'Devoir Google Classroom',
                'type_eval'       => 'devoir_maison',
                'date_evaluation' => now()->format('Y-m-d'),
                'note_sur'        => $devoir['maxPoints'] ?? 100,
                'coefficient'     => 1,
                'trimestre'       => 'T1',
                'created_by'      => $connexion->user_id,
            ]
        );

        foreach ($soumissions as $soumission) {
            $grade = $soumission['assignedGrade'] ?? null;
            if ($grade === null) continue;

            // Trouver l'élève EduGest par email Google
            $userId = $soumission['userId'];
            $profil = Http::withToken($token)
                ->get("https://classroom.googleapis.com/v1/userProfiles/{$userId}")
                ->json();
            $emailGoogle = $profil['emailAddress'] ?? '';

            $eleve = \App\Models\Eleve::where('email', $emailGoogle)
                ->whereHas('inscriptions', fn($q) => $q->where('groupe_id', $groupeId))
                ->first();

            if (!$eleve) continue;

            Note::updateOrCreate(
                ['evaluation_id' => $evaluation->id, 'eleve_id' => $eleve->id],
                [
                    'tenant_id' => config('tenant.current_id'),
                    'note'      => ($grade / ($devoir['maxPoints'] ?? 100)) * $evaluation->note_sur,
                    'saisie_par'=> $connexion->user_id,
                ]
            );
            $importees++;
        }

        $lien->update(['derniere_sync' => now()]);
        return ['importees' => $importees];
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVÉ — Helpers
    // ══════════════════════════════════════════════════════════════

    private function creerOuMajCourseWork(string $token, string $courseId, Evaluation $eval): ?array
    {
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/courses/{$courseId}/courseWork", [
                'title'       => $eval->titre,
                'description' => "Évaluation EduGest DZ — Coeff. {$eval->coefficient}",
                'maxPoints'   => $eval->note_sur,
                'workType'    => 'ASSIGNMENT',
                'state'       => 'PUBLISHED',
                'dueDate'     => [
                    'year'  => (int) $eval->date_evaluation->format('Y'),
                    'month' => (int) $eval->date_evaluation->format('m'),
                    'day'   => (int) $eval->date_evaluation->format('d'),
                ],
            ]);

        if ($response->successful()) return $response->json();

        Log::warning('Google Classroom creerCourseWork: ' . $response->body());
        return null;
    }

    private function getAccessToken(GoogleClassroomConnexion $connexion): ?string
    {
        // Si le token expire dans moins de 5 min → le rafraîchir
        if ($connexion->token_expire_le && $connexion->token_expire_le->subMinutes(5)->isPast()) {
            $refresh = Crypt::decryptString($connexion->refresh_token ?? '');
            if (!$refresh) return null;

            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refresh,
                'grant_type'    => 'refresh_token',
            ]);

            if ($response->successful()) {
                $tokens = $response->json();
                $connexion->update([
                    'access_token'    => Crypt::encryptString($tokens['access_token']),
                    'token_expire_le' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                ]);
                return $tokens['access_token'];
            }
            return null;
        }

        return Crypt::decryptString($connexion->access_token);
    }
}
```

---

## ÉTAPE 9 — GoogleClassroomController

**Créer :**
`edugestdz/backend/app/Http/Controllers/Api/V1/GoogleClassroomController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoogleClassroomConnexion;
use App\Models\GoogleClassroomCours;
use App\Models\Evaluation;
use App\Services\GoogleClassroomService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GoogleClassroomController extends Controller
{
    public function __construct(private GoogleClassroomService $gc) {}

    /** URL OAuth2 pour connecter Google Classroom */
    public function authUrl(): JsonResponse
    {
        $url = $this->gc->getAuthUrl(auth('api')->id());
        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }

    /** Callback OAuth2 Google */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $connexion = $this->gc->handleCallback(
                $request->query('code', ''),
                $request->query('state', '')
            );
            return redirect(config('app.frontend_url', '/') . '/profil?gc=connected');
        } catch (\Throwable $e) {
            return redirect(config('app.frontend_url', '/') . '/profil?gc=error&msg=' . urlencode($e->getMessage()));
        }
    }

    /** Statut de la connexion Google Classroom */
    public function statutConnexion(): JsonResponse
    {
        $connexion = GoogleClassroomConnexion::where('user_id', auth('api')->id())
            ->where('actif', true)->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'connecte'    => (bool) $connexion,
                'email'       => $connexion?->email_google,
                'expire_le'   => $connexion?->token_expire_le,
                'api_active'  => !empty(config('services.google.client_id')),
            ],
        ]);
    }

    /** Lister les cours Google Classroom de l'enseignant connecté */
    public function listerCours(): JsonResponse
    {
        $connexion = GoogleClassroomConnexion::where('user_id', auth('api')->id())
            ->where('actif', true)->firstOrFail();

        $cours = $this->gc->listerCours($connexion);
        return response()->json(['success' => true, 'data' => $cours]);
    }

    /** Lier un cours Classroom à un groupe EduGest */
    public function lierCours(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'groupe_id'   => 'required|uuid|exists:groupes,id',
            'gc_course_id'=> 'required|string',
            'gc_course_nom'=> 'required|string',
            'sync_notes_actif'  => 'boolean',
            'sync_devoirs_actif'=> 'boolean',
        ]);

        $connexion = GoogleClassroomConnexion::where('user_id', auth('api')->id())
            ->where('actif', true)->firstOrFail();

        $lien = GoogleClassroomCours::updateOrCreate(
            ['groupe_id' => $validated['groupe_id'], 'gc_course_id' => $validated['gc_course_id']],
            [
                'tenant_id'          => config('tenant.current_id'),
                'connexion_id'       => $connexion->id,
                'gc_course_nom'      => $validated['gc_course_nom'],
                'sync_notes_actif'   => $validated['sync_notes_actif'] ?? true,
                'sync_devoirs_actif' => $validated['sync_devoirs_actif'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $lien,
            'message' => 'Cours Classroom lié au groupe EduGest DZ',
        ], 201);
    }

    /** Synchroniser les notes EduGest → Classroom */
    public function syncNotesVersClassroom(Request $request, string $lienId): JsonResponse
    {
        $lien       = GoogleClassroomCours::with('connexion')->findOrFail($lienId);
        $evaluation = Evaluation::findOrFail($request->evaluation_id);

        $result = $this->gc->exporterNotesVersClassroom($lien, $evaluation);

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => "{$result['reussies']} notes exportées vers Google Classroom",
        ]);
    }

    /** Importer les notes depuis Classroom → EduGest */
    public function syncDepuisClassroom(Request $request, string $lienId): JsonResponse
    {
        $validated = $request->validate([
            'gc_coursework_id' => 'required|string',
            'groupe_id'        => 'required|uuid|exists:groupes,id',
        ]);

        $lien   = GoogleClassroomCours::with('connexion')->findOrFail($lienId);
        $result = $this->gc->importerNotesDepuisClassroom(
            $lien,
            $validated['gc_coursework_id'],
            $validated['groupe_id']
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => "{$result['importees']} notes importées depuis Google Classroom",
        ]);
    }

    /** Déconnecter Google Classroom */
    public function deconnecter(): JsonResponse
    {
        GoogleClassroomConnexion::where('user_id', auth('api')->id())
            ->update(['actif' => false]);

        return response()->json(['success' => true, 'message' => 'Google Classroom déconnecté']);
    }
}
```

---

## ÉTAPE 10 — Modèles Google Classroom

**Créer :** `edugestdz/backend/app/Models/GoogleClassroomConnexion.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class GoogleClassroomConnexion extends Model {
    use HasUuids;
    protected $table = 'google_classroom_connexions';
    protected $fillable = ['tenant_id','user_id','google_id','email_google','access_token','refresh_token','token_expire_le','actif'];
    protected $hidden   = ['access_token','refresh_token'];
    protected $casts    = ['token_expire_le'=>'datetime','actif'=>'boolean'];
    public function user()  { return $this->belongsTo(User::class); }
    public function cours() { return $this->hasMany(GoogleClassroomCours::class, 'connexion_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/GoogleClassroomCours.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class GoogleClassroomCours extends Model {
    use HasUuids;
    protected $table = 'google_classroom_cours';
    protected $fillable = ['tenant_id','groupe_id','connexion_id','gc_course_id','gc_course_nom','gc_course_section','sync_notes_actif','sync_devoirs_actif','derniere_sync'];
    protected $casts = ['sync_notes_actif'=>'boolean','sync_devoirs_actif'=>'boolean','derniere_sync'=>'datetime'];
    public function connexion() { return $this->belongsTo(GoogleClassroomConnexion::class, 'connexion_id'); }
    public function groupe()    { return $this->belongsTo(Groupe::class); }
    public function syncs()     { return $this->hasMany(GoogleClassroomSync::class, 'cours_id'); }
}
```

**Créer :** `edugestdz/backend/app/Models/GoogleClassroomSync.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class GoogleClassroomSync extends Model {
    use HasUuids;
    protected $table = 'google_classroom_syncs';
    protected $fillable = ['cours_id','type','elements_traites','elements_reussis','elements_echoues','details','debut','fin'];
    protected $casts = ['debut'=>'datetime','fin'=>'datetime'];
    public function cours() { return $this->belongsTo(GoogleClassroomCours::class, 'cours_id'); }
}
```

---

## ÉTAPE 11 — Config Google + Variables .env

**Modifier :** `edugestdz/backend/config/services.php`

```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri'  => env('GOOGLE_REDIRECT_URI', ''),
],
```

**Modifier :** `edugestdz/backend/.env.example`

```dotenv
# ── Google Classroom API ──────────────────────────────────────
# Obtenir sur : console.cloud.google.com → APIs → Classroom API
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxx
GOOGLE_REDIRECT_URI=https://app.edugest.dz/api/v1/google/classroom/callback
```

---

## ÉTAPE 12 — Routes Google Classroom

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\GoogleClassroomController;

// Callback OAuth2 Google (public — pas d'auth JWT)
Route::get('/v1/google/classroom/callback', [GoogleClassroomController::class, 'callback']);

// Endpoints authentifiés
Route::middleware(['auth:api', 'tenant'])->prefix('v1/google/classroom')->group(function () {
    Route::get('/auth-url',    [GoogleClassroomController::class, 'authUrl']);
    Route::get('/statut',      [GoogleClassroomController::class, 'statutConnexion']);
    Route::get('/cours',       [GoogleClassroomController::class, 'listerCours']);
    Route::post('/lier',       [GoogleClassroomController::class, 'lierCours']);
    Route::post('/deconnecter',[GoogleClassroomController::class, 'deconnecter']);
    Route::post('/sync/{id}/vers-classroom',  [GoogleClassroomController::class, 'syncNotesVersClassroom']);
    Route::post('/sync/{id}/depuis-classroom',[GoogleClassroomController::class, 'syncDepuisClassroom']);
});
```

---

## ÉTAPE 13 — Tests

**Créer :**
`edugestdz/backend/tests/Feature/Controllers/IntegrationsTest.php`

```php
<?php
namespace Tests\Feature\Controllers;
use App\Models\User;
use App\Services\WhatsAppBusinessService;
use App\Services\GoogleClassroomService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── WhatsApp Business API ──────────────────────────────────

    public function test_webhook_meta_verification_get(): void
    {
        config(['services.whatsapp.verify_token' => 'mon_secret']);
        $this->get('/api/v1/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_challenge'    => 'abc123',
            'hub_verify_token' => 'mon_secret',
        ]))->assertStatus(200)->assertSee('abc123');
    }

    public function test_webhook_meta_token_invalide_retourne_403(): void
    {
        config(['services.whatsapp.verify_token' => 'mon_secret']);
        $this->get('/api/v1/whatsapp/webhook?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_challenge'    => 'abc123',
            'hub_verify_token' => 'mauvais_token',
        ]))->assertStatus(403);
    }

    public function test_webhook_message_entrant(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id'   => 'wamid.test123',
                            'from' => '213555000001',
                            'type' => 'text',
                            'text' => ['body' => 'OUI'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $this->postJson('/api/v1/whatsapp/webhook', $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_stats_whatsapp_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/whatsapp/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['success','data' => ['total_envoyes','livres','lus','api_active']]);
    }

    public function test_historique_messages_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/whatsapp/messages')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_envoyer_message_sans_config(): void
    {
        config(['services.whatsapp.phone_id' => '']);
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/whatsapp/envoyer', [
                'telephone' => '0555000001',
                'message'   => 'Test',
            ])
            ->assertStatus(200)  // Pas d'erreur — juste désactivé silencieusement
            ->assertJsonPath('success', true);
    }

    public function test_whatsapp_service_normalise_telephone(): void
    {
        $service = app(WhatsAppBusinessService::class);
        $reflect = new \ReflectionClass($service);
        $method  = $reflect->getMethod('normaliserTelephone');
        $method->setAccessible(true);

        $this->assertEquals('213555123456', $method->invoke($service, '0555123456'));
        $this->assertEquals('213555123456', $method->invoke($service, '+213555123456'));
        $this->assertEquals('213555123456', $method->invoke($service, '213555123456'));
    }

    // ── Google Classroom ───────────────────────────────────────

    public function test_statut_connexion_google_non_connecte(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/google/classroom/statut')
            ->assertStatus(200)
            ->assertJsonPath('data.connecte', false);
    }

    public function test_auth_url_google_genere_url(): void
    {
        config(['services.google.client_id' => 'test_client_id']);
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/google/classroom/auth-url')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_webhook_sans_auth_public(): void
    {
        // Le webhook Meta doit être public (pas de 401)
        $this->postJson('/api/v1/whatsapp/webhook', ['object' => 'test'])
            ->assertStatus(200);
    }

    public function test_callback_google_sans_code_redirige(): void
    {
        // Sans code → redirection (pas de 500)
        $response = $this->get('/api/v1/google/classroom/callback?error=access_denied');
        $this->assertContains($response->getStatusCode(), [302, 301, 200]);
    }

    public function test_acces_stats_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/whatsapp/stats')->assertStatus(401);
    }
}
```

---

## ÉTAPE 14 — Migration + Tests + Commit

```bash
cd edugestdz/backend

# 1. Packages
composer require google/apiclient:^2.15

# 2. Migration
php artisan migrate

# 3. Autoload
composer dump-autoload -o

# 4. Tests
php artisan test --parallel
# → 0 régression + 13 nouveaux tests verts

# 5. Commit
git add .
git commit -m "feat: Intégration WhatsApp Business API officielle (Meta) + Google Classroom — Templates, webhook, HMAC, OAuth2, sync notes bidirectionnel + 13 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_WHATSAPP_GOOGLE_CLASSROOM.md — 14 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les tests existants restent verts.
3. composer require google/apiclient:^2.15 AVANT de créer les fichiers.
4. WhatsApp webhook GET + POST = PUBLICS (pas de middleware auth:api).
   Meta ne s'authentifie pas en JWT — juste en HMAC X-Hub-Signature-256.
5. Google Classroom callback GET = PUBLIC également.
6. WhatsAppBusinessService : si WHATSAPP_PHONE_ID vide → retourner null silencieusement.
   Ne jamais faire échouer l'app si WhatsApp n'est pas configuré.
7. GoogleClassroomService : si GOOGLE_CLIENT_ID vide → authUrl() retourne '#'.
8. Les tokens Google sont chiffrés avec Crypt::encryptString() — jamais en clair en BDD.
9. Évaluation model peut ne pas avoir le champ gc_coursework_id :
   → l'ajouter dans une migration séparée si nécessaire :
   Schema::table('evaluations', function(Blueprint $t) {
       $t->string('gc_coursework_id')->nullable()->after('description');
   });
10. Le WhatsAppController existant est REMPLACÉ complètement (étape 4).

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
