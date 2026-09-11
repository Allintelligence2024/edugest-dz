<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sprint 6 § 5 — Loi 18-07 : politique de rétention.
 *
 * Les exports contenant des données personnelles (portabilité, archives)
 * ne doivent pas s'accumuler indéfiniment : 30 jours pour les exports
 * RGPD, 1 an pour les exports d'audit.
 */
class RgpdRetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_purge_les_exports_rgpd_expires_et_garde_les_recents(): void
    {
        Storage::disk('local')->put('exports/tenant-1/portabilite.json', 'donnees');
        Storage::disk('local')->put('exports/tenant-1/recent.json', 'donnees');

        // Vieillir le premier fichier de 40 jours (rétention : 30).
        touch(Storage::disk('local')->path('exports/tenant-1/portabilite.json'), time() - 40 * 86400);

        $exit = Artisan::call('edugest:rgpd-retention');
        $this->assertSame(0, $exit);

        $this->assertFalse(Storage::disk('local')->exists('exports/tenant-1/portabilite.json'));
        $this->assertTrue(Storage::disk('local')->exists('exports/tenant-1/recent.json'));
    }

    public function test_dry_run_ne_supprime_rien(): void
    {
        Storage::disk('local')->put('exports/tenant-1/portabilite.json', 'donnees');
        touch(Storage::disk('local')->path('exports/tenant-1/portabilite.json'), time() - 40 * 86400);

        $exit = Artisan::call('edugest:rgpd-retention', ['--dry-run' => true]);
        $this->assertSame(0, $exit);

        $this->assertTrue(Storage::disk('local')->exists('exports/tenant-1/portabilite.json'));
    }

    public function test_retention_audit_plus_longue_que_exports(): void
    {
        Storage::disk('local')->put('audit/2026-08-01/audit.json', 'preuve');
        Storage::disk('local')->put('exports/tenant-1/portabilite.json', 'donnees');

        // 40 jours : au-delà de la rétention exports (30 j) mais en-deçà
        // de la rétention audit (365 j).
        $hier40Jours = time() - 40 * 86400;
        touch(Storage::disk('local')->path('audit/2026-08-01/audit.json'), $hier40Jours);
        touch(Storage::disk('local')->path('exports/tenant-1/portabilite.json'), $hier40Jours);

        Artisan::call('edugest:rgpd-retention');

        $this->assertFalse(Storage::disk('local')->exists('exports/tenant-1/portabilite.json'));
        $this->assertTrue(Storage::disk('local')->exists('audit/2026-08-01/audit.json'));
    }

    public function test_repertoire_absent_sans_erreur(): void
    {
        $this->assertSame(0, Artisan::call('edugest:rgpd-retention'));
    }
}
