<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuthCompteService;
use App\Services\AuthSessionService;
use Illuminate\Http\{Request, JsonResponse};
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function __construct(
        private AuthSessionService $session,
        private AuthCompteService $compte,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="Connexion utilisateur",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email",    type="string", format="email", example="admin@ecole-oran.dz"),
     *             @OA\Property(property="password", type="string", format="password", example="secret")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token",      type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(property="user",       type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Identifiants invalides", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $result   = $this->session->login($request);
        $response = response()->json($result['payload'], $result['status']);

        if (!isset($result['cookie'])) {
            return $response;
        }

        /** @var Cookie $cookie */
        $cookie = $result['cookie'];

        return $response->withCookie($cookie);
    }

    public function complete2fa(Request $request): JsonResponse
    {
        $result   = $this->session->complete2fa($request);
        $response = response()->json($result['payload'], $result['status']);

        if (!isset($result['cookie'])) {
            return $response;
        }

        /** @var Cookie $cookie */
        $cookie = $result['cookie'];

        return $response->withCookie($cookie);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Déconnexion (invalide le token JWT)",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Déconnecté", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=401, description="Non authentifié",  @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $result   = $this->session->logout($request);
        $response = response()->json($result['payload'], $result['status']);

        /** @var Cookie $cookie */
        $cookie = $result['cookie'];

        return $response->withCookie($cookie);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     summary="Rafraîchir le token JWT",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Token rafraîchi", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=401, description="Token invalide",  @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        $result   = $this->session->refresh($request);
        $response = response()->json($result['payload'], $result['status']);

        if (!isset($result['cookie'])) {
            return $response;
        }

        /** @var Cookie $cookie */
        $cookie = $result['cookie'];

        return $response->withCookie($cookie);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="Profil de l'utilisateur connecté",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Profil utilisateur", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=401, description="Non authentifié",    @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function me(): JsonResponse
    {
        $result = $this->compte->me();

        return response()->json($result['payload'], $result['status']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $result = $this->compte->changePassword($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $result = $this->compte->updateProfile($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $result = $this->compte->forgotPassword($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $result = $this->compte->resetPassword($request);

        return response()->json($result['payload'], $result['status']);
    }
}
