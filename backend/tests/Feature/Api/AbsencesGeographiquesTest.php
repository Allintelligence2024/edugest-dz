<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Eleve;
use App\Models\Wilaya;
use App\Models\AbsenceJournaliere;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AbsencesGeographiquesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);

        $this->admin = User::factory()->create([
            'role_id'   => $role->id,
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);
    }

    private function getOrCreateWilaya(array $attrs = []): Wilaya
    {
        $id = $attrs['id'] ?? null;
        if ($id && Wilaya::find($id)) {
            return Wilaya::find($id);
        }
        return Wilaya::updateOrCreate(
            ['id' => $id ?? rand(100, 999)],
            array_merge([
                'code'   => str_pad(rand(1, 58), 2, '0', STR_PAD_LEFT),
                'nom_fr' => 'Test Wilaya',
                'nom_ar' => 'ولاية اختبار',
            ], $attrs)
        );
    }

    private function makeEleveWithWilaya(string $wilayaId): Eleve
    {
        return Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'wilaya_id' => $wilayaId,
        ]);
    }

    public function test_par_wilaya_retourne_donnees(): void
    {
        $wilaya = $this->getOrCreateWilaya(['id' => 99, 'nom_fr' => 'Alger Test']);
        $eleve = $this->makeEleveWithWilaya($wilaya->id);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => '2026-07-10',
            'statut'       => 'absent',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/absences/geographie/par-wilaya')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_par_wilaya_avec_absences(): void
    {
        $wilaya = $this->getOrCreateWilaya(['id' => 98, 'nom_fr' => 'Oran Test']);
        $eleve1 = $this->makeEleveWithWilaya($wilaya->id);
        $eleve2 = $this->makeEleveWithWilaya($wilaya->id);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve1->id,
            'date_absence' => '2026-07-10',
            'statut'       => 'absent',
        ]);
        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve2->id,
            'date_absence' => '2026-07-11',
            'statut'       => 'absent',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/absences/geographie/par-wilaya');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    public function test_taux_absentisme(): void
    {
        $wilaya = $this->getOrCreateWilaya(['id' => 97, 'nom_fr' => 'Constantine Test']);
        $eleve = $this->makeEleveWithWilaya($wilaya->id);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => '2026-07-10',
            'statut'       => 'absent',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/absences/geographie/taux-absentisme')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_resume(): void
    {
        $wilaya = $this->getOrCreateWilaya(['id' => 96, 'nom_fr' => 'Annaba Test']);
        $eleve = $this->makeEleveWithWilaya($wilaya->id);

        AbsenceJournaliere::create([
            'tenant_id'    => $this->tenant->id,
            'eleve_id'     => $eleve->id,
            'date_absence' => '2026-07-10',
            'statut'       => 'absent',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/absences/geographie/resume')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_absences',
                    'eleves_avec_wilaya',
                    'wilayas_concernees',
                    'top_5_wilayas',
                ],
            ]);
    }

    public function test_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/absences/geographie/par-wilaya')
            ->assertStatus(401);
    }
}
