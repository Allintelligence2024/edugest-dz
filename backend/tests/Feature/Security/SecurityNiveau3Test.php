<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\PasswordPolicyService;
use App\Services\ImmutableAuditService;
use App\Services\JwtSecretRotationService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityNiveau3Test extends TestCase
{
    use RefreshDatabase;

    public function test_mot_de_passe_court_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('Pass1!');
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('12 caractères', $violations[0]);
    }

    public function test_mot_de_passe_sans_majuscule_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('password123!');
        $this->assertNotEmpty($violations);
    }

    public function test_mot_de_passe_courant_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('Password1');
        $this->assertNotEmpty($violations);
    }

    public function test_mot_de_passe_fort_accepte(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('EduGest@2026!Secure#42');
        $this->assertEmpty($violations);
    }

    public function test_mot_de_passe_identique_email_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('admin@edugest.dz', 'admin@edugest.dz');
        $this->assertNotEmpty($violations);
    }

    public function test_calcul_force_mot_de_passe(): void
    {
        $policy  = app(PasswordPolicyService::class);
        $faible  = $policy->calculerForce('abc');
        $fort    = $policy->calculerForce('EduGest@2026!Secure');

        $this->assertLessThan($fort['score'], $faible['score']);
        $this->assertEquals('Très faible', $faible['niveau']);
    }

    public function test_rotation_jwt_genere_nouveau_secret(): void
    {
        $service = app(JwtSecretRotationService::class);
        $result  = $service->effectuerRotation();

        $this->assertArrayHasKey('nouveau_secret', $result);
        $this->assertEquals(64, strlen($result['nouveau_secret']));
        $this->assertNotEquals(config('jwt.secret'), $result['nouveau_secret']);
    }

    public function test_historique_rotation_enregistre(): void
    {
        $service = app(JwtSecretRotationService::class);
        $service->effectuerRotation();

        $historique = $service->getHistoriqueRotations();
        $this->assertNotEmpty($historique);
        $this->assertArrayHasKey('date', $historique[0]);
    }

    public function test_export_audit_signe_avec_hash(): void
    {
        $service = app(ImmutableAuditService::class);
        $result  = $service->exporterJournalier(null, now()->format('Y-m-d'));

        $this->assertArrayHasKey('exportes', $result);
        $this->assertArrayHasKey('hash', $result);
    }
}
