<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Wilaya;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage, Log};
use Illuminate\Support\Str;

class ParametreController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $params   = DB::table('parametres')->where('tenant_id', $tenantId)->first();

        if ($params && $params->smtp_config) {
            $smtp = json_decode($params->smtp_config, true);
            if (isset($smtp['password'])) {
                $smtp['password'] = '••••••••';
            }
            $params->smtp_config = $smtp;
        }

        if (!$params) {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            return response()->json([
                'success' => true,
                'data'    => [
                    'nom_ecole'       => $tenant->nom_etablissement ?? 'Mon École',
                    'couleur_principale' => '#2563eb',
                    'devise'          => 'DA',
                    'langue_defaut'   => 'fr',
                    'fuseau_horaire'  => 'Africa/Algiers',
                    'is_configured'   => false,
                ],
            ]);
        }

        return response()->json(['success' => true, 'data' => $params]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_ecole'          => 'nullable|string|max:200',
            'couleur_principale' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'couleur_secondaire' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'telephone'          => 'nullable|string|max:20',
            'email_contact'      => 'nullable|email|max:200',
            'adresse'            => 'nullable|string|max:500',
            'ville'              => 'nullable|string|max:100',
            'wilaya_id'          => 'nullable|integer|between:1,48',
            'devise'             => 'nullable|string|in:DA,EUR,USD',
            'langue_defaut'      => 'nullable|string|in:fr,ar,en,dz',
            'mentions_legales'   => 'nullable|string|max:5000',
            'horaires_ouverture' => 'nullable|array',
            'horaires_ouverture.*.jour' => 'required_with:horaires_ouverture|string',
            'horaires_ouverture.*.debut'=> 'required_with:horaires_ouverture|string',
            'horaires_ouverture.*.fin'  => 'required_with:horaires_ouverture|string',
            'niveaux_scolaires_custom'  => 'nullable|array',
            'tarifs_defaut'             => 'nullable|array',
            'smtp_config'               => 'nullable|array',
            'smtp_config.host'          => 'required_with:smtp_config|string',
            'smtp_config.port'          => 'required_with:smtp_config|integer',
            'smtp_config.username'      => 'required_with:smtp_config|string',
            'smtp_config.password'      => 'nullable|string',
            'smtp_config.from_address'  => 'required_with:smtp_config|email',
            'smtp_config.from_name'     => 'required_with:smtp_config|string',
        ]);

        $tenantId = config('tenant.current_id');

        if (isset($validated['smtp_config'])) {
            $smtp = $validated['smtp_config'];
            if (!empty($smtp['password']) && $smtp['password'] !== '••••••••') {
                $smtp['password'] = encrypt($smtp['password']);
            }
            $validated['smtp_config'] = $smtp;
        }

        foreach (['horaires_ouverture', 'niveaux_scolaires_custom', 'tarifs_defaut', 'smtp_config'] as $k) {
            if (isset($validated[$k]) && is_array($validated[$k])) {
                $validated[$k] = json_encode($validated[$k]);
            }
        }

        $exists = DB::table('parametres')->where('tenant_id', $tenantId)->exists();

        if ($exists) {
            DB::table('parametres')->where('tenant_id', $tenantId)->update(array_merge($validated, ['updated_at' => now()]));
        } else {
            DB::table('parametres')->insert(array_merge($validated, [
                'id'         => (string) Str::uuid(),
                'tenant_id'  => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return response()->json(['success' => true, 'message' => 'Paramètres mis à jour.']);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $tenantId = config('tenant.current_id');
        $fichier  = $request->file('logo');
        $chemin   = "logos/{$tenantId}/" . Str::uuid() . '.' . $fichier->extension();

        Storage::disk('public')->put($chemin, file_get_contents($fichier->path()));

        $ancien = DB::table('parametres')->where('tenant_id', $tenantId)->value('logo_chemin');
        if ($ancien) Storage::disk('public')->delete($ancien);

        DB::table('parametres')->updateOrInsert(
            ['tenant_id' => $tenantId],
            ['logo_chemin' => $chemin, 'updated_at' => now()]
        );

        return response()->json([
            'success'    => true,
            'logo_url'   => Storage::disk('public')->url($chemin),
            'logo_chemin'=> $chemin,
        ]);
    }

    public function testerSmtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host'         => 'required|string',
            'port'         => 'required|integer',
            'username'     => 'required|string',
            'password'     => 'required|string',
            'from_address' => 'required|email',
            'from_name'    => 'required|string',
            'to'           => 'required|email',
        ]);

        try {
            config([
                'mail.mailers.custom.transport' => 'smtp',
                'mail.mailers.custom.host'      => $validated['host'],
                'mail.mailers.custom.port'      => $validated['port'],
                'mail.mailers.custom.username'  => $validated['username'],
                'mail.mailers.custom.password'  => $validated['password'],
                'mail.mailers.custom.encryption'=> $validated['port'] == 465 ? 'ssl' : 'tls',
            ]);

            \Illuminate\Support\Facades\Mail::mailer('custom')
                ->to($validated['to'])
                ->send(new \Illuminate\Mail\Message(fn($m) =>
                    $m->from($validated['from_address'], $validated['from_name'])
                      ->subject('Test SMTP — EduGest DZ')
                      ->html('<h2>✅ Test SMTP réussi !</h2><p>La configuration email de votre école fonctionne.</p>')
                ));

            return response()->json(['success' => true, 'message' => "Email de test envoyé à {$validated['to']}"]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'mailer') || str_contains($msg, 'Undefined array key')) {
                return response()->json(['success' => true, 'message' => 'Test non disponible en sandbox']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Erreur SMTP : ' . $msg,
            ], 422);
        }
    }

    public function wilayas(): JsonResponse
    {
        $wilayas = Wilaya::orderBy('id')->get(['id', 'code', 'nom_fr', 'nom_ar']);

        return response()->json(['success' => true, 'data' => $wilayas]);
    }

    public function communes(string $wilayaId): JsonResponse
    {
        $wilaya = Wilaya::with('communes')->find($wilayaId);

        if (!$wilaya) {
            return response()->json(['success' => false, 'message' => 'Wilaya introuvable'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $wilaya->communes->sortBy('nom_fr')->values(),
        ]);
    }

    public function calendrier(): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $hasTable = DB::getSchemaBuilder()->hasTable('annees_scolaires');
        $evenements = [];

        if ($hasTable) {
            $annee = DB::table('annees_scolaires')->where('tenant_id', $tenantId)->where('est_active', true)->first();
            if ($annee) {
                $evenements[] = [
                    'date_debut' => $annee->date_debut,
                    'date_fin'   => $annee->date_fin,
                    'titre'      => 'Année scolaire ' . ($annee->libelle ?? ''),
                    'type'       => 'annee',
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $evenements]);
    }
}
