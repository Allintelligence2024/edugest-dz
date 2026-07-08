<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_chain', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('bloc_numero')->unique();
            $table->string('previous_hash', 64);
            $table->string('data_hash', 64);
            $table->text('signature');
            $table->json('payload');
            $table->string('causer_id', 36)->nullable();
            $table->string('causer_type')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index('bloc_numero', 'idx_ac_bloc');
            $table->index('previous_hash', 'idx_ac_prev');
        });

        $genesisPayload = json_encode([
            'event' => 'GENESIS_BLOCK',
            'message' => 'Bloc genesis de la chaine d\'audit immuable',
            'created_at' => now()->toIso8601String(),
        ]);

        $dataHash = hash('sha256', $genesisPayload);
        $previousHash = str_repeat('0', 64);
        $signature = hash('sha256', 'genesis:' . $previousHash . ':' . $dataHash);

        DB::table('audit_chain')->insert([
            'id' => '00000000-0000-0000-0000-000000000000',
            'bloc_numero' => 0,
            'previous_hash' => $previousHash,
            'data_hash' => $dataHash,
            'signature' => $signature,
            'payload' => $genesisPayload,
            'causer_id' => null,
            'causer_type' => null,
            'logged_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain');
    }
};
