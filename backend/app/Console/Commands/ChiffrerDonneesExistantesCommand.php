<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ChiffrerDonneesExistantesCommand extends Command
{
    protected $signature   = 'edugest:chiffrer-donnees';
    protected $description = 'Chiffrer les données sensibles existantes non encore chiffrées';

    public function handle(): int
    {
        $this->info('Chiffrement des donnees sensibles...');
        $total = 0;

        $connexions = DB::table('google_classroom_connexions')->lazyById();
        foreach ($connexions as $row) {
            $updated = [];

            foreach (['access_token', 'refresh_token'] as $col) {
                if (!$row->$col) continue;
                try {
                    Crypt::decryptString($row->$col);
                } catch (\Throwable) {
                    $updated[$col] = Crypt::encryptString($row->$col);
                    $total++;
                }
            }

            if (!empty($updated)) {
                DB::table('google_classroom_connexions')
                    ->where('id', $row->id)
                    ->update($updated);
            }
        }

        $this->info("{$total} valeur(s) chiffree(s).");
        return Command::SUCCESS;
    }
}
