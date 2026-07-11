<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfilApprentissageService
{
    public function calculerProfil(string $eleveId, string $tenantId = null): array
    {
        $tenantId   = $tenantId ?? config('tenant.current_id');
        $historique = $this->chargerHistoriqueSemaine($eleveId, 8);
        $moy8sem    = $historique->avg('moyenne') ?? 10.0;
        $variance   = $this->variance($historique->pluck('moyenne')->toArray());
        $tendance   = $this->pente($historique->pluck('moyenne')->toArray());
        $absencesPct = $this->calculerPctAbsences($eleveId);

        [$nbChutesRec, $nbChutesSansRec] = $this->detecterChuteEtRecuperation($historique);

        $profil = $this->choisirProfil($moy8sem, $variance, $tendance, $absencesPct, $nbChutesSansRec, $nbChutesRec);

        [$pointsForts, $pointsFaibles] = $this->calculerPointsForts($eleveId);

        $this->sauvegarder($eleveId, $tenantId, [
            'profil'                     => $profil,
            'stabilite_score'            => max(0, min(100, 100 - $variance * 5)),
            'tendance_long_terme'        => round($tendance, 3),
            'variance_notes'             => round($variance, 4),
            'nb_chutes_recuperees'       => $nbChutesRec,
            'nb_chutes_non_recuperees'   => $nbChutesSansRec,
            'correlation_absences_notes' => $this->correlationAbsenceNote($eleveId),
            'points_forts'               => json_encode($pointsForts),
            'points_faibles'             => json_encode($pointsFaibles),
            'historique_profils'         => $this->majHistoriqueProfils($eleveId, $profil),
        ]);

        return [
            'profil'         => $profil,
            'label_fr'       => $this->labelFr($profil),
            'emoji'          => $this->emoji($profil),
            'alarme'         => $this->requiertAlarme($profil),
            'points_forts'   => $pointsForts,
            'points_faibles' => $pointsFaibles,
            'stabilite'      => max(0, min(100, (int)(100 - $variance * 5))),
            'explication'    => $this->explication($profil, $moy8sem, $tendance),
        ];
    }

    private function choisirProfil(
        float $moy,
        float $var,
        float $tendance,
        float $absPct,
        int   $chutesSansRec,
        int   $chutesRec
    ): string {
        if ($moy >= 15.0 && $var < 3.0)               return 'excellent_stable';
        if ($absPct > 0.20)                            return 'absenteiste';
        if ($moy < 5.0 && $chutesSansRec >= 3)         return 'decrochage_avance';
        if ($tendance < -0.8 && $var < 5.0)            return 'chute_rapide';
        if ($chutesRec >= 2 && $chutesSansRec < 2)     return 'resilient';
        if ($var > 8.0)                                return 'instable_oscillant';
        if ($moy >= 12.0 && $var < 5.0)               return 'bon_regulier';
        if ($tendance > 0.5 && $moy < 10.0)           return 'fragile_amelioration';
        if ($moy >= 8.0 && $var < 4.0)                return 'moyen_stable';
        return 'moyen_stable';
    }

    private function requiertAlarme(string $profil): bool
    {
        return in_array($profil, ['chute_rapide', 'decrochage_avance', 'absenteiste']);
    }

    private function labelFr(string $profil): string
    {
        return match ($profil) {
            'excellent_stable'     => 'Excellent & Stable',
            'bon_regulier'         => 'Bon & Régulier',
            'moyen_stable'         => 'Niveau Stable (sans alarme)',
            'fragile_amelioration' => 'Fragile mais en Progrès',
            'chute_rapide'         => 'Chute Rapide',
            'instable_oscillant'   => 'Instable / Irrégulier',
            'absenteiste'          => 'Absentéisme Préoccupant',
            'decrochage_avance'    => 'Décrochage Avancé',
            'saisonnier'           => 'Difficultés Saisonnières',
            'resilient'            => 'Résilient (se récupère bien)',
            default                => 'Profil en cours d\'analyse',
        };
    }

    private function emoji(string $profil): string
    {
        return match ($profil) {
            'excellent_stable'     => '⭐',
            'bon_regulier'         => '✅',
            'moyen_stable'         => '〰️',
            'fragile_amelioration' => '📈',
            'chute_rapide'         => '📉',
            'instable_oscillant'   => '〜',
            'absenteiste'          => '🚫',
            'decrochage_avance'    => '🆘',
            'resilient'            => '💪',
            default                => '❓',
        };
    }

    private function explication(string $profil, float $moy, float $tendance): string
    {
        return match ($profil) {
            'chute_rapide'         => "Baisse de " . abs(round($tendance, 1)) . " pts/semaine sur 8 semaines — Intervention requise",
            'moyen_stable'         => "Moyenne de {$moy}/20 stable depuis 8 semaines — Niveau consolidé, pas d'alarme",
            'decrochage_avance'    => "Situation critique depuis plusieurs semaines — Plan d'urgence requis",
            'resilient'            => "Élève qui récupère systématiquement après les chutes — Bon signe",
            'fragile_amelioration' => "En difficulté mais la tendance est positive (+{$tendance} pts/sem)",
            'excellent_stable'     => "Excellentes performances stables — Peut être valorisé",
            default                => "Profil {$profil} — Surveillance standard",
        };
    }

    private function chargerHistoriqueSemaine(string $eleveId, int $semaines)
    {
        try {
            return DB::table('historique_diagnostics as h')
                ->where('h.eleve_id', $eleveId)
                ->where('h.analyse_le', '>=', now()->subWeeks($semaines))
                ->select('h.moyenne_generale as moyenne', 'h.analyse_le')
                ->orderBy('h.analyse_le')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function variance(array $vals): float
    {
        if (count($vals) < 2) return 0.0;
        $moy = array_sum($vals) / count($vals);
        return array_sum(array_map(fn($x) => ($x - $moy) ** 2, $vals)) / count($vals);
    }

    private function pente(array $vals): float
    {
        $n = count($vals);
        if ($n < 2) return 0.0;
        $xs   = range(0, $n - 1);
        $moyX = array_sum($xs) / $n;
        $moyY = array_sum($vals) / $n;
        $num  = $den = 0;
        foreach ($xs as $i => $x) {
            $num += ($x - $moyX) * ($vals[$i] - $moyY);
            $den += ($x - $moyX) ** 2;
        }
        return $den > 0 ? round($num / $den, 3) : 0.0;
    }

    private function detecterChuteEtRecuperation($historique): array
    {
        $vals = $historique->pluck('moyenne')->toArray();
        if (count($vals) < 3) return [0, 0];

        $chutesRec = $chutesSansRec = 0;
        $i = 1;
        while ($i < count($vals) - 1) {
            if ($vals[$i] < $vals[$i - 1] - 1.5) {
                if ($i + 1 < count($vals) && $vals[$i + 1] > $vals[$i] + 1.0) {
                    $chutesRec++;
                    $i += 2;
                } else {
                    $chutesSansRec++;
                    $i++;
                }
            } else {
                $i++;
            }
        }

        return [$chutesRec, $chutesSansRec];
    }

    private function calculerPctAbsences(string $eleveId): float
    {
        try {
            $total = DB::table('absences_journalieres')
                ->where('eleve_id', $eleveId)
                ->where('date_absence', '>=', now()->subWeeks(8)->toDateString())
                ->count();

            return min(1.0, $total / 40.0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function correlationAbsenceNote(string $eleveId): ?float
    {
        try {
            $donnees = DB::table('historique_diagnostics')
                ->where('eleve_id', $eleveId)
                ->where('analyse_le', '>=', now()->subWeeks(8))
                ->select('moyenne_generale', DB::raw("(details->'comportement'->>'absences')::numeric as nb_abs"))
                ->get();

            if ($donnees->count() < 3) return null;

            $moyennes = $donnees->pluck('moyenne_generale')->toArray();
            $absences = $donnees->map(fn($d) => (float)($d->nb_abs ?? 0))->toArray();
            $n        = count($moyennes);
            $moyM     = array_sum($moyennes) / $n;
            $moyA     = array_sum($absences) / $n;

            $num = $denM = $denA = 0;
            for ($i = 0; $i < $n; $i++) {
                $num  += ($moyennes[$i] - $moyM) * ($absences[$i] - $moyA);
                $denM += ($moyennes[$i] - $moyM) ** 2;
                $denA += ($absences[$i] - $moyA) ** 2;
            }

            $den = sqrt($denM * $denA);
            return $den > 0 ? round(-$num / $den, 3) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function calculerPointsForts(string $eleveId): array
    {
        try {
            $parMatiere = DB::table('notes as n')
                ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
                ->join('cours as c', 'e.groupe_id', '=', 'c.groupe_id')
                ->join('matieres as m', 'c.matiere_id', '=', 'm.id')
                ->where('n.eleve_id', $eleveId)
                ->where('e.date_evaluation', '>=', now()->subWeeks(8)->toDateString())
                ->groupBy('m.id', 'm.nom_fr')
                ->select('m.nom_fr', DB::raw('AVG(n.note) as moy'))
                ->get();

            $forts   = $parMatiere->filter(fn($m) => $m->moy >= 13)->pluck('nom_fr')->toArray();
            $faibles = $parMatiere->filter(fn($m) => $m->moy < 8)->pluck('nom_fr')->toArray();

            return [$forts, $faibles];
        } catch (\Throwable $e) {
            return [[], []];
        }
    }

    private function majHistoriqueProfils(string $eleveId, string $nouveauProfil): string
    {
        try {
            $actuel = DB::table('profils_apprentissage')
                ->where('eleve_id', $eleveId)
                ->value('historique_profils');

            $historique = $actuel ? json_decode($actuel, true) : [];
            $historique[] = ['date' => now()->toDateString(), 'profil' => $nouveauProfil];

            $historique = array_slice($historique, -12);

            return json_encode($historique);
        } catch (\Throwable $e) {
            return json_encode([['date' => now()->toDateString(), 'profil' => $nouveauProfil]]);
        }
    }

    private function sauvegarder(string $eleveId, string $tenantId, array $data): void
    {
        try {
            $existing = DB::table('profils_apprentissage')->where('eleve_id', $eleveId)->first();

            $payload = array_merge($data, [
                'tenant_id'  => $tenantId,
                'eleve_id'   => $eleveId,
                'calcule_le' => now(),
                'updated_at' => now(),
            ]);

            if ($existing) {
                DB::table('profils_apprentissage')->where('eleve_id', $eleveId)->update($payload);
            } else {
                DB::table('profils_apprentissage')->insert(array_merge($payload, [
                    'id'         => (string) Str::uuid(),
                    'created_at' => now(),
                ]));
            }
        } catch (\Throwable $e) {
            // Silently ignore persistence errors
        }
    }
}
