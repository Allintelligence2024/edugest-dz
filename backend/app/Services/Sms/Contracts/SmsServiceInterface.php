<?php
namespace App\Services\Sms\Contracts;

interface SmsServiceInterface
{
    public function send(string $to, string $message): array;
    public function sendBulk(array $recipients, string $message): array;
    public function getBalance(): ?float;
}
