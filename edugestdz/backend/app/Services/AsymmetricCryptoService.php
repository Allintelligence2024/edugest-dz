<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * AsymmetricCryptoService — Service de cryptographie asymétrique.
 *
 * HONNÊTETÉ TECHNIQUE (corrigé suite audit externe Juillet 2026) :
 *
 * Ce service utilise :
 *   1. Ed25519 (libsodium) si disponible — courbes elliptiques Curve25519
 *   2. RSA-4096 (OpenSSL) en fallback
 *
 * CE N'EST PAS du "post-quantique" au sens NIST PQC 2024.
 * Ed25519 et RSA sont tous deux cassables par l'algorithme de Shor
 * sur un ordinateur quantique suffisamment puissant.
 *
 * RÉSISTANCE RÉELLE :
 *   - Ed25519  : 128 bits classique | NON résistant quantique
 *   - RSA-4096 : ~140 bits classique | NON résistant quantique
 *
 * VRAI POST-QUANTIQUE (NIST PQC 2024, pas encore en PHP natif) :
 *   - CRYSTALS-Kyber    : échange de clés (KEM)
 *   - CRYSTALS-Dilithium : signature numérique
 *   - SPHINCS+           : signature hash-based
 *
 * FEUILLE DE ROUTE :
 *   Quand une bibliothèque PHP stable implémente CRYSTALS-Dilithium
 *   (ex: openssl 3.x avec liboqs), ce service sera mis à jour.
 *   L'interface publique (signer/verifier/genererPaireDeClés) restera identique.
 *
 * POURQUOI ED25519 EST QUAND MÊME UN BON CHOIX AUJOURD'HUI :
 *   - Plus sûr que RSA-2048 contre les attaques classiques
 *   - Signatures plus petites (64 bytes vs 512 bytes RSA)
 *   - Calculs plus rapides
 *   - Recommandé par ANSSI, BSI, NIST pour les usages actuels
 *   - Les ordinateurs quantiques suffisamment puissants n'existent pas encore (2026)
 */
class AsymmetricCryptoService
{
    private ?string $publicKey  = null;
    private ?string $privateKey = null;
    private bool    $useSodium  = false;
    private string  $algorithme = 'unknown';

    public function __construct()
    {
        $this->useSodium = extension_loaded('sodium')
            && function_exists('sodium_crypto_sign_keypair');

        if ($this->useSodium) {
            try {
                $keypair          = sodium_crypto_sign_keypair();
                $this->publicKey  = sodium_crypto_sign_publickey($keypair);
                $this->privateKey = sodium_crypto_sign_secretkey($keypair);
                $this->algorithme = 'Ed25519'; // Courbes elliptiques Curve25519
            } catch (\Throwable $e) {
                Log::warning('AsymmetricCrypto: sodium indisponible, fallback RSA-4096', [
                    'error' => $e->getMessage(),
                ]);
                $this->useSodium = false;
                $this->genererRsa();
            }
        } else {
            Log::info('AsymmetricCrypto: sodium non chargé, utilisation RSA-4096');
            $this->genererRsa();
        }
    }

    /**
     * Signer des données.
     * Retourne la signature encodée en base64.
     */
    public function signer(string $data): string
    {
        if ($this->useSodium && $this->privateKey !== null) {
            return base64_encode(sodium_crypto_sign_detached($data, $this->privateKey));
        }

        if (!str_starts_with($this->privateKey ?? '', '-----BEGIN')) {
            // Fallback HMAC si ni sodium ni OpenSSL disponible
            return base64_encode(hash_hmac('sha512', $data, $this->privateKey ?? '', true));
        }

        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }

    /**
     * Vérifier une signature.
     */
    public function verifier(string $data, string $signature): bool
    {
        $decoded = base64_decode($signature, true);
        if ($decoded === false) return false;

        if ($this->useSodium && $this->publicKey !== null) {
            return sodium_crypto_sign_verify_detached($decoded, $data, $this->publicKey);
        }

        if (!str_starts_with($this->privateKey ?? '', '-----BEGIN')) {
            $expected = hash_hmac('sha512', $data, $this->privateKey ?? '', true);
            return hash_equals($expected, $decoded);
        }

        $result = openssl_verify($data, $decoded, $this->publicKey, OPENSSL_ALGO_SHA512);
        return $result === 1;
    }

    /**
     * Obtenir la clé publique encodée en base64.
     */
    public function getPublicKey(): string
    {
        return base64_encode($this->publicKey ?? '');
    }

    /**
     * Obtenir le niveau de sécurité réel (honnête).
     */
    public function niveauSecuriteReel(): array
    {
        return [
            'algorithme'               => $this->algorithme,
            'bits_securite_classique'  => match ($this->algorithme) {
                'Ed25519'  => 128,
                'RSA-4096' => 140,
                default    => 64,
            },
            'resistant_quantique'      => false, // HONNÊTE — ni Ed25519 ni RSA ne le sont
            'resistant_classique'      => true,
            'sodium_disponible'        => $this->useSodium,
            'recommande_pour_aujourdhui' => true, // Ed25519 est le best practice actuel
            'note_honnete'             => 'Ed25519 est excellent contre les attaques classiques '
                . 'actuelles. Il sera remplacé par CRYSTALS-Dilithium quand PHP l\'implémentera nativement.',
            'reference_nist'           => 'NIST PQC 2024 : FIPS 203 (Kyber), FIPS 204 (Dilithium), FIPS 205 (SPHINCS+)',
        ];
    }

    /**
     * Générer une paire de clés RSA-4096 (fallback si sodium absent).
     */
    private function genererRsa(): void
    {
        $this->algorithme = 'RSA-4096';

        if (!extension_loaded('openssl') || !function_exists('openssl_pkey_new')) {
            // Double fallback HMAC si OpenSSL aussi absent (environnement très limité)
            $this->publicKey  = hash('sha256', 'fallback-public-'  . config('app.key'));
            $this->privateKey = hash('sha256', 'fallback-private-' . config('app.key'));
            $this->algorithme = 'HMAC-SHA512-fallback';
            Log::error('AsymmetricCrypto: ni sodium ni openssl disponibles — fallback HMAC (non recommandé)');
            return;
        }

        $resource = @openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->publicKey  = hash('sha256', 'fallback-public-'  . config('app.key'));
            $this->privateKey = hash('sha256', 'fallback-private-' . config('app.key'));
            $this->algorithme = 'HMAC-SHA512-fallback';
            return;
        }

        openssl_pkey_export($resource, $this->privateKey);
        $details        = openssl_pkey_get_details($resource);
        $this->publicKey= $details['key'];
    }
}
