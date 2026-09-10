<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Tenant, User};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Fail-closed : ce seeder crée un compte administrateur et ne doit
        // JAMAIS s'exécuter en production, même par erreur de commande.
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'TestUserSeeder est interdit en production. Utiliser InitialProductionSeeder.'
            );
        }

        // Mot de passe aléatoire par défaut : plus de 'password' en dur.
        // Surchargeable pour les tests automatisés via TEST_USER_PASSWORD.
        $motDePasse = env('TEST_USER_PASSWORD') ?: Str::random(20);

        $tenant = Tenant::create([
            'nom_etablissement' => 'Centre Alpha',
            'slug'            => 'centre-alpha',
            'email'           => 'contact@centrealpha.dz',
            'telephone'       => '0550123456',
            'statut'          => 'actif',
            'date_expiration' => now()->addYear(),
            'wilaya_id'       => 16,
            'commune_id'      => 1,
            'adresse'         => '16000 Alger',
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'nom'       => 'Admin',
            'prenom'    => 'Centre',
            'email'     => 'admin@edugest.dz',
            'password'  => Hash::make($motDePasse),
            'telephone' => '0550123456',
            'langue'    => 'fr',
            'role_id'   => 2,
            'statut'    => 'actif',
        ]);
        // Le mot de passe généré n'est affiché qu'ici : il n'est stocké
        // nulle part en clair.
        if (!app()->runningUnitTests()) {
            $this->command?->warn('┌───────────────────────────────────────────────');
            $this->command?->warn('│ Compte de test créé');
            $this->command?->warn('│   email    : admin@edugest.dz');
            $this->command?->warn("│   mot de passe : {$motDePasse}");
            $this->command?->warn('│ Notez-le : il ne sera plus affiché.');
            $this->command?->warn('└───────────────────────────────────────────────');
        }
    }
}
