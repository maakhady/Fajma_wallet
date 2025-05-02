<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur.
     *
     * @param  \App\Http\Requests\RegisterRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        // 1. Validation déjà effectuée par RegisterRequest
        $data = $request->validated();

        // 2. Hash du mot de passe
        $data['password'] = Hash::make($data['password']);

        // 3. Génération d’un code de vérification à 5 chiffres
        $data['verification_code'] = rand(10000, 99999);

        // 4. Création de l'utilisateur en base
        $user = User::create($data);

        // 5. Génération immédiate du token JWT pour l'utilisateur
        $token = JWTAuth::fromUser($user);

        // 6. Réponse JSON avec l'utilisateur, le token et sa durée de vie
        return response()->json([
            'message'    => 'Inscription réussie.',
            'user'       => $user,
            'token'      => $token,
            // JWTAuth::factory()->getTTL() renvoie la durée en minutes configurée (ici 60)
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ], Response::HTTP_CREATED);
    }

    /**
     * Authentification (connexion) d'un utilisateur existant.
     *
     * @param  \App\Http\Requests\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        // 1. Récupération des identifiants validés
        $credentials = $request->validated();

        try {
            // 2. Tentative de création du token à partir des identifiants
            if (! $token = JWTAuth::attempt($credentials)) {
                // 3. Identifiants invalides
                return response()->json([
                    'error' => 'Identifiants incorrects.'
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (JWTException $e) {
            // 4. Erreur lors de la génération du token
            return response()->json([
                'error' => 'Impossible de générer le token.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 5. Réponse JSON avec le token et sa durée de vie
        return response()->json([
            'message'    => 'Connexion réussie.',
            'token'      => $token,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    /**
     * Déconnexion : invalide le token courant.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        // 1. On récupère le token courant et on le révoque
        auth('api')->logout();

        // 2. Réponse JSON de confirmation
        return response()->json([
            'message' => 'Déconnexion réussie.'
        ]);
    }

    /**
     * Rafraîchissement du token expiré (peu importe sa date d’expiration).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        // 1. Génération d’un nouveau token basé sur l’ancien
        $newToken = auth('api')->refresh();

        // 2. Réponse JSON avec le nouveau token et sa durée de vie
        return response()->json([
            'token'      => $newToken,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    /**
     * Récupérer les informations de l’utilisateur authentifié.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        // 1. renvoie l’utilisateur connecté
        return response()->json(auth('api')->user());
    }
}
