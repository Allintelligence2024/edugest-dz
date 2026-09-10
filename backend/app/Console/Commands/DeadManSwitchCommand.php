<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeadManSwitchCommand extends Command
{
    protected $signature = 'edugest:deadman-switch';
    protected $description = 'Verifie les utilisateurs inactifs (Dead Man Switch)';

    private const SEUIL_INACTIVITE_JOURS = 90;
    private const SEUIL_NOTIFICATION_JOURS = 80;

    public function handle(): int
    {
        try {
            $hasColumn = DB::connection()
                ->getSchemaBuilder()
                ->hasColumn('users', 'last_login_at');

            if (!$hasColumn) {
                $this->info('DeadManSwitch: colonne last_login_at non trouvee, ignore.');
                return Command::SUCCESS;
            }

            $inactifs = User::where('last_login_at', '<', now()->subDays(self::SEUIL_INACTIVITE_JOURS))
                ->orWhereNull('last_login_at')
                ->get();

            $count = 0;

            foreach ($inactifs as $user) {
                $joursInactif = $user->last_login_at
                    ? now()->diffInDays($user->last_login_at)
                    : PHP_INT_MAX;

                Log::info('DeadManSwitch: utilisateur inactif detecte', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'jours_inactif' => $joursInactif,
                    'seuil' => self::SEUIL_INACTIVITE_JOURS,
                ]);

                if ($joursInactif >= self::SEUIL_NOTIFICATION_JOURS && $joursInactif < self::SEUIL_INACTIVITE_JOURS) {
                    $this->notifier($user, $joursInactif);
                }

                $count++;
            }

            $this->info("DeadManSwitch: {$count} utilisateur(s) inactif(s) detecte(s).");

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('DeadManSwitch: erreur', [
                'error' => $e->getMessage(),
            ]);

            $this->warn('DeadManSwitch: erreur ignoree: ' . $e->getMessage());

            return Command::SUCCESS;
        }
    }

    private function notifier(User $user, int $joursInactif): void
    {
        Log::warning('DeadManSwitch: notification inactivite', [
            'user_id' => $user->id,
            'email' => $user->email,
            'jours_inactif' => $joursInactif,
        ]);
    }
}
