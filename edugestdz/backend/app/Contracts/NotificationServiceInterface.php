<?php

namespace App\Contracts;

interface NotificationServiceInterface
{
    public function envoyer(string $destinataire, string $sujet, string $contenu): bool;
    public function envoyerSms(string $telephone, string $message): bool;
    public function envoyerEmail(string $email, string $sujet, string $contenu): bool;
}
