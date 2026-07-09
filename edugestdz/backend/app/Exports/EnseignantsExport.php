<?php

namespace App\Exports;

use App\Models\Enseignant;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EnseignantsExport implements FromQuery, WithHeadings, WithStyles, ShouldAutoSize
{
    protected string $tenantId;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function query(): Builder
    {
        return Enseignant::query()
            ->where('tenant_id', $this->tenantId)
            ->select(['nom', 'prenom', 'email', 'telephone', 'specialite', 'statut', 'created_at']);
    }

    public function headings(): array
    {
        return [
            'Nom',
            'Prénom',
            'Email',
            'Téléphone',
            'Spécialité',
            'Statut',
            'Date création',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
