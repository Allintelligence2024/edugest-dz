<?php
namespace Database\Seeders;

use App\Models\{Tenant, User, Role, Eleve, Enseignant, Groupe, Matiere, Cours, Seance, Salle, Facture, Paiement, Note, Evaluation, Paie};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private Tenant $tenant;

    public function run(): void
    {
        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'centre-alpha'],
            ['nom_etablissement' => 'Centre Alpha', 'statut' => 'actif', 'wilaya_id' => 16, 'plan_abonnement' => 'pro']
        );

        config(['tenant.current_id' => $this->tenant->id]);

        $roleEns = Role::firstOrCreate(['nom' => 'enseignant'], ['label_fr' => 'Enseignant', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['nom' => 'admin'], ['label_fr' => 'Admin', 'is_system' => true]);

        if (User::where('email', 'admin@edugest.dz')->doesntExist()) {
            User::factory()->create([
                'tenant_id' => $this->tenant->id, 'role_id' => $roleAdmin->id,
                'nom' => 'Admin', 'prenom' => 'Centre', 'email' => 'admin@edugest.dz', 'password' => Hash::make('password'),
            ]);
        }

        $this->matieres();
        $this->salles();
        $this->enseignants($roleEns);
        $this->eleves();
        $this->groupes();
        $this->cours();
        $this->seances();
        $this->notes();
        $this->facturesPaiements();
        $this->paies();

        $this->command?->info('Données de démo générées');
    }

    private function matieres(): void
    {
        $items = [
            ['nom_fr' => 'Mathématiques', 'nom_ar' => 'الرياضيات', 'couleur' => '#1E5EBC'],
            ['nom_fr' => 'Physique', 'nom_ar' => 'الفيزياء', 'couleur' => '#27AE60'],
            ['nom_fr' => 'Français', 'nom_ar' => 'الفرنسية', 'couleur' => '#E74C3C'],
            ['nom_fr' => 'Anglais', 'nom_ar' => 'الإنجليزية', 'couleur' => '#F39C12'],
            ['nom_fr' => 'Arabe', 'nom_ar' => 'العربية', 'couleur' => '#8E44AD'],
            ['nom_fr' => 'SVT', 'nom_ar' => 'علوم الطبيعة', 'couleur' => '#2ECC71'],
            ['nom_fr' => 'Histoire-Géo', 'nom_ar' => 'تاريخ-جغرافيا', 'couleur' => '#E67E22'],
            ['nom_fr' => 'Philosophie', 'nom_ar' => 'الفلسفة', 'couleur' => '#34495E'],
            ['nom_fr' => 'Informatique', 'nom_ar' => 'الإعلام الآلي', 'couleur' => '#3498DB'],
        ];
        foreach ($items as $m) {
            Matiere::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'nom_fr' => $m['nom_fr']],
                array_merge($m, ['tenant_id' => $this->tenant->id, 'statut' => 'actif'])
            );
        }
    }

    private function salles(): void
    {
        $items = [['Salle A', 25], ['Salle B', 20], ['Salle C', 30], ['Labo', 15], ['Amphi', 50]];
        foreach ($items as [$nom, $cap]) {
            Salle::create(['tenant_id' => $this->tenant->id, 'nom' => $nom, 'capacite' => $cap, 'statut' => 'actif']);
        }
    }

    private function enseignants($role): void
    {
        $items = [
            ['Khelil', 'Youcef'], ['Benali', 'Fatima'], ['Messaoudi', 'Sami'],
            ['Ouali', 'Nadia'], ['Toumi', 'Karim'], ['Zidane', 'Lina'],
            ['Boumediene', 'Sofiane'], ['Cherif', 'Amira'], ['Djebali', 'Hocine'],
        ];
        foreach ($items as [$nom, $prenom]) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id, 'role_id' => $role->id,
                'nom' => $nom, 'prenom' => $prenom,
            ]);
            Enseignant::factory()->create([
                'tenant_id' => $this->tenant->id, 'user_id' => $user->id,
                'nom' => $nom, 'prenom' => $prenom,
            ]);
        }
    }

    private function eleves(): void
    {
        $niveaux = ['1AM', '2AM', '3AM', '4AM', '1AS', '2AS', '3AS'];
        $prenoms = ['Mohamed', 'Ahmed', 'Amina', 'Sara', 'Lina', 'Youssef', 'Yacine', 'Ines', 'Malak', 'Adam', 'Anis', 'Rania', 'Amine', 'Meriem', 'Walid', 'Nour', 'Ilyes', 'Hiba', 'Rayan', 'Chaima'];
        foreach ($prenoms as $p) {
            Eleve::factory()->create([
                'tenant_id' => $this->tenant->id, 'prenom' => $p,
                'niveau_scolaire' => $niveaux[array_rand($niveaux)],
            ]);
        }
    }

    private function groupes(): void
    {
        $matieres = Matiere::where('tenant_id', $this->tenant->id)->get();
        $enseignants = Enseignant::where('tenant_id', $this->tenant->id)->get();
        $niveaux = ['1AM', '2AM', '3AM', '4AM', '1AS', '2AS', '3AS'];

        foreach (range(1, 8) as $i) {
            $g = Groupe::factory()->create([
                'tenant_id' => $this->tenant->id,
                'matiere_id' => $matieres->random()->id,
                'nom' => 'Grp ' . $niveaux[array_rand($niveaux)] . ' #' . $i,
            ]);
            $eleves = Eleve::where('tenant_id', $this->tenant->id)->inRandomOrder()->take(rand(3, 8))->get();
            foreach ($eleves as $e) {
                DB::table('inscriptions')->insert([
                    'id' => Str::uuid(),
                    'tenant_id' => $this->tenant->id,
                    'eleve_id' => $e->id,
                    'groupe_id' => $g->id,
                    'annee_scolaire' => now()->year . '/' . (now()->year + 1),
                    'date_inscription' => now()->subDays(rand(10, 180)),
                    'statut' => 'validée',
                ]);
            }
        }
    }

    private function cours(): void
    {
        $groupes = Groupe::where('tenant_id', $this->tenant->id)->get();
        $enseignants = Enseignant::where('tenant_id', $this->tenant->id)->get();
        $matieres = Matiere::where('tenant_id', $this->tenant->id)->get();
        $salles = Salle::where('tenant_id', $this->tenant->id)->get();

        foreach ($groupes as $g) {
            Cours::factory()->create([
                'tenant_id' => $this->tenant->id,
                'enseignant_id' => $enseignants->random()->id,
                'matiere_id' => $matieres->random()->id,
                'groupe_id' => $g->id,
                'salle_id' => $salles->random()->id,
                'type_cours' => 'groupe',
                'recurrence' => 'hebdo',
            ]);
        }
    }

    private function seances(): void
    {
        $cours = Cours::where('tenant_id', $this->tenant->id)->get();
        foreach ($cours as $c) {
            Seance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'cours_id' => $c->id,
                'statut' => 'planifiée',
                'date_seance' => now()->addDays(rand(1, 14)),
            ]);
            Seance::factory()->create([
                'tenant_id' => $this->tenant->id,
                'cours_id' => $c->id,
                'statut' => 'terminée',
                'date_seance' => now()->subDays(rand(1, 30)),
            ]);
        }
    }

    private function notes(): void
    {
        $groupes = Groupe::with('matiere')->where('tenant_id', $this->tenant->id)->get();
        $trimestres = ['T1', 'T2', 'T3'];
        $types = ['devoir_classe', 'devoir_maison', 'test_rapide'];

        foreach ($groupes as $g) {
            $eval = Evaluation::create([
                'tenant_id' => $this->tenant->id,
                'groupe_id' => $g->id,
                'titre' => 'Devoir ' . $g->matiere->nom_fr,
                'type_eval' => $types[array_rand($types)],
                'date_evaluation' => now()->subDays(rand(5, 60)),
                'note_sur' => 20,
                'coefficient' => rand(1, 3),
                'trimestre' => $trimestres[array_rand($trimestres)],
            ]);

            $inscrits = DB::table('inscriptions')
                ->where('groupe_id', $g->id)
                ->where('statut', 'validée')
                ->pluck('eleve_id');

            foreach ($inscrits as $eid) {
                Note::create([
                    'tenant_id' => $this->tenant->id,
                    'evaluation_id' => $eval->id,
                    'eleve_id' => $eid,
                    'note' => rand(2, 20) . '.' . rand(0, 99),
                    'appreciation' => 'Bien',
                ]);
            }
        }
    }

    private function facturesPaiements(): void
    {
        $eleves = Eleve::where('tenant_id', $this->tenant->id)->get();
        $statuts = ['brouillon', 'émise', 'payée'];
        $modes = ['espèces', 'cib', 'dahabia'];

        foreach ($eleves->take(10) as $e) {
            $total = rand(3000, 15000);
            $statut = $statuts[array_rand($statuts)];

            $f = Facture::create([
                'tenant_id' => $this->tenant->id,
                'eleve_id' => $e->id,
                'numero_facture' => 'FAC-' . now()->year . '-' . strtoupper(Str::random(6)),
                'mois' => now()->month,
                'annee' => now()->year,
                'date_emission' => now()->subDays(rand(1, 60)),
                'date_echeance' => now()->addDays(30),
                'sous_total' => $total,
                'total_ttc' => $total,
                'statut' => $statut,
            ]);

            if ($statut === 'payée') {
                Paiement::create([
                    'tenant_id' => $this->tenant->id,
                    'facture_id' => $f->id,
                    'eleve_id' => $e->id,
                    'montant' => $total,
                    'mode_paiement' => $modes[array_rand($modes)],
                    'date_paiement' => now(),
                    'statut' => 'confirmé',
                ]);
            }
        }
    }

    private function paies(): void
    {
        $enseignants = Enseignant::where('tenant_id', $this->tenant->id)->get();
        foreach ($enseignants as $ens) {
            $base = $ens->salaire_base ?? rand(35000, 80000);
            Paie::create([
                'tenant_id' => $this->tenant->id,
                'enseignant_id' => $ens->id,
                'mois' => now()->month,
                'annee' => now()->year,
                'salaire_base' => $base,
                'salaire_net' => round($base * 0.88, 2),
                'primes' => rand(0, 5000),
                'retenues_absences' => rand(0, 1000),
                'irg' => round($base * 0.03, 2),
                'cnas' => round($base * 0.09, 2),
                'heures_travaillees' => rand(80, 160),
                'taux_horaire' => $ens->taux_horaire ?? rand(800, 2000),
                'statut' => 'calculé',
            ]);
        }
    }
}
