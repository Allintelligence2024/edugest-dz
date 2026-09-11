<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class VisioService
{
    public function generateLink(string $reservationId, string $coursName): string
    {
        $random = Str::random(8);
        $slug = Str::slug($coursName);

        return "https://meet.jit.si/EduGestDZ_{$reservationId}_{$slug}_{$random}";
    }
}
