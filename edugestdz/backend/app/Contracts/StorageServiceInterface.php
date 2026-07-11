<?php

namespace App\Contracts;

interface StorageServiceInterface
{
    public function upload(string $path, $file): string;
    public function delete(string $path): bool;
    public function url(string $path): string;
}
