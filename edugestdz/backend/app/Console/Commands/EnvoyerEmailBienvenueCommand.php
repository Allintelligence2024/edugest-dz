<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnvoyerEmailBienvenueCommand extends Command
{
    protected $signature   = 'edugest:email-bienvenue {userId} {--password=}';
    protected $description = 'Envoyer un email de bienvenue à un nouvel utilisateur';

    public function handle(): int
    {
        $user = User::find($this->argument('userId'));

        if (!$user || !$user->email) {
            $this->error('Utilisateur non trouvé ou email absent');
            return Command::FAILURE;
        }

        $motDePasse = $this->option('password') ?? 'VoirAdministrateur';

        try {
            Mail::send('emails.bienvenue', [
                'nom'                  => $user->nom,
                'prenom'               => $user->prenom,
                'email'                => $user->email,
                'motDePasseTemporaire' => $motDePasse,
                'nomEcole'             => config('app.name', 'EduGest DZ'),
                'urlApplication'       => config('app.url', 'http://localhost:5173'),
            ], function ($m) use ($user) {
                $m->to($user->email)
                  ->subject('Bienvenue sur EduGest DZ — Vos accès')
                  ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $this->info("✅ Email de bienvenue envoyé à {$user->email}");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Échec: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
