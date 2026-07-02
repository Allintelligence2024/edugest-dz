<?php
// database/migrations/0004_create_referentiels_algeriens.php
use App\Models\Wilaya;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Wilayas d'Algérie (48) ──
        Schema::create('wilayas', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('code', 5);
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
        });

        // ── Communes ──
        Schema::create('communes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedSmallInteger('wilaya_id');
            $table->string('code_postal', 10)->nullable();
            $table->string('nom_fr', 150);
            $table->string('nom_ar', 150)->nullable();
            $table->foreign('wilaya_id')->references('id')->on('wilayas');
        });

        // ── Calendrier Scolaire Algérien ──
        Schema::create('calendrier_scolaire', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('annee_scolaire', 10);
            $table->string('evenement', 200);
            $table->string('type');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->unsignedSmallInteger('wilaya_id')->nullable();
            $table->timestamps();
        });

        // ── Audit Logs ──
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('action', 100);
            $table->string('table_concernee', 50)->nullable();
            $table->uuid('enregistrement_id')->nullable();
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });

        // Seed wilayas
        if (\App::environment('testing') || !Wilaya::count()) {
            Wilaya::insert([
                ['id' => 1,  'code' => '01', 'nom_fr' => 'Adrar',       'nom_ar' => 'أدرار'],
                ['id' => 2,  'code' => '02', 'nom_fr' => 'Chlef',       'nom_ar' => 'الشلف'],
                ['id' => 3,  'code' => '03', 'nom_fr' => 'Laghouat',    'nom_ar' => 'الأغواط'],
                ['id' => 4,  'code' => '04', 'nom_fr' => 'Oum El Bouaghi', 'nom_ar' => 'أم البواقي'],
                ['id' => 5,  'code' => '05', 'nom_fr' => 'Batna',       'nom_ar' => 'باتنة'],
                ['id' => 6,  'code' => '06', 'nom_fr' => 'Béjaïa',      'nom_ar' => 'بجاية'],
                ['id' => 7,  'code' => '07', 'nom_fr' => 'Biskra',      'nom_ar' => 'بسكرة'],
                ['id' => 8,  'code' => '08', 'nom_fr' => 'Béchar',      'nom_ar' => 'بشار'],
                ['id' => 9,  'code' => '09', 'nom_fr' => 'Blida',       'nom_ar' => 'البليدة'],
                ['id' => 10, 'code' => '10', 'nom_fr' => 'Bouira',      'nom_ar' => 'البويرة'],
                ['id' => 11, 'code' => '11', 'nom_fr' => 'Tamanrasset', 'nom_ar' => 'تمنراست'],
                ['id' => 12, 'code' => '12', 'nom_fr' => 'Tébessa',     'nom_ar' => 'تبسة'],
                ['id' => 13, 'code' => '13', 'nom_fr' => 'Tlemcen',     'nom_ar' => 'تلمسان'],
                ['id' => 14, 'code' => '14', 'nom_fr' => 'Tiaret',      'nom_ar' => 'تيارت'],
                ['id' => 15, 'code' => '15', 'nom_fr' => 'Tizi Ouzou',  'nom_ar' => 'تيزي وزو'],
                ['id' => 16, 'code' => '16', 'nom_fr' => 'Alger',       'nom_ar' => 'الجزائر'],
                ['id' => 17, 'code' => '17', 'nom_fr' => 'Djelfa',      'nom_ar' => 'الجلفة'],
                ['id' => 18, 'code' => '18', 'nom_fr' => 'Jijel',       'nom_ar' => 'جيجل'],
                ['id' => 19, 'code' => '19', 'nom_fr' => 'Sétif',       'nom_ar' => 'سطيف'],
                ['id' => 20, 'code' => '20', 'nom_fr' => 'Saïda',       'nom_ar' => 'سعيدة'],
                ['id' => 21, 'code' => '21', 'nom_fr' => 'Skikda',      'nom_ar' => 'سكيكدة'],
                ['id' => 22, 'code' => '22', 'nom_fr' => 'Sidi Bel Abbès', 'nom_ar' => 'سيدي بلعباس'],
                ['id' => 23, 'code' => '23', 'nom_fr' => 'Annaba',      'nom_ar' => 'عنابة'],
                ['id' => 24, 'code' => '24', 'nom_fr' => 'Guelma',      'nom_ar' => 'قالمة'],
                ['id' => 25, 'code' => '25', 'nom_fr' => 'Constantine', 'nom_ar' => 'قسنطينة'],
                ['id' => 26, 'code' => '26', 'nom_fr' => 'Médéa',       'nom_ar' => 'المدية'],
                ['id' => 27, 'code' => '27', 'nom_fr' => 'Mostaganem',  'nom_ar' => 'مستغانم'],
                ['id' => 28, 'code' => '28', 'nom_fr' => 'Msila',       'nom_ar' => 'المسيلة'],
                ['id' => 29, 'code' => '29', 'nom_fr' => 'Mascara',     'nom_ar' => 'معسكر'],
                ['id' => 30, 'code' => '30', 'nom_fr' => 'Ouargla',     'nom_ar' => 'ورقلة'],
                ['id' => 31, 'code' => '31', 'nom_fr' => 'Oran',        'nom_ar' => 'وهران'],
                ['id' => 32, 'code' => '32', 'nom_fr' => 'El Bayadh',   'nom_ar' => 'البيض'],
                ['id' => 33, 'code' => '33', 'nom_fr' => 'Illizi',      'nom_ar' => 'إليزي'],
                ['id' => 34, 'code' => '34', 'nom_fr' => 'Bordj Bou Arreridj', 'nom_ar' => 'برج بوعريريج'],
                ['id' => 35, 'code' => '35', 'nom_fr' => 'Boumerdès',   'nom_ar' => 'بومرداس'],
                ['id' => 36, 'code' => '36', 'nom_fr' => 'El Tarf',     'nom_ar' => 'الطارف'],
                ['id' => 37, 'code' => '37', 'nom_fr' => 'Tindouf',     'nom_ar' => 'تندوف'],
                ['id' => 38, 'code' => '38', 'nom_fr' => 'Tissemsilt',  'nom_ar' => 'تيسمسيلت'],
                ['id' => 39, 'code' => '39', 'nom_fr' => 'El Oued',     'nom_ar' => 'الوادي'],
                ['id' => 40, 'code' => '40', 'nom_fr' => 'Khenchela',   'nom_ar' => 'خنشلة'],
                ['id' => 41, 'code' => '41', 'nom_fr' => 'Souk Ahras',  'nom_ar' => 'سوق أهراس'],
                ['id' => 42, 'code' => '42', 'nom_fr' => 'Tipaza',      'nom_ar' => 'تيبازة'],
                ['id' => 43, 'code' => '43', 'nom_fr' => 'Mila',        'nom_ar' => 'ميلة'],
                ['id' => 44, 'code' => '44', 'nom_fr' => 'Aïn Defla',   'nom_ar' => 'عين الدفلى'],
                ['id' => 45, 'code' => '45', 'nom_fr' => 'Naâma',       'nom_ar' => 'النعامة'],
                ['id' => 46, 'code' => '46', 'nom_fr' => 'Aïn Témouchent', 'nom_ar' => 'عين تموشنت'],
                ['id' => 47, 'code' => '47', 'nom_fr' => 'Ghardaïa',    'nom_ar' => 'غرداية'],
                ['id' => 48, 'code' => '48', 'nom_fr' => 'Relizane',    'nom_ar' => 'غليزان'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('calendrier_scolaire');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('wilayas');
    }
};
