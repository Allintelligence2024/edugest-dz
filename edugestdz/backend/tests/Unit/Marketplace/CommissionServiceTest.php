<?php

namespace Tests\Unit\Marketplace;

use App\Models\{Commission, Tenant, Role, User, Enseignant, Matiere, OffrePublique, Reservation};
use App\Services\Marketplace\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionService();
    }

    private function createTenantWithRole(string $plan = 'pro'): array
    {
        $tenant = Tenant::factory()->create([
            'plan_abonnement' => $plan,
            'statut'          => 'actif',
        ]);

        config(['tenant.current_id' => $tenant->id]);

        $role = Role::factory()->create(['nom' => 'enseignant']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $role->id,
        ]);

        $enseignant = Enseignant::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id'   => $user->id,
        ]);

        $matiere = Matiere::factory()->create();

        $offre = OffrePublique::factory()->create([
            'tenant_id'     => $tenant->id,
            'enseignant_id' => $enseignant->id,
            'matiere_id'    => $matiere->id,
            'tarif_seance'  => 2000.0,
            'statut'        => 'active',
        ]);

        $eleveUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $eleve = \App\Models\Eleve::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id'   => $eleveUser->id,
        ]);

        $reservation = Reservation::factory()->create([
            'tenant_id'  => $tenant->id,
            'offre_id'   => $offre->id,
            'eleve_id'   => $eleve->id,
            'montant'    => 2000.0,
            'statut'     => 'en_attente',
        ]);

        return compact('tenant', 'enseignant', 'matiere', 'offre', 'reservation');
    }

    private function createCommission(array $data): void
    {
        DB::table('marketplace_commissions')->insert([
            'id'                  => Str::uuid()->toString(),
            'tenant_id'           => $data['tenant_id'],
            'enseignant_id'       => $data['enseignant_id'] ?? null,
            'reservation_id'      => $data['reservation_id'],
            'montant_total'       => $data['montant_total'],
            'taux_commission'     => $data['taux_commission'],
            'montant_commission'  => $data['montant_commission'],
            'montant_enseignant'  => $data['montant_enseignant'],
            'statut'              => $data['statut'] ?? 'en_attente',
            'plan_tenant'         => $data['plan_tenant'] ?? 'pro',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function test_calculateCommission_plan_gratuit(): void
    {
        $tenant = Tenant::factory()->create(['plan_abonnement' => 'gratuit']);
        $result = $this->service->calculateCommission(1000.0, $tenant);
        $this->assertEquals(100.0, $result);
    }

    public function test_calculateCommission_plan_pro(): void
    {
        $tenant = Tenant::factory()->create(['plan_abonnement' => 'pro']);
        $result = $this->service->calculateCommission(1000.0, $tenant);
        $this->assertEquals(70.0, $result);
    }

    public function test_calculateCommission_plan_premium(): void
    {
        $tenant = Tenant::factory()->create(['plan_abonnement' => 'premium']);
        $result = $this->service->calculateCommission(1000.0, $tenant);
        $this->assertEquals(50.0, $result);
    }

    public function test_calculateCommission_plan_inconnu(): void
    {
        $tenant = Tenant::factory()->create(['plan_abonnement' => 'unknown']);
        $result = $this->service->calculateCommission(1000.0, $tenant);
        $this->assertEquals(70.0, $result);
    }

    public function test_calculateNetEnseignant(): void
    {
        $result = $this->service->calculateNetEnseignant(1000.0, 70.0);
        $this->assertEquals(930.0, $result);
    }

    public function test_persistCommission_creates_record(): void
    {
        ['reservation' => $reservation] = $this->createTenantWithRole('pro');

        $commission = $this->service->persistCommission($reservation);

        $this->assertDatabaseHas('marketplace_commissions', [
            'reservation_id' => $reservation->id,
            'statut'         => 'en_attente',
        ]);

        $this->assertEquals(2000.0, (float) $commission->montant_total);
        $this->assertEquals(140.0, (float) $commission->montant_commission);
        $this->assertEquals(1860.0, (float) $commission->montant_enseignant);
    }

    public function test_persistCommission_default_rate_when_no_tenant(): void
    {
        $data = $this->createTenantWithRole('premium');

        $this->createCommission([
            'tenant_id'          => null,
            'enseignant_id'      => $data['enseignant']->id,
            'reservation_id'     => $data['reservation']->id,
            'montant_total'      => 2000.0,
            'taux_commission'    => 0.07,
            'montant_commission' => 140.0,
            'montant_enseignant' => 1860.0,
            'statut'             => 'en_attente',
        ]);

        $this->assertDatabaseHas('marketplace_commissions', [
            'reservation_id'      => $data['reservation']->id,
            'montant_commission'  => 140.0,
            'taux_commission'     => 0.07,
        ]);
    }

    public function test_listCommissions_returns_paginated(): void
    {
        $data = $this->createTenantWithRole();

        for ($i = 0; $i < 5; $i++) {
            $res = Reservation::factory()->create([
                'tenant_id' => $data['tenant']->id,
                'offre_id'  => $data['offre']->id,
                'eleve_id'  => $data['reservation']->eleve_id,
                'montant'   => 1000.0,
            ]);

            $this->createCommission([
                'tenant_id'          => $data['tenant']->id,
                'enseignant_id'      => $data['enseignant']->id,
                'reservation_id'     => $res->id,
                'montant_total'      => 1000.0,
                'taux_commission'    => 0.07,
                'montant_commission' => 70.0,
                'montant_enseignant' => 930.0,
                'statut'             => 'en_attente',
            ]);
        }

        config(['tenant.current_id' => $data['tenant']->id]);
        $result = $this->service->listCommissions();
        $this->assertEquals(5, $result->total());
    }

    public function test_listCommissions_filtre_par_statut(): void
    {
        $data = $this->createTenantWithRole();

        $this->createCommission([
            'tenant_id'          => $data['tenant']->id,
            'enseignant_id'      => $data['enseignant']->id,
            'reservation_id'     => $data['reservation']->id,
            'montant_total'      => 2000.0,
            'taux_commission'    => 0.07,
            'montant_commission' => 140.0,
            'montant_enseignant' => 1860.0,
            'statut'             => 'payee',
        ]);

        config(['tenant.current_id' => $data['tenant']->id]);
        $result = $this->service->listCommissions(['statut' => 'en_attente']);
        $this->assertEquals(0, $result->total());

        $result = $this->service->listCommissions(['statut' => 'payee']);
        $this->assertEquals(1, $result->total());
    }

    public function test_calculatePayout(): void
    {
        $data = $this->createTenantWithRole();

        for ($i = 0; $i < 3; $i++) {
            $res = Reservation::factory()->create([
                'tenant_id' => $data['tenant']->id,
                'offre_id'  => $data['offre']->id,
                'eleve_id'  => $data['reservation']->eleve_id,
                'montant'   => 1000.0,
            ]);

            $this->createCommission([
                'tenant_id'          => $data['tenant']->id,
                'enseignant_id'      => $data['enseignant']->id,
                'reservation_id'     => $res->id,
                'montant_total'      => 1000.0,
                'taux_commission'    => 0.07,
                'montant_commission' => 70.0,
                'montant_enseignant' => 930.0,
                'statut'             => 'en_attente',
            ]);
        }

        $result = $this->service->calculatePayout($data['enseignant']->id);

        $this->assertEquals(3, $result['nb_commissions']);
        $this->assertEquals(210.0, $result['total_commission']);
        $this->assertEquals(2790.0, $result['total_a_payer']);
    }

    public function test_getStats(): void
    {
        $data = $this->createTenantWithRole();

        for ($i = 0; $i < 3; $i++) {
            $res = Reservation::factory()->create([
                'tenant_id' => $data['tenant']->id,
                'offre_id'  => $data['offre']->id,
                'eleve_id'  => $data['reservation']->eleve_id,
                'montant'   => 1000.0,
            ]);

            $this->createCommission([
                'tenant_id'          => $data['tenant']->id,
                'enseignant_id'      => $data['enseignant']->id,
                'reservation_id'     => $res->id,
                'montant_total'      => 1000.0,
                'taux_commission'    => 0.07,
                'montant_commission' => 70.0,
                'montant_enseignant' => 930.0,
                'statut'             => $i < 2 ? 'en_attente' : 'payee',
            ]);
        }

        config(['tenant.current_id' => $data['tenant']->id]);
        $stats = $this->service->getStats();

        $this->assertEquals(3, $stats['total_commissions']);
        $this->assertEquals(2, $stats['en_attente']);
        $this->assertEquals(1, $stats['payees']);
        $this->assertEquals(210.0, $stats['montant_commissions']);
    }
}
