<?php

namespace Tests\Unit\Services;

use App\Services\PasswordPolicyService;
use Tests\TestCase;

class PasswordPolicyCasLimitesTest extends TestCase
{
    private PasswordPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PasswordPolicyService::class);
    }

    public function test_exactement_12_caracteres_accepte(): void
    {
        $violations = $this->service->valider('Abcdef1@#xyz');
        $this->assertEmpty($violations);
    }

    public function test_11_caracteres_refuse(): void
    {
        $violations = $this->service->valider('Abcdef1@#xy');
        $this->assertNotEmpty($violations);
    }

    public function test_4_repetitions_refuse(): void
    {
        $violations = $this->service->valider('Abc1@aaaa_longs');
        $this->assertNotEmpty($violations);
    }

    public function test_3_repetitions_accepte(): void
    {
        $violations = $this->service->valider('Abc1@aaab_longsuf');
        $hasRepeat = false;
        foreach ($violations as $v) {
            if (str_contains($v, '4 caractères consécutifs')) {
                $hasRepeat = true;
            }
        }
        $this->assertFalse($hasRepeat);
    }

    public function test_mot_de_passe_tres_long_accepte(): void
    {
        $violations = $this->service->valider('EduGest@Oran#2026!VerySecure_Passphrase42');
        $this->assertEmpty($violations);
    }

    public function test_force_faible_retourne_niveau_correct(): void
    {
        $result = $this->service->calculerForce('abc');
        $this->assertContains($result['niveau'], ['Très faible', 'Faible']);
    }

    public function test_force_fort_retourne_niveau_correct(): void
    {
        $result = $this->service->calculerForce('EduGest@2026!SecurePass#42');
        $this->assertContains($result['niveau'], ['Fort', 'Très fort']);
    }

    public function test_force_score_entre_0_et_100(): void
    {
        foreach (['abc', 'Abcdef1@', 'EduGest@2026!SecureLong'] as $pwd) {
            $result = $this->service->calculerForce($pwd);
            $this->assertGreaterThanOrEqual(0, $result['score']);
            $this->assertLessThanOrEqual(100, $result['score']);
        }
    }

    public function test_mots_de_passe_faibles_refuses(): void
    {
        $interdits = ['Algeria2026', 'admin123', 'azerty123'];
        foreach ($interdits as $mdp) {
            $violations = $this->service->valider($mdp);
            $this->assertNotEmpty($violations, "{$mdp} devrait être refusé");
        }
    }
}
