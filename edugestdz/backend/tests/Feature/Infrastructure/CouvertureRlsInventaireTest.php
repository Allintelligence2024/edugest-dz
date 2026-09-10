<?php

namespace Tests\Feature\Infrastructure;

use App\Console\Commands\RlsStatusCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Vérifie que l'inventaire RLS (RlsStatusCommand::TABLES_ATTENDUES) couvre
 * exactement les tables qui portent réellement une colonne `tenant_id` dans
 * les migrations. Ce test est statique (aucun driver requis) : il tourne donc
 * en CI sur SQLite et protège la liste contre toute désynchronisation.
 */
class CouvertureRlsInventaireTest extends TestCase
{
    /**
     * Extrait depuis les fichiers de migration le nom des tables créées dans
     * un bloc Schema::create contenant une déclaration de colonne tenant_id.
     *
     * @return list<string>
     */
    private function tablesTenantDansMigrations(): array
    {
        $resultat = [];
        $chemin = database_path('migrations');

        foreach (File::files($chemin) as $fichier) {
            $contenu = File::get($fichier->getPathname());

            if (!str_contains($contenu, "Schema::create(")) {
                continue;
            }

            // Isolation des blocs Schema::create par équilibrage d'accolades.
            $pattern = "/Schema::create\('([^']+)'\s*,\s*function\s*\(/";
            preg_match_all($pattern, $contenu, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $index => $m) {
                $table = $m[0];
                $start = $matches[0][$index][1];
                $open = strpos($contenu, '{', $start);

                if ($open === false) {
                    continue;
                }

                $depth = 0;
                $i = $open;
                $longueur = strlen($contenu);

                while ($i < $longueur) {
                    $char = $contenu[$i];

                    if ($char === '{') {
                        $depth++;
                    } elseif ($char === '}') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }

                    $i++;
                }

                $bloc = substr($contenu, $open, $i - $open + 1);

                // Le tenant_id peut être déclaré par des variantes :
                // foreignId, unsignedBigInteger, nullableUuid, uuid, string...
                if (preg_match('/\btenant_id\b/', $bloc)) {
                    $resultat[] = $table;
                }
            }
        }

        sort($resultat);
        return $resultat;
    }

    /**
     * Détecte les tables qui reçoivent une colonne tenant_id via
     * Schema::table (ajout a posteriori, ex. migrateurs de rattrapage).
     *
     * @return list<string>
     */
    private function tablesTenantAjouteesViaSchemaTable(): array
    {
        $resultat = [];
        $chemin = database_path('migrations');

        foreach (File::files($chemin) as $fichier) {
            $contenu = File::get($fichier->getPathname());

            if (!str_contains($contenu, 'Schema::table(')) {
                continue;
            }

            // Chaque invocation Schema::table('table', ...) qui, dans son bloc,
            // déclare tenant_id (colonne ajoutée par $table->...('tenant_id'...)).
            $pattern = "/Schema::table\('([^']+)'\s*,\s*function\s*\(/";
            preg_match_all($pattern, $contenu, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $index => $m) {
                $table = $m[0];
                $start = $matches[0][$index][1];
                $open = strpos($contenu, '{', $start);

                if ($open === false) {
                    continue;
                }

                $depth = 0;
                $i = $open;
                $longueur = strlen($contenu);

                while ($i < $longueur) {
                    $char = $contenu[$i];

                    if ($char === '{') {
                        $depth++;
                    } elseif ($char === '}') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }

                    $i++;
                }

                $bloc = substr($contenu, $open, $i - $open + 1);

                // La déclaration tient dans : $table->uuid('tenant_id')
                // / foreignId('tenant_id') / unsignedBigInteger('tenant_id').
                if (preg_match("/->\s*(uuid|foreignId|unsignedBigInteger|nullableUuid|string)\s*\(\s*'tenant_id'\s*\)/", $bloc)) {
                    $resultat[] = $table;
                }
            }
        }

        sort($resultat);
        return array_values(array_unique($resultat));
    }

    public function test_linventaire_couvre_exactement_les_tables_avec_tenant_id(): void
    {
        $tablesReelles = array_values(array_unique(array_merge(
            $this->tablesTenantDansMigrations(),
            $this->tablesTenantAjouteesViaSchemaTable()
        )));
        sort($tablesReelles);

        $inventaire = RlsStatusCommand::TABLES_ATTENDUES;
        sort($inventaire);

        $manquantes = array_values(array_diff($tablesReelles, $inventaire));
        $superflues = array_values(array_diff($inventaire, $tablesReelles));

        $this->assertSame(
            [],
            $manquantes,
            "Tables avec colonne tenant_id absentes de TABLES_ATTENDUES :\n  - " .
            implode("\n  - ", $manquantes)
        );

        $this->assertSame(
            [],
            $superflues,
            "Tables dans TABLES_ATTENDUES sans colonne tenant_id dans les migrations :\n  - " .
            implode("\n  - ", $superflues)
        );
    }
}