<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les colonnes sensibles existent déjà en TEXT.
        // Le chiffrement est géré par le cast EncryptedString au niveau Eloquent.
        // Les données existantes seront chiffrées via la commande edugest:chiffrer-donnees.
    }

    public function down(): void {}
};
