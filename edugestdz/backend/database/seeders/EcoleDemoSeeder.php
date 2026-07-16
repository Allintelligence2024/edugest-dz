<?php

namespace Database\Seeders;

use App\Models\{
    Tenant, User, Role, Eleve, Enseignant, Groupe, Matiere, Cours,
    Seance, Salle, Facture, Paiement, Note, Evaluation, Paie,
    ParentEleve, DiagnosticEleve, Inscription, Presence
};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class EcoleDemoSeeder extends Seeder
{
    private Tenant $tenant;

    public function run(): void
    {
        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'ecole-demo'],
            [
                'nom_etablissement' => 'École Demo EduGest',
                'type_etablissement' => 'centre',
                'statut' => 'actif',
                'wilaya_id' => 16,
                'plan_abonnement' => 'pro',
            ]
        );

        config(['tenant.current_id' => $this->tenant->id]);

        $roleEns = Role::firstOrCreate(
            ['nom' => 'enseignant'],
            ['label_fr' => 'Enseignant', 'is_system' => true]
        );
        $roleAdmin = Role::firstOrCreate(
            ['nom' => 'admin'],
            ['label_fr' => 'Admin', 'is_system' => true]
        );

        if (User::where('email', 'admin@edugest-demo.dz')->doesntExist()) {
            User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'role_id' => $roleAdmin->id,
                'nom' => 'Admin',
                'prenom' => 'Demo',
                'email' => 'admin@edugest-demo.dz',
                'password' => Hash::make('password'),
            ]);
        }

        $this->matieres();
        $this->salles();
        $enseignants = $this->enseignants($roleEns);
        $eleves = $this->eleves();
        $parents = $this->parents();
        $this->lieElevesParents($eleves, $parents);
        $groupes = $this->groupes();
        $this->inscrireEleves($eleves, $groupes);
        $coursList = $this->cours($groupes, $enseignants);
        $this->seances($coursList);
        $this->notes($groupes);
        $this->presences();
        $this->facturesPaiements($eleves);
        $this->paies($enseignants);
        $this->diagnostics($eleves);

        $this->command?->info('EcoleDemoSeeder terminé avec succès');
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
        $items = [
            ['nom' => 'Salle Alpha', 'capacite' => 25],
            ['nom' => 'Salle Beta', 'capacite' => 20],
            ['nom' => 'Salle Gamma', 'capacite' => 30],
            ['nom' => 'Laboratoire', 'capacite' => 15],
            ['nom' => 'Amphithéâtre', 'capacite' => 50],
        ];

        foreach ($items as $s) {
            Salle::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'nom' => $s['nom']],
                ['tenant_id' => $this->tenant->id, 'capacite' => $s['capacite'], 'statut' => 'actif']
            );
        }
    }

    private function enseignants(Role $role): \Illuminate\Support\Collection
    {
        $items = [
            ['Khelil', 'Youcef'],
            ['Benali', 'Fatima'],
            ['Messaoudi', 'Sami'],
            ['Ouali', 'Nadia'],
            ['Toumi', 'Karim'],
            ['Zidane', 'Lina'],
            ['Boumediene', 'Sofiane'],
            ['Cherif', 'Amira'],
            ['Djebali', 'Hocine'],
        ];

        $result = collect();
        foreach ($items as [$nom, $prenom]) {
            $existing = Enseignant::where('tenant_id', $this->tenant->id)
                ->where('nom', $nom)
                ->where('prenom', $prenom)
                ->first();

            if ($existing) {
                $result->push($existing);
                continue;
            }

            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'role_id' => $role->id,
                'nom' => $nom,
                'prenom' => $prenom,
            ]);

            $ens = Enseignant::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $user->id,
                'nom' => $nom,
                'prenom' => $prenom,
            ]);

            $result->push($ens);
        }

        return $result;
    }

    private function eleves(): \Illuminate\Support\Collection
    {
        $niveaux = ['1AM', '2AM', '3AM', '4AM', '1AS', '2AS', '3AS'];
        $prenoms = [
            'Mohamed', 'Ahmed', 'Amina', 'Sara', 'Lina',
            'Youssef', 'Yacine', 'Ines', 'Malak', 'Adam',
            'Anis', 'Rania', 'Amine', 'Meriem', 'Walid',
            'Nour', 'Ilyes', 'Hiba', 'Rayan', 'Chaima',
        ];

        $result = collect();
        foreach ($prenoms as $i => $p) {
            $existing = Eleve::where('tenant_id', $this->tenant->id)
                ->where('prenom', $p)
                ->first();

            if ($existing) {
                $result->push($existing);
                continue;
            }

            $eleve = Eleve::factory()->create([
                'tenant_id' => $this->tenant->id,
                'numero_inscription' => sprintf('ECO-%d-%04d', now()->year, $i + 1),
                'prenom' => $p,
                'niveau_scolaire' => $niveaux[array_rand($niveaux)],
            ]);

            $result->push($eleve);
        }

        return $result;
    }

    private function parents(): \Illuminate\Support\Collection
    {
        $items = [
            ['nom' => 'Khelil', 'prenom' => 'Abdelkader', 'lien' => 'père', 'tel' => '0555102030'],
            ['nom' => 'Benali', 'prenom' => 'Hassan', 'lien' => 'père', 'tel' => '0555203040'],
            ['nom' => 'Messaoudi', 'prenom' => 'Fatima', 'lien' => 'mère', 'tel' => '0555304050'],
            ['nom' => 'Ouali', 'prenom' => 'Mohamed', 'lien' => 'père', 'tel' => '0555405060'],
            ['nom' => 'Toumi', 'prenom' => 'Aicha', 'lien' => 'mère', 'tel' => '0555506070'],
            ['nom' => 'Zidane', 'prenom' => 'Karim', 'lien' => 'père', 'tel' => '0555607080'],
            ['nom' => 'Boumediene', 'prenom' => 'Nadia', 'lien' => 'mère', 'tel' => '0555708090'],
            ['nom' => 'Cherif', 'prenom' => 'Sofiane', 'lien' => 'père', 'tel' => '0555809100'],
            ['nom' => 'Djebali', 'prenom' => 'Amira', 'lien' => 'mère', 'tel' => '0555901020'],
            ['nom' => 'Rahmani', 'prenom' => 'Omar', 'lien' => 'père', 'tel' => '0555001020'],
        ];

        $result = collect();
        foreach ($items as $p) {
            $existing = ParentEleve::where('tenant_id', $this->tenant->id)
                ->where('nom', $p['nom'])
                ->where('prenom', $p['prenom'])
                ->first();

            if ($existing) {
                $result->push($existing);
                continue;
            }

            $parent = ParentEleve::create([
                'tenant_id' => $this->tenant->id,
                'nom' => $p['nom'],
                'prenom' => $p['prenom'],
                'lien' => $p['lien'],
                'telephone_1' => $p['tel'],
                'est_urgence' => true,
            ]);

            $result->push($parent);
        }

        return $result;
    }

    private function lieElevesParents(\Illuminate\Support\Collection $eleves, \Illuminate\Support\Collection $parents): void
    {
        $pairs = [
            [0, 0], [1, 1], [2, 2], [3, 3], [4, 4],
            [5, 5], [6, 6], [7, 7], [8, 8], [9, 9],
            [0, 1], [1, 0], [3, 5], [5, 3],
        ];

        foreach ($pairs as [$ei, $pi]) {
            if (!isset($eleves[$ei]) || !isset($parents[$pi])) {
                continue;
            }

            $exists = DB::table('eleve_parent')
                ->where('eleve_id', $eleves[$ei]->id)
                ->where('parent_id', $parents[$pi]->id)
                ->exists();

            if (!$exists) {
                DB::table('eleve_parent')->insert([
                    'eleve_id' => $eleves[$ei]->id,
                    'parent_id' => $parents[$pi]->id,
                    'est_principal' => $pi === 0,
                ]);
            }
        }
    }

    private function groupes(): \Illuminate\Support\Collection
    {
        $matieres = Matiere::where('tenant_id', $this->tenant->id)->get();
        $niveaux = ['1AM', '2AM', '3AM', '4AM', '1AS', '2AS', '3AS'];

        $result = collect();
        foreach (range(1, 8) as $i) {
            $nom = 'Grp ' . $niveaux[array_rand($niveaux)] . ' #' . $i;
            $g = Groupe::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'nom' => $nom],
                [
                    'tenant_id' => $this->tenant->id,
                    'matiere_id' => $matieres->random()->id,
                    'niveau_scolaire' => $niveaux[array_rand($niveaux)],
                    'statut' => 'actif',
                ]
            );
            $result->push($g);
        }

        return $result;
    }

    private function inscrireEleves(\Illuminate\Support\Collection $eleves, \Illuminate\Support\Collection $groupes): void
    {
        foreach ($groupes as $g) {
            $elevesGroupe = $eleves->random(min(4, $eleves->count()));

            foreach ($elevesGroupe as $e) {
                $exists = Inscription::where('tenant_id', $this->tenant->id)
                    ->where('eleve_id', $e->id)
                    ->where('groupe_id', $g->id)
                    ->where('annee_scolaire', now()->year . '/' . (now()->year + 1))
                    ->exists();

                if (!$exists) {
                    Inscription::create([
                        'tenant_id' => $this->tenant->id,
                        'eleve_id' => $e->id,
                        'groupe_id' => $g->id,
                        'annee_scolaire' => now()->year . '/' . (now()->year + 1),
                        'date_inscription' => now()->subDays(rand(10, 90)),
                        'statut' => 'validée',
                    ]);
                }
            }
        }
    }

    private function cours(\Illuminate\Support\Collection $groupes, \Illuminate\Support\Collection $enseignants): \Illuminate\Support\Collection
    {
        $matieres = Matiere::where('tenant_id', $this->tenant->id)->get();
        $salles = Salle::where('tenant_id', $this->tenant->id)->get();

        $result = collect();
        foreach ($groupes as $g) {
            $exists = Cours::where('tenant_id', $this->tenant->id)
                ->where('groupe_id', $g->id)
                ->exists();

            if ($exists) {
                $result->push(Cours::where('tenant_id', $this->tenant->id)->where('groupe_id', $g->id)->first());
                continue;
            }

            $c = Cours::create([
                'tenant_id' => $this->tenant->id,
                'enseignant_id' => $enseignants->random()->id,
                'matiere_id' => $matieres->random()->id,
                'groupe_id' => $g->id,
                'salle_id' => $salles->random()->id,
                'jour_semaine' => rand(0, 5),
                'heure_debut' => sprintf('%02d:%02d', rand(8, 16), [0, 30][array_rand([0, 30])]),
                'heure_fin' => sprintf('%02d:%02d', rand(10, 18), [0, 30][array_rand([0, 30])]),
                'type_cours' => 'groupe',
                'recurrence' => 'hebdo',
                'date_debut' => now()->subMonths(3),
                'statut' => 'actif',
            ]);

            $result->push($c);
        }

        return $result;
    }

    private function seances(\Illuminate\Support\Collection $coursList): void
    {
        foreach ($coursList as $c) {
            $dates = [
                now()->toDateString(),
                now()->addDays(2)->toDateString(),
                now()->addDays(9)->toDateString(),
                now()->subDays(5)->toDateString(),
                now()->subDays(12)->toDateString(),
            ];

            foreach ($dates as $i => $date) {
                $statut = str_contains($date, '-') && now()->parse($date)->isPast()
                    ? 'terminée'
                    : 'planifiée';

                Seance::firstOrCreate(
                    ['cours_id' => $c->id, 'date_seance' => $date],
                    [
                        'tenant_id' => $this->tenant->id,
                        'cours_id' => $c->id,
                        'date_seance' => $date,
                        'heure_debut' => '09:00',
                        'heure_fin' => '11:00',
                        'statut' => $statut,
                    ]
                );
            }
        }
    }

    private function notes(\Illuminate\Support\Collection $groupes): void
    {
        $trimestres = ['T1', 'T2', 'T3'];
        $types = ['devoir_classe', 'devoir_maison', 'test_rapide'];

        foreach ($groupes as $g) {
            $eval = Evaluation::firstOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'groupe_id' => $g->id,
                    'titre' => 'Devoir ' . ($g->matiere?->nom_fr ?? 'Général'),
                    'type_eval' => $types[array_rand($types)],
                    'date_evaluation' => now()->subDays(10),
                ],
                [
                    'tenant_id' => $this->tenant->id,
                    'groupe_id' => $g->id,
                    'titre' => 'Devoir ' . ($g->matiere?->nom_fr ?? 'Général'),
                    'type_eval' => $types[array_rand($types)],
                    'date_evaluation' => now()->subDays(10),
                    'note_sur' => 20,
                    'coefficient' => rand(1, 3),
                    'trimestre' => $trimestres[array_rand($trimestres)],
                ]
            );

            $inscrits = Inscription::where('tenant_id', $this->tenant->id)
                ->where('groupe_id', $g->id)
                ->where('statut', 'validée')
                ->pluck('eleve_id');

            foreach ($inscrits as $eid) {
                Note::firstOrCreate(
                    ['evaluation_id' => $eval->id, 'eleve_id' => $eid],
                    [
                        'tenant_id' => $this->tenant->id,
                        'evaluation_id' => $eval->id,
                        'eleve_id' => $eid,
                        'note' => rand(6, 20) . '.' . rand(0, 9),
                        'appreciation' => 'Bien',
                    ]
                );
            }
        }
    }

    private function presences(): void
    {
        $pastSeances = Seance::where('tenant_id', $this->tenant->id)
            ->where('date_seance', '<', now()->toDateString())
            ->where('statut', 'terminée')
            ->get();

        $statuts = ['présent', 'présent', 'présent', 'absent', 'absent', 'retard'];

        foreach ($pastSeances as $seance) {
            $cours = $seance->cours;
            if (!$cours) continue;

            $inscrits = Inscription::where('tenant_id', $this->tenant->id)
                ->where('groupe_id', $cours->groupe_id)
                ->where('statut', 'validée')
                ->pluck('eleve_id');

            foreach ($inscrits as $eid) {
                $exists = Presence::where('tenant_id', $this->tenant->id)
                    ->where('seance_id', $seance->id)
                    ->where('eleve_id', $eid)
                    ->exists();

                if (!$exists) {
                    $statut = $statuts[array_rand($statuts)];
                    Presence::create([
                        'tenant_id'    => $this->tenant->id,
                        'seance_id'    => $seance->id,
                        'eleve_id'     => $eid,
                        'statut'       => $statut,
                        'heure_arrivee' => $statut === 'retard' ? sprintf('%02d:%02d', rand(8, 10), rand(5, 55)) : null,
                        'motif'        => $statut === 'absent' ? 'Absence non justifiée' : null,
                    ]);
                }
            }
        }
    }

    private function facturesPaiements(\Illuminate\Support\Collection $eleves): void
    {
        $statuts = ['brouillon', 'émise', 'payée'];
        $modes = ['espèces', 'cib', 'dahabia'];

        $paiementCount = 0;
        foreach ($eleves->take(10) as $e) {
            $num = 'FAC-' . now()->year . '-' . strtoupper(Str::random(6));

            $existing = Facture::where('tenant_id', $this->tenant->id)
                ->where('eleve_id', $e->id)
                ->where('mois', now()->month)
                ->where('annee', now()->year)
                ->first();

            if ($existing) {
                $f = $existing;
            } else {
                $total = rand(3000, 15000);
                // First 3 factures guaranteed 'payée' to ensure paiements exist
                $statut = $paiementCount < 3 ? 'payée' : $statuts[array_rand($statuts)];

                $f = Facture::create([
                    'tenant_id' => $this->tenant->id,
                    'eleve_id' => $e->id,
                    'numero_facture' => $num,
                    'mois' => now()->month,
                    'annee' => now()->year,
                    'date_emission' => now()->subDays(rand(1, 30)),
                    'date_echeance' => now()->addDays(30),
                    'sous_total' => $total,
                    'total_ttc' => $total,
                    'statut' => $statut,
                ]);
            }

            $paiementExiste = Paiement::where('tenant_id', $this->tenant->id)
                ->where('facture_id', $f->id)
                ->exists();

            if (!$paiementExiste && $f->statut === 'payée') {
                Paiement::create([
                    'tenant_id' => $this->tenant->id,
                    'facture_id' => $f->id,
                    'eleve_id' => $e->id,
                    'montant' => $f->total_ttc,
                    'mode_paiement' => $modes[array_rand($modes)],
                    'date_paiement' => now(),
                    'statut' => 'confirmé',
                ]);
                $paiementCount++;
            }
        }
    }

    private function paies(\Illuminate\Support\Collection $enseignants): void
    {
        foreach ($enseignants as $ens) {
            $existing = Paie::where('tenant_id', $this->tenant->id)
                ->where('enseignant_id', $ens->id)
                ->where('mois', now()->month)
                ->where('annee', now()->year)
                ->exists();

            if ($existing) {
                continue;
            }

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

    private function diagnostics(\Illuminate\Support\Collection $eleves): void
    {
        foreach ($eleves as $e) {
            $notes = $e->notes()->with('evaluation')->get();
            $moyenne = 0;
            $sous5 = 0;
            $sous10 = 0;

            if ($notes->isNotEmpty()) {
                $totalPondere = $notes->sum(fn($n) => ($n->note ?? 0) * ($n->evaluation?->coefficient ?? 1));
                $totalCoeff = $notes->sum(fn($n) => $n->evaluation?->coefficient ?? 1);
                $moyenne = $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : 0;
                $sous5 = $notes->where('note', '<', 5)->count();
                $sous10 = $notes->where('note', '<', 10)->count();
            }

            $scoreRisque = min(100, max(0, 100 - ($moyenne * 5)));
            $niveau = 'normal';
            if ($scoreRisque <= 10) $niveau = 'excellent';
            elseif ($scoreRisque <= 30) $niveau = 'normal';
            elseif ($scoreRisque <= 55) $niveau = 'vigilance';
            elseif ($scoreRisque <= 75) $niveau = 'danger';
            else $niveau = 'critique';

            DiagnosticEleve::updateOrCreate(
                ['eleve_id' => $e->id],
                [
                    'tenant_id' => $this->tenant->id,
                    'eleve_id' => $e->id,
                    'niveau_global' => $niveau,
                    'score_risque' => $scoreRisque,
                    'moyenne_generale' => $moyenne,
                    'nb_notes_sous_5' => $sous5,
                    'nb_notes_sous_10' => $sous10,
                    'rattrapage_requis' => $moyenne < 10,
                    'convocation_requise' => $sous5 > 2,
                    'derniere_analyse' => now(),
                ]
            );
        }
    }
}
