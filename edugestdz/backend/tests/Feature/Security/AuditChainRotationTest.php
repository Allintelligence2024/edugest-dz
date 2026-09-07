<?php

namespace Tests\Feature\Security;

use App\Models\AuditChain;
use App\Services\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sprint 3 — Chaîne d'audit : clé dédiée, rotation, vérification de signature.
 *
 * Deux défauts corrigés ici :
 *   1. la signature utilisait config('app.key') : toute rotation d'APP_KEY
 *      invalidait rétroactivement l'ensemble de la chaîne ;
 *   2. verifierIntegriteComplete() ne vérifiait QUE les hachages, jamais la
 *      signature HMAC — un attaquant ayant un accès en écriture à la base
 *      pouvait donc réécrire l'historique de façon parfaitement cohérente.
 */
class AuditChainRotationTest extends TestCase
{
    use RefreshDatabase;

    private AuditChainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AuditChainService::class);

        config([
            'security.audit.key_version' => 1,
            'security.audit.keys'        => [1 => 'cle-audit-v1-pour-les-tests-32-caracteres'],
        ]);
    }

    public function test_un_bloc_est_signe_et_verifiable(): void
    {
        $bloc = $this->service->enregistrer('TEST_EVENT', ['foo' => 'bar']);

        $this->assertNotEmpty($bloc->signature);
        $this->assertSame(1, $bloc->key_version);
        $this->assertTrue($this->service->signatureValide($bloc));
    }

    public function test_la_chaine_complete_est_valide(): void
    {
        $this->service->enregistrer('A', ['n' => 1]);
        $this->service->enregistrer('B', ['n' => 2]);
        $this->service->enregistrer('C', ['n' => 3]);

        $resultats = $this->service->verifierIntegriteComplete();

        $this->assertTrue($resultats['valide'], json_encode($resultats['invalides']));
    }

    /**
     * Le cœur du correctif : une rotation d'APP_KEY ne doit RIEN casser,
     * puisque la chaîne s'appuie désormais sur une clé dédiée.
     */
    public function test_une_rotation_de_app_key_ninvalide_pas_la_chaine(): void
    {
        $this->service->enregistrer('AVANT_ROTATION', ['x' => 1]);

        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $resultats = $this->service->verifierIntegriteComplete();

        $this->assertTrue(
            $resultats['valide'],
            "Une rotation d'APP_KEY ne doit pas invalider la chaîne d'audit"
        );
    }

    /** Après rotation de la clé d'audit, les anciens blocs restent vérifiables. */
    public function test_la_rotation_de_cle_audit_preserve_lhistorique(): void
    {
        $ancien = $this->service->enregistrer('ANCIEN', ['v' => 1]);
        $this->assertSame(1, $ancien->key_version);

        // Rotation : nouvelle clé courante, ancienne conservée.
        config([
            'security.audit.key_version' => 2,
            'security.audit.keys'        => [
                1 => 'cle-audit-v1-pour-les-tests-32-caracteres',
                2 => 'cle-audit-v2-toute-neuve-32-caracteres-ok',
            ],
        ]);

        $nouveau = $this->service->enregistrer('NOUVEAU', ['v' => 2]);
        $this->assertSame(2, $nouveau->key_version);

        $resultats = $this->service->verifierIntegriteComplete();

        $this->assertTrue($resultats['valide'], json_encode($resultats['invalides']));
        $this->assertTrue($this->service->signatureValide($ancien->refresh()));
        $this->assertTrue($this->service->signatureValide($nouveau));
    }

    /** Un payload modifié en base doit être détecté. */
    public function test_une_alteration_du_payload_est_detectee(): void
    {
        $bloc = $this->service->enregistrer('SENSIBLE', ['montant' => 100]);

        DB::table('audit_chain')
            ->where('id', $bloc->id)
            ->update(['payload' => json_encode(['event' => 'SENSIBLE', 'montant' => 999999])]);

        $resultats = $this->service->verifierIntegriteComplete();

        $this->assertFalse($resultats['valide']);
    }

    /**
     * Le scénario que la vérification de signature ajoute : un attaquant
     * recalcule des hachages parfaitement cohérents. Sans HMAC, indétectable.
     */
    public function test_une_reecriture_coherente_est_detectee_par_la_signature(): void
    {
        $this->service->enregistrer('LEGITIME', ['montant' => 100]);
        $bloc = AuditChain::orderByDesc('bloc_numero')->first();

        $payloadFalsifie = ['event' => 'LEGITIME', 'montant' => 999999, 'timestamp' => now()->toIso8601String()];
        $dataHashCoherent = hash('sha256', $this->service->encoderPayload($payloadFalsifie));

        // L'attaquant met à jour payload ET data_hash de façon cohérente.
        DB::table('audit_chain')->where('id', $bloc->id)->update([
            'payload'   => json_encode($payloadFalsifie),
            'data_hash' => $dataHashCoherent,
        ]);

        $resultats = $this->service->verifierIntegriteComplete();

        $this->assertFalse(
            $resultats['valide'],
            'Une réécriture cohérente doit être détectée par la signature HMAC'
        );

        $raisons = array_column($resultats['invalides'], 'raison');
        $this->assertNotEmpty(
            array_filter($raisons, fn ($r) => str_contains($r, 'signature')),
            'La détection doit provenir de la signature'
        );
    }

    /** Une signature bricolée est rejetée. */
    public function test_une_signature_forgee_est_rejetee(): void
    {
        $bloc = $this->service->enregistrer('X', ['a' => 1]);

        DB::table('audit_chain')
            ->where('id', $bloc->id)
            ->update(['signature' => str_repeat('a', 64)]);

        $this->assertFalse($this->service->signatureValide($bloc->refresh()));
    }

    /** L'encodage doit être stable quel que soit l'ordre des clés. */
    public function test_lencodage_du_payload_est_canonique(): void
    {
        $a = $this->service->encoderPayload(['b' => 2, 'a' => 1]);
        $b = $this->service->encoderPayload(['a' => 1, 'b' => 2]);

        $this->assertSame($a, $b, 'Le hachage ne doit pas dépendre de l\'ordre des clés');
    }

    /** L'ordre des listes, lui, est signifiant et doit être préservé. */
    public function test_lordre_des_listes_est_preserve(): void
    {
        $a = $this->service->encoderPayload(['l' => [1, 2, 3]]);
        $b = $this->service->encoderPayload(['l' => [3, 2, 1]]);

        $this->assertNotSame($a, $b);
    }
}
