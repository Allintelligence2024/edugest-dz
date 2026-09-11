<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sprint 6 § 5 — Loi 18-07 : politique de rétention.
 *
 * Purge des fichiers d'export contenant des données personnelles :
 *  - exports RGPD (droit de portabilité, archives annuelles) : 30 jours —
 *    le temps de les télécharger, ils n'ont pas vocation à s'accumuler ;
 *  - exports d'audit (preuves d'intégrité quotidiennes) : 1 an.
 *
 * Les lignes de demandes_rgpd et consentements_rgpd ne sont JAMAIS
 * supprimées : ce sont les preuves de conformité (qui a demandé quoi, qui
 * a consenti à quoi). Les conserver est l'obligation, pas la fuite.
 *
 * Planifié quotidiennement à 04 h 10 (bootstrap/app.php) et exposé au cron
 * serverless via /api/v1/cron/rgpd-retention.
 */
class RgpdRetentionCommand extends Command
{
    protected $signature = 'edugest:rgpd-retention
                            {--jours-exports=30 : Rétention des exports RGPD, en jours}
                            {--jours-audit=365 : Rétention des exports d’audit, en jours}
                            {--dry-run : Lister les fichiers sans les supprimer}';

    protected $description = 'Loi 18-07 : purger les fichiers d’export RGPD et d’audit au-delà de la rétention';

    public function handle(): int
    {
        $joursExports = max(1, (int) $this->option('jours-exports'));
        $joursAudit   = max(1, (int) $this->option('jours-audit'));
        $dryRun       = (bool) $this->option('dry-run');

        $rapport = [
            'exports' => $this->purgerRepertoire('exports', $joursExports, $dryRun),
            'audit'   => $this->purgerRepertoire('audit', $joursAudit, $dryRun),
        ];

        $total = $rapport['exports'] + $rapport['audit'];
        $mode  = $dryRun ? '[dry-run] ' : '';

        $this->info(sprintf(
            '%sRétention 18-07 : %d fichier(s) au-delà de la rétention (exports > %d j : %d, audit > %d j : %d).',
            $mode,
            $total,
            $joursExports,
            $rapport['exports'],
            $joursAudit,
            $rapport['audit']
        ));

        if (!$dryRun && $total > 0) {
            Log::info('Rétention 18-07 : fichiers purgés', $rapport);
        }

        return self::SUCCESS;
    }

    /**
     * Supprimer les fichiers d'un répertoire du disque « local » plus vieux
     * que le seuil (mtime). Retourne le nombre de fichiers concernés.
     */
    private function purgerRepertoire(string $repertoire, int $jours, bool $dryRun): int
    {
        $disk = Storage::disk('local');

        if (!$disk->exists($repertoire)) {
            return 0;
        }

        $seuil     = now()->subDays($jours)->getTimestamp();
        $supprimes = 0;

        foreach ($disk->allFiles($repertoire) as $chemin) {
            // lastModified() du contrat Filesystem retourne int|bool (false
            // si le fichier disparaît entre le listage et la lecture).
            $mtime = $disk->lastModified($chemin);
            if ($mtime !== false && $mtime < $seuil) {
                if (!$dryRun) {
                    $disk->delete($chemin);
                }
                $supprimes++;
            }
        }

        return $supprimes;
    }
}
