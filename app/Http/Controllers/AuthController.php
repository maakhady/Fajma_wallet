<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth; // Importation manquante
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;

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
        try {
            // 1. Validation déjà effectuée par RegisterRequest
            $data = $request->validated();

            // 2. Hash du mot de passe
            $data['password'] = Hash::make($data['password']);

            // 3. Génération d'un code de vérification à 5 chiffres
            $data['verification_code'] = sprintf("%05d", mt_rand(0, 99999));

            // 4. Gestion de la photo de profil si présente
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = Str::slug($data['first_name'] . '-' . $data['last_name']) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('profile-photos', $filename, 'public');
                $data['profile_photo'] = 'profile-photos/' . $filename;
            }

            // 5. Par défaut, l'utilisateur est actif
            $data['is_active'] = true;

            // 6. Création de l'utilisateur en base
            $user = User::create($data);

            // 7. Réponse JSON avec l'utilisateur (sans token)
            return response()->json([
                'message' => 'Inscription réussie.',
                'user' => $user
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            Log::error('Erreur inscription: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
            // 2. Tentative d'authentification avec le garde JWT
            if (!auth('api')->attempt($credentials)) {
                return response()->json([
                    'error' => 'Identifiants incorrects.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // 3. Récupération de l'utilisateur
            $user = auth('api')->user();
            
            // 4. Vérifier si le compte est actif
            if (!$user->is_active) {
                auth('api')->logout();
                return response()->json([
                    'error' => 'Ce compte a été désactivé. Veuillez contacter l\'administrateur.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // 5. Obtention du token
            $token = auth('api')->tokenById($user->id);
            
            // 6. Mise à jour de la dernière connexion sans utiliser Carbon
            try {
                $user->forceFill(['last_login_at' => now()->format('Y-m-d H:i:s')]);
                $user->save();
            } catch (\Exception $e) {
                // Ignorer l'erreur et continuer
                Log::error('Erreur dernière connexion: ' . $e->getMessage());
            }
            
            // 7. Réponse JSON avec le token
            return response()->json([
                'message' => 'Connexion réussie.',
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 3600, // 1 heure en secondes
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur login: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la connexion: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Déconnexion : invalide le token courant.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // 1. On récupère le token courant et on le révoque
            auth('api')->logout();
            
            // 2. Réponse JSON de confirmation
            return response()->json([
                'message' => 'Déconnexion réussie.'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Erreur lors de la déconnexion.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Rafraîchissement du token expiré (peu importe sa date d'expiration).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            // 1. Génération d'un nouveau token basé sur l'ancien
            $newToken = auth('api')->refresh();
            
            // 2. Réponse JSON avec le nouveau token et sa durée de vie
            return response()->json([
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => 3600, // 1 heure en secondes
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Impossible de rafraîchir le token. Veuillez vous reconnecter.'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Récupérer les informations de l'utilisateur authentifié.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            // 1. renvoie l'utilisateur connecté avec ses relations
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Chargement des relations utiles
            $user->load(['cards']);
            
            return response()->json([
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la récupération des informations utilisateur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Mettre à jour le code de vérification d'un utilisateur.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateVerificationCode()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Générer un nouveau code de vérification
            $newCode = sprintf("%05d", mt_rand(0, 99999));
            $user->forceFill(['verification_code' => $newCode]);
            $user->save();
            
            // Vous pourriez envoyer ce code par SMS/email ici
            
            return response()->json([
                'message' => 'Code de vérification régénéré avec succès.',
                'verification_code' => $user->verification_code
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la régénération du code de vérification: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}