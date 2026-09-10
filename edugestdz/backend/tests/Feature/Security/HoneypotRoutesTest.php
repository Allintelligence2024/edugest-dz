<?php

namespace Tests\Feature\Security;

use App\Services\HoneypotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Garde des routes leurres (Sprint 5 — 5.4).
 *
 * ── Le défaut que ce test existe pour empêcher ────────────────────────────
 *
 * Les chemins des leurres étaient écrits en dur à deux endroits :
 * HoneypotService (22 entrées) et routes/api/honeypot.php (16 routes). Les
 * deux copies avaient divergé. Manquaient à l'appel .env, admin, debug,
 * backup, config et dump — les six chemins que tout scanner tente en
 * premier. Un test vérifiait pourtant « 22 leurres » : il comptait le
 * tableau du service, pas les routes servies. Il mesurait l'intention.
 *
 * D'où la règle appliquée ici : ne pas vérifier ce qui est déclaré, mais
 * interroger le routeur, et frapper les chemins pour de bon.
 */
class HoneypotRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_chemin_configure_est_enregistre_comme_leurre(): void
    {
        $absents = [];

        foreach (config('security.honeypot.routes') as $nom => $chemin) {
            $route = Route::getRoutes()->getByName("honeypot.{$nom}");

            if ($route === null) {
                $absents[] = "{$nom} ({$chemin}) : aucune route nommée honeypot.{$nom}";
                continue;
            }

            $attendu = ltrim('api' . $chemin, '/');

            if ($route->uri() !== $attendu) {
                $absents[] = "{$nom} : route sur « {$route->uri()} », attendu « {$attendu} »";
            }
        }

        $this->assertSame([], $absents, implode(' | ', $absents));
    }

    /**
     * Le leurre doit répondre exactement comme un chemin inexistant. Une
     * réponse distinctive (403, message spécifique, en-tête particulier)
     * apprendrait au scanner qu'il a été repéré, et lui indiquerait du même
     * coup quels chemins éviter.
     */
    public function test_les_leurres_repondent_404_indiscernable(): void
    {
        $anomalies = [];

        foreach (config('security.honeypot.routes') as $nom => $chemin) {
            $reponse = $this->getJson('/api' . $chemin);

            if ($reponse->status() !== 404) {
                $anomalies[] = "{$nom} → HTTP {$reponse->status()}";
                continue;
            }

            if ($reponse->json('message') !== 'Not Found.') {
                $anomalies[] = "{$nom} → corps « " . json_encode($reponse->json()) . ' »';
            }
        }

        $this->assertSame([], $anomalies, implode(' | ', $anomalies));
    }

    /**
     * Les six chemins qui manquaient. Nommés explicitement : une régression
     * sur eux doit se lire dans le nom du test en échec, pas se diluer dans
     * une boucle.
     */
    public function test_les_cibles_favorites_des_scanners_sont_couvertes(): void
    {
        foreach (['/v1/.env', '/v1/admin', '/v1/debug', '/v1/backup', '/v1/config', '/v1/dump'] as $chemin) {
            $this->assertContains(
                $chemin,
                array_values(config('security.honeypot.routes')),
                "Chemin de reconnaissance courant non couvert : {$chemin}"
            );

            $this->getJson('/api' . $chemin)->assertStatus(404);
        }
    }

    /**
     * Le service et le routeur doivent rester d'accord — c'est exactement le
     * point sur lequel ils avaient silencieusement divergé.
     */
    public function test_le_service_expose_la_meme_liste_que_le_routeur(): void
    {
        $duService = app(HoneypotService::class)->getRoutesLeurres();

        $duRouteur = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'honeypot.'))
            ->map(fn ($route) => '/' . $route->uri())
            ->values()
            ->all();

        sort($duService);
        sort($duRouteur);

        $this->assertSame($duRouteur, $duService);
    }

    /**
     * Un leurre enregistré après coup ne doit jamais capturer une route
     * métier. La vérification porte sur le nom : si un chemin de leurre est
     * servi par autre chose qu'un `honeypot.*`, c'est une collision.
     */
    public function test_aucun_leurre_ne_masque_une_route_metier(): void
    {
        $collisions = [];

        foreach (config('security.honeypot.routes') as $nom => $chemin) {
            $uri = ltrim('api' . $chemin, '/');

            $servantes = collect(Route::getRoutes()->getRoutes())
                ->filter(fn ($route) => $route->uri() === $uri)
                ->map(fn ($route) => (string) $route->getName())
                ->all();

            $metier = array_filter($servantes, fn ($n) => ! str_starts_with($n, 'honeypot.'));

            if ($metier !== []) {
                $collisions[] = "{$chemin} également servi par : " . implode(', ', $metier);
            }
        }

        $this->assertSame([], $collisions, implode(' | ', $collisions));
    }

    /**
     * L'interrupteur doit réellement couper : une bascule de configuration
     * qui n'a aucun effet est pire qu'absente, elle donne l'illusion du
     * contrôle.
     */
    public function test_le_drapeau_actif_desactive_bien_les_leurres(): void
    {
        config(['security.honeypot.actif' => false]);

        $origine = app('router');
        $isole   = new \Illuminate\Routing\Router(app('events'), app());

        // Le fichier de routes passe par la façade : il faut donc lui
        // substituer un routeur vierge pour observer ce qu'il enregistre,
        // puis rendre l'original — sans quoi les tests suivants hériteraient
        // d'un routeur vide.
        try {
            Route::swap($isole);
            require base_path('routes/api/honeypot.php');
        } finally {
            Route::swap($origine);
        }

        $leurres = collect($isole->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'honeypot.'));

        $this->assertCount(0, $leurres, 'Leurres enregistrés malgré security.honeypot.actif = false');
    }
}
