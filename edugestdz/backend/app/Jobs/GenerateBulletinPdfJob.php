<?php

namespace App\Jobs;

use App\Models\Bulletin;
use App\Services\BulletinService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBulletinPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public Bulletin $bulletin,
    ) {
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        $this->bulletin->update(['statut_pdf' => 'en_cours']);

        $bulletinLoaded = $this->bulletin->fresh()->load('eleve', 'groupe.matiere');

        $service = app(BulletinService::class);
        $path = $service->genererPDF($bulletinLoaded);

        if ($path !== '') {
            $this->bulletin->update([
                'fichier_url' => $path,
                'statut_pdf'  => 'genere',
            ]);
        } else {
            $this->bulletin->update(['statut_pdf' => 'erreur']);
            Log::error('[BulletinPdf] Échec génération PDF', [
                'bulletin_id' => $this->bulletin->id,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->bulletin->update(['statut_pdf' => 'erreur']);
        Log::error('[BulletinPdf] Exception fatale', [
            'bulletin_id' => $this->bulletin->id,
            'erreur'      => $e->getMessage(),
        ]);
    }
}
