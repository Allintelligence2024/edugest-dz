<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImmutableAuditService
{
    public function exporterJournalier(?string $tenantId = null, ?string $date = null): array
    {
        $date = $date ?? now()->subDay()->format('Y-m-d');

        $logs = DB::table('audit_logs')
            ->where('created_at', '>=', $date . ' 00:00:00')
            ->where('created_at', '<=', $date . ' 23:59:59')
            ->when($tenantId, fn($q) => $q->where('properties->tenant_id', $tenantId))
            ->orderBy('created_at')
            ->get()
            ->toArray();

        if (empty($logs)) {
            return ['exportes' => 0, 'hash' => null];
        }

        $contenu   = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $hash      = hash('sha256', $contenu);
        $signature = hash_hmac('sha256', $hash, config('app.key'));

        $chemin    = "audit/{$date}/audit-{$date}" . ($tenantId ? "-{$tenantId}" : '') . ".json";
        Storage::disk('local')->put($chemin, $contenu);

        DB::table('audit_log_exports')->insert([
            'id'             => \Illuminate\Support\Str::uuid(),
            'tenant_id'      => $tenantId,
            'periode'        => $date,
            'type_export'    => 'daily',
            'nb_entrees'     => count($logs),
            'hash_sha256'    => $hash,
            'signature'      => $signature,
            'fichier_chemin' => $chemin,
            'integrite_ok'   => true,
            'exporte_le'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Log::info("Audit exporté et signé: {$date} — " . count($logs) . " entrées, hash: {$hash}");

        return [
            'exportes' => count($logs),
            'hash'     => $hash,
            'chemin'   => $chemin,
            'date'     => $date,
        ];
    }

    public function verifierIntegrite(string $exportId): array
    {
        $export = DB::table('audit_log_exports')->where('id', $exportId)->first();

        if (!$export) {
            return ['ok' => false, 'raison' => 'Export non trouvé'];
        }

        if (!Storage::disk('local')->exists($export->fichier_chemin)) {
            return ['ok' => false, 'raison' => 'Fichier manquant — possible suppression'];
        }

        $contenuActuel    = Storage::disk('local')->get($export->fichier_chemin);
        $hashActuel       = hash('sha256', $contenuActuel);
        $signatureActuelle = hash_hmac('sha256', $hashActuel, config('app.key'));

        $hashOk      = hash_equals($export->hash_sha256, $hashActuel);
        $signatureOk = hash_equals($export->signature, $signatureActuelle);

        if (!$hashOk || !$signatureOk) {
            DB::table('audit_log_exports')
                ->where('id', $exportId)
                ->update(['integrite_ok' => false, 'updated_at' => now()]);

            Log::critical('AUDIT INTEGRITY FAILURE', [
                'export_id'     => $exportId,
                'periode'       => $export->periode,
                'hash_attendu'  => $export->hash_sha256,
                'hash_actuel'   => $hashActuel,
            ]);

            return [
                'ok'     => false,
                'raison' => 'Intégrité compromise — le fichier a été modifié après signature',
            ];
        }

        return ['ok' => true, 'hash' => $hashActuel, 'periode' => $export->periode];
    }
}
