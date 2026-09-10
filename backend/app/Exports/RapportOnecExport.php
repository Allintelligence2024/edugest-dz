<?php

namespace App\Exports;

use App\Models\CandidatExamen;
use App\Models\SessionExamen;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export complet rapport ONDEC : liste candidats + statistiques.
 */
class RapportOnecExport implements WithMultipleSheets
{
    protected string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function sheets(): array
    {
        return [
            new CandidatsOnecSheet($this->sessionId),
            new StatistiquesOnecSheet($this->sessionId),
        ];
    }
}

/**
 * Feuille 1 : Liste des candidats avec salles et présences.
 */
class CandidatsOnecSheet implements FromQuery, WithHeadings, WithStyles, ShouldAutoSize
{
    protected string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return CandidatExamen::query()
            ->where('session_id', $this->sessionId)
            ->leftJoin('salles_examen', 'candidats_examen.salle_id', '=', 'salles_examen.id')
            ->select([
                'candidats_examen.numero_inscription',
                'candidats_examen.nom',
                'candidats_examen.prenom',
                'candidats_examen.date_naissance',
                'candidats_examen.lieu_naissance',
                'candidats_examen.type_candidat',
                'candidats_examen.filiere',
                'salles_examen.nom as salle',
                'candidats_examen.numero_place',
                'candidats_examen.present',
            ])
            ->orderBy('candidats_examen.nom')
            ->orderBy('candidats_examen.prenom');
    }

    public function headings(): array
    {
        return [
            'N° Inscription',
            'Nom',
            'Prénom',
            'Date naissance',
            'Lieu naissance',
            'Type',
            'Filière',
            'Salle',
            'N° Place',
            'Présent',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}

/**
 * Feuille 2 : Statistiques ONDEC (nombre candidats, taux présence, etc.).
 */
class StatistiquesOnecSheet implements FromQuery, WithHeadings, WithStyles, ShouldAutoSize
{
    protected string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        $stats = $this->calculerStats();

        return CandidatExamen::query()
            ->where('session_id', $this->sessionId)
            ->limit(0)
            ->select([
                DB::raw("'{$stats['total_candidats']}' as indicateur"),
                DB::raw("'{$stats['candidats_scolarises']}' as valeur"),
            ]);
    }

    public function headings(): array
    {
        return ['Indicateur', 'Valeur'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }

    /**
     * Calcule les statistiques ONDEC pour la session.
     */
    protected function calculerStats(): array
    {
        $candidats = CandidatExamen::where('session_id', $this->sessionId);

        $total = (clone $candidats)->count();
        $presents = (clone $candidats)->where('present', true)->count();
        $scolarises = (clone $candidats)->where('type_candidat', 'scolarise')->count();
        $libres = (clone $candidats)->where('type_candidat', 'libre')->count();

        $session = SessionExamen::findOrFail($this->sessionId);
        $nb_salles = $session->salles()->count();
        $nb_epreuves = $session->epreuves()->count();

        return [
            'total_candidats'       => $total,
            'candidats_presents'    => $presents,
            'candidats_absents'     => $total - $presents,
            'taux_presence'         => $total > 0 ? round($presents / $total * 100, 1) . '%' : '0%',
            'candidats_scolarises'  => $scolarises,
            'candidats_libres'      => $libres,
            'nb_salles'             => $nb_salles,
            'nb_epreuves'           => $nb_epreuves,
            'type_session'          => $session->type,
            'annee_scolaire'        => $session->annee_scolaire,
            'wilaya'                => $session->wilaya ?? 'N/A',
            'centre'                => $session->nom_centre ?? 'N/A',
        ];
    }
}
