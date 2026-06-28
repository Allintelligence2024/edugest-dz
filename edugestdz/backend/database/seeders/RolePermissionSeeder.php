<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'eleves'       => ['lister', 'créer', 'modifier', 'supprimer', 'exporter', 'importer'],
            'parents'      => ['lister', 'créer', 'modifier', 'supprimer'],
            'enseignants'  => ['lister', 'créer', 'modifier', 'supprimer', 'consulter_paie'],
            'contrats'     => ['lister', 'créer', 'modifier', 'supprimer'],
            'paies'        => ['lister', 'calculer', 'valider', 'payer'],
            'matieres'     => ['lister', 'créer', 'modifier', 'supprimer'],
            'salles'       => ['lister', 'créer', 'modifier', 'supprimer'],
            'groupes'      => ['lister', 'créer', 'modifier', 'supprimer', 'gerer_eleves'],
            'inscriptions' => ['lister', 'créer', 'modifier', 'supprimer'],
            'planning'     => ['consulter', 'créer_cours', 'modifier_cours', 'supprimer_cours', 'gerer_seances'],
            'presences'    => ['saisir', 'modifier', 'consulter_rapport'],
            'evaluations'  => ['lister', 'créer', 'modifier', 'supprimer', 'saisir_notes'],
            'bulletins'    => ['lister', 'generer', 'consulter_pdf', 'envoyer'],
            'factures'     => ['lister', 'créer', 'modifier', 'supprimer', 'generer_pdf', 'envoyer'],
            'paiements'    => ['lister', 'enregistrer', 'modifier', 'consulter_caisse'],
            'finance'      => ['consulter_tableau_bord', 'consulter_impayes', 'envoyer_relances', 'consulter_bilan'],
            'notifications'=> ['lister', 'envoyer', 'supprimer'],
            'messages'     => ['lire', 'envoyer'],
            'rapports'     => ['consulter_presence', 'consulter_financier', 'consulter_pedagogique', 'generer_attestation'],
            'parametres'   => ['lire', 'modifier'],
            'utilisateurs' => ['lister', 'créer', 'modifier', 'supprimer'],
        ];

        $permissionId = 0;
        $permissions = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissionId++;
                $permissions[] = [
                    'id'          => $permissionId,
                    'nom'         => "{$module}.{$action}",
                    'module'      => $module,
                    'action'      => $action,
                    'description' => null,
                ];
            }
        }

        DB::table('permissions')->insert($permissions);

        $roles = [
            [
                'id'       => 1,
                'tenant_id'=> null,
                'nom'      => 'super_admin',
                'label_fr' => 'Super Admin',
                'label_ar' => 'مدير عام',
                'is_system'=> true,
                'permissions' => range(1, $permissionId),
            ],
            [
                'id'       => 2,
                'tenant_id'=> null,
                'nom'      => 'admin',
                'label_fr' => 'Administrateur',
                'label_ar' => 'مدير',
                'is_system'=> true,
                'permissions' => range(1, $permissionId),
            ],
            [
                'id'       => 3,
                'tenant_id'=> null,
                'nom'      => 'gestionnaire',
                'label_fr' => 'Gestionnaire',
                'label_ar' => 'مسير',
                'is_system'=> true,
                'permissions' => [1,2,3,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,48,49,50,51,52,53,55,56,57,58,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86],
            ],
            [
                'id'       => 4,
                'tenant_id'=> null,
                'nom'      => 'enseignant',
                'label_fr' => 'Enseignant',
                'label_ar' => 'أستاذ',
                'is_system'=> true,
                'permissions' => [24, 25, 28, 29, 30, 31, 34, 35, 36, 38, 39, 40, 41, 42, 43, 44, 73, 74],
            ],
            [
                'id'       => 5,
                'tenant_id'=> null,
                'nom'      => 'parent',
                'label_fr' => 'Parent',
                'label_ar' => 'ولي أمر',
                'is_system'=> true,
                'permissions' => [24, 38, 39, 46, 49, 73, 74, 75, 81],
            ],
            [
                'id'       => 6,
                'tenant_id'=> null,
                'nom'      => 'comptable',
                'label_fr' => 'Comptable',
                'label_ar' => 'محاسب',
                'is_system'=> true,
                'permissions' => [55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78],
            ],
        ];

        foreach ($roles as $r) {
            $roleId = $r['id'];
            DB::table('roles')->insert([
                'id'        => $roleId,
                'tenant_id' => $r['tenant_id'],
                'nom'       => $r['nom'],
                'label_fr'  => $r['label_fr'],
                'label_ar'  => $r['label_ar'],
                'is_system' => $r['is_system'],
            ]);

            $pivot = array_map(fn($p) => ['role_id' => $roleId, 'permission_id' => $p], $r['permissions']);
            DB::table('role_permissions')->insert($pivot);
        }
    }
}
