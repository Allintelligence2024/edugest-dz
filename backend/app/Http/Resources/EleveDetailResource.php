<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EleveDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['age'] = $this->age;
        $data['photo_url_full'] = $this->photo_url_full;

        return $data;
    }
}
