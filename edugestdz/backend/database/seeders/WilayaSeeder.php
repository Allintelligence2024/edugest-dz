<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WilayaSeeder extends Seeder
{
    public function run(): void
    {
        $wilayas = [
            [1, '01', 'Adrar', 'أدرار'],
            [2, '02', 'Chlef', 'الشلف'],
            [3, '03', 'Laghouat', 'الأغواط'],
            [4, '04', 'Oum El Bouaghi', 'أم البواقي'],
            [5, '05', 'Batna', 'باتنة'],
            [6, '06', 'Béjaïa', 'بجاية'],
            [7, '07', 'Biskra', 'بسكرة'],
            [8, '08', 'Béchar', 'بشار'],
            [9, '09', 'Blida', 'البليدة'],
            [10, '10', 'Bouira', 'البويرة'],
            [11, '11', 'Tamanrasset', 'تمنراست'],
            [12, '12', 'Tébessa', 'تبسة'],
            [13, '13', 'Tlemcen', 'تلمسان'],
            [14, '14', 'Tiaret', 'تيارت'],
            [15, '15', 'Tizi Ouzou', 'تيزي وزو'],
            [16, '16', 'Alger', 'الجزائر'],
            [17, '17', 'Djelfa', 'الجلفة'],
            [18, '18', 'Jijel', 'جيجل'],
            [19, '19', 'Sétif', 'سطيف'],
            [20, '20', 'Saïda', 'سعيدة'],
            [21, '21', 'Skikda', 'سكيكدة'],
            [22, '22', 'Sidi Bel Abbès', 'سيدي بلعباس'],
            [23, '23', 'Annaba', 'عنابة'],
            [24, '24', 'Guelma', 'قالمة'],
            [25, '25', 'Constantine', 'قسنطينة'],
            [26, '26', 'Médéa', 'المدية'],
            [27, '27', 'Mostaganem', 'مستغانم'],
            [28, '28', 'Msila', 'المسيلة'],
            [29, '29', 'Mascara', 'معسكر'],
            [30, '30', 'Ouargla', 'ورقلة'],
            [31, '31', 'Oran', 'وهران'],
            [32, '32', 'El Bayadh', 'البيض'],
            [33, '33', 'Illizi', 'إليزي'],
            [34, '34', 'Bordj Bou Arreridj', 'برج بوعريريج'],
            [35, '35', 'Boumerdès', 'بومرداس'],
            [36, '36', 'El Tarf', 'الطارف'],
            [37, '37', 'Tindouf', 'تندوف'],
            [38, '38', 'Tissemsilt', 'تيسمسيلت'],
            [39, '39', 'El Oued', 'الوادي'],
            [40, '40', 'Khenchela', 'خنشلة'],
            [41, '41', 'Souk Ahras', 'سوق أهراس'],
            [42, '42', 'Tipaza', 'تيبازة'],
            [43, '43', 'Mila', 'ميلة'],
            [44, '44', 'Aïn Defla', 'عين الدفلى'],
            [45, '45', 'Naâma', 'النعامة'],
            [46, '46', 'Aïn Témouchent', 'عين تموشنت'],
            [47, '47', 'Ghardaïa', 'غرداية'],
            [48, '48', 'Relizane', 'غليزان'],
            [49, '49', 'Timimoun', 'تيميمون'],
            [50, '50', 'Bordj Badji Mokhtar', 'برج باجي مختار'],
            [51, '51', 'Ouled Djellal', 'أولاد جلال'],
            [52, '52', 'Béni Abbès', 'بني عباس'],
            [53, '53', 'In Salah', 'عين صالح'],
            [54, '54', 'In Guezzam', 'عين قزام'],
            [55, '55', 'Touggourt', 'تقرت'],
            [56, '56', 'Djanet', 'جانت'],
            [57, '57', 'El M\'Ghair', 'المغير'],
            [58, '58', 'El Meniaa', 'المنيعة'],
        ];

        DB::table('wilayas')->insertOrIgnore(array_map(fn ($w) => [
            'id'     => $w[0],
            'code'   => $w[1],
            'nom_fr' => $w[2],
            'nom_ar' => $w[3],
        ], $wilayas));
    }
}
