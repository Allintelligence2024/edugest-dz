<?php

namespace App\Services;

class PasswordPolicyService
{
    private const MOTS_DE_PASSE_INTERDITS = [
        'password', 'password1', '123456', '12345678', '123456789',
        '1234567890', 'qwerty', 'abc123', 'Password1', 'password123',
        'admin', 'admin123', 'Admin123', 'azerty', 'azerty123',
        'Algeria2024', 'Algeria2025', 'Algeria2026', 'Algerie123',
        'EduGest', 'EduGest123', 'edugest', 'ecole123', 'ecole2026',
        'directeur', 'Directeur1', 'enseignant', 'parent123',
        'motdepasse', 'motdepasse1', 'bonjour123', 'salam123',
        'welcome', 'Welcome1', 'changeme', 'changeit',
        '111111', '111111111', '000000', '000000000',
        'iloveyou', 'sunshine', 'princess', 'dragon',
        'aaaaaa', 'aaaaaaaa', 'zzzzzz', '1q2w3e4r',
        'qazwsx', 'qwerty123', 'Qwerty123', 'Pass@word1',
    ];

    public function valider(string $password, ?string $email = null, ?string $nomEcole = null): array
    {
        $violations = [];

        if (strlen($password) < 12) {
            $violations[] = 'Le mot de passe doit contenir au moins 12 caractères.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $violations[] = 'Au moins une lettre majuscule est requise.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $violations[] = 'Au moins une lettre minuscule est requise.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $violations[] = 'Au moins un chiffre est requis.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $violations[] = 'Au moins un caractère spécial est requis (@, #, !, ...)';
        }

        if (in_array($password, self::MOTS_DE_PASSE_INTERDITS)) {
            $violations[] = 'Ce mot de passe est trop courant et ne peut pas être utilisé.';
        }

        if ($email && strtolower($password) === strtolower($email)) {
            $violations[] = 'Le mot de passe ne peut pas être identique à l\'email.';
        }

        if ($nomEcole && mb_stripos($password, $nomEcole) !== false) {
            $violations[] = 'Le mot de passe ne peut pas contenir le nom de l\'établissement.';
        }

        if (preg_match('/(.)\1{3,}/', $password)) {
            $violations[] = 'Le mot de passe ne peut pas contenir 4 caractères identiques consécutifs.';
        }

        return $violations;
    }

    public function estConforme(string $password, ?string $email = null): bool
    {
        return empty($this->valider($password, $email));
    }

    public function calculerForce(string $password): array
    {
        $score = 0;

        if (strlen($password) >= 8)  $score += 20;
        if (strlen($password) >= 12) $score += 10;
        if (strlen($password) >= 16) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 20;

        $niveau = match(true) {
            $score >= 80 => 'Très fort',
            $score >= 60 => 'Fort',
            $score >= 40 => 'Moyen',
            $score >= 20 => 'Faible',
            default      => 'Très faible',
        };

        return ['score' => $score, 'niveau' => $niveau];
    }
}
