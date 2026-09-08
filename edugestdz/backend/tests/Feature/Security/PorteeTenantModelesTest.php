<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Inventaire de la portée tenant des modèles (Sprint 4 — P1-6).
 *
 * ── Ce que l'audit disait, et pourquoi c'était faux ───────────────────────
 *
 * Le point P1-6 du plan décrivait des « double checks tenant redondants »
 * dans les contrôleurs, à supprimer une fois les tests d'isolation verts.
 * La vérification a montré l'inverse : sur les sept modèles concernés par
 * ces filtres, quatre n'ont **aucun** scope tenant. Le `where('tenant_id',
 * …)` du contrôleur n'était pas une redondance, c'était la seule barrière.
 * Le retirer aurait ouvert une fuite inter-établissements.
 *
 * On a donc renversé l'ordre : rendre l'invariant vrai d'abord, le prouver
 * ici, et ne parler de redondance qu'ensuite.
 *
 * ── Ce que ce test garantit ───────────────────────────────────────────────
 *
 * Tout modèle dont la table porte une colonne `tenant_id` doit enregistrer
 * le global scope `tenant`, sauf s'il figure dans l'une des deux listes
 * ci-dessous. Un nouveau modèle non scopé fait échouer la CI : c'est le
 * seul moyen d'empêcher la dette de se reconstituer silencieusement.
 *
 * Les deux listes sont volontairement distinctes. Confondre « exclu par
 * conception » et « pas encore traité » ferait disparaître la dette dans
 * une liste d'exceptions que plus personne n'interroge.
 */
class PorteeTenantModelesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Modèles délibérément non scopés — la lecture inter-tenant fait partie
     * de leur raison d'être. Toute entrée ici est une décision, pas un oubli.
     */
    private const HORS_PERIMETRE = [
        // La place de marché est publique par construction : un établissement
        // doit voir les offres, profils et avis des autres. Le scope tenant
        // la rendrait vide.
        'AvisMarketplace'        => 'catalogue public inter-établissements',
        'FavoriMarketplace'      => 'catalogue public inter-établissements',
        'ProfilMarketplace'      => 'catalogue public inter-établissements',
        'ReservationMarketplace' => 'catalogue public inter-établissements',
        'OffreCours'             => 'catalogue public inter-établissements',

        // Référentiel des rôles : partagé, et consulté avant même que le
        // tenant ne soit résolu (résolution des permissions à la connexion).
        'Role'                   => 'référentiel partagé, lu avant résolution du tenant',

        // Table de jonction tenant ↔ module, manipulée par la console
        // super-admin, donc précisément hors d'un tenant donné.
        'TenantModule'           => 'administration inter-tenant (super-admin)',

        // L'authentification cherche un compte par e-mail avant qu'un tenant
        // soit résolu — c'est le compte trouvé qui détermine le tenant. Un
        // scope fail-closed rendrait toute connexion impossible. L'isolation
        // des comptes repose donc sur les filtres explicites des contrôleurs
        // et services : ce sont les seuls, ils ne doivent pas être retirés.
        'User'                   => 'recherché par e-mail avant résolution du tenant (connexion)',
    ];

    /**
     * Dette identifiée : ces modèles DEVRAIENT être scopés et ne le sont pas
     * encore. Chaque correction retire une ligne d'ici.
     *
     * Ils ne sont pas corrigés en bloc à dessein : ajouter le trait modifie
     * le comportement à la création (injection du tenant, exception si aucun
     * n'est résolu) et peut casser des chemins d'appel hors requête HTTP —
     * commandes, jobs, seeders. Cela se traite par lots vérifiables.
     */
    private const DETTE = [
        'AlerteSurveillance',
        'CameraConfig',
        'CandidatExamen',
        'SalleExamen',
        'SessionExamen',
        'SurveiillantExamen',
    ];

    public function test_tout_modele_portant_tenant_id_est_scope(): void
    {
        $manquants = [];

        foreach ($this->modeles() as $nom => $modele) {
            $table = $modele->getTable();

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            if (array_key_exists($nom, self::HORS_PERIMETRE) || in_array($nom, self::DETTE, true)) {
                continue;
            }

            if (!array_key_exists('tenant', $modele->getGlobalScopes())) {
                $manquants[] = $nom;
            }
        }

        $this->assertSame(
            [],
            $manquants,
            'Modèles avec une colonne tenant_id mais sans global scope « tenant » : '
            . implode(', ', $manquants)
            . '. Ajouter le trait App\Traits\BelongsToTenant, ou justifier explicitement '
            . 'dans HORS_PERIMETRE si la lecture inter-tenant est voulue.'
        );
    }

    /**
     * Empêche les listes de pourrir : un modèle corrigé doit sortir de la
     * dette. Sans ce contrôle, DETTE gonfle et ne veut plus rien dire.
     */
    public function test_la_dette_ne_contient_que_des_modeles_encore_non_scopes(): void
    {
        $modeles  = $this->modeles();
        $obsoletes = [];

        foreach (self::DETTE as $nom) {
            if (!isset($modeles[$nom])) {
                $obsoletes[] = "{$nom} (modèle introuvable)";
                continue;
            }

            if (array_key_exists('tenant', $modeles[$nom]->getGlobalScopes())) {
                $obsoletes[] = "{$nom} (désormais scopé)";
            }
        }

        $this->assertSame(
            [],
            $obsoletes,
            'Entrées à retirer de DETTE : ' . implode(', ', $obsoletes)
        );
    }

    /**
     * Le trait doit rester fail-closed : sans tenant résolu, un filtre vide,
     * jamais l'absence de filtre. C'est l'hypothèse sur laquelle repose tout
     * le reste de ce test.
     */
    public function test_le_scope_est_fail_closed_sans_tenant_resolu(): void
    {
        config(['tenant.current_id' => null]);

        $sql = \App\Models\Eleve::query()->toSql();

        $this->assertStringContainsString(
            '1 = 0',
            $sql,
            "Sans tenant résolu, la requête doit être vidée (« 1 = 0 »). SQL obtenu : {$sql}"
        );
    }

    /**
     * @return array<string, Model>
     */
    private function modeles(): array
    {
        $modeles = [];

        foreach (glob(app_path('Models/*.php')) as $fichier) {
            $nom    = basename($fichier, '.php');
            $classe = 'App\\Models\\' . $nom;

            if (!class_exists($classe)) {
                continue;
            }

            $reflet = new \ReflectionClass($classe);

            if ($reflet->isAbstract() || !$reflet->isSubclassOf(Model::class)) {
                continue;
            }

            try {
                $modeles[$nom] = $reflet->newInstance();
            } catch (\Throwable) {
                // Un modèle dont le constructeur exige des arguments n'est pas
                // instanciable ici ; il sortira du périmètre de l'inventaire.
                continue;
            }
        }

        return $modeles;
    }
}
