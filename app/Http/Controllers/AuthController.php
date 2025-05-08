<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CardController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            // Démarrer une transaction pour assurer l'intégrité
            DB::beginTransaction();

            // 1. Validation déjà effectuée par RegisterRequest
            $data = $request->validated();

            // 2. Hash du mot de passe
            $data['password'] = Hash::make($data['password']);

            // 3. Génération d'un code de vérification à 5 chiffres
            $data['verification_code'] = sprintf("%05d", mt_rand(0, 99999));

            // 4. Stocker l'email de contact
            $data['contact_email'] = $data['email'];

            // 5. Générer un email @fajma.sn pour l'authentification
            $data['email'] = $this->generateUniqueEmail($data['first_name'], $data['last_name']);

            // 6. Gestion de la photo de profil si présente
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = Str::slug($data['first_name'] . '-' . $data['last_name']) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('profile-photos', $filename, 'public');
                $data['profile_photo'] = 'profile-photos/' . $filename;
            }

            // 7. Par défaut, l'utilisateur est actif
            $data['is_active'] = true;

            // 8. Création de l'utilisateur en base
            $user = User::create($data);

            // Création automatique de la carte
            $cardController = new CardController();
            $card = $cardController->createCardForUser($user);

            // Vérifier si la carte a été créée avec succès
            if (!$card) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Erreur lors de la création de la carte'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Tout est OK, on peut valider la transaction
            DB::commit();

            // 9. S'assurer que les headers de réponse sont corrects
            return response()->json([
                'message' => 'Inscription réussie.',
                'user' => $user,
                'card' => $card
            ], Response::HTTP_CREATED)->header('Content-Type', 'application/json');

        } catch (\Exception $e) {
            // S'assurer que la transaction est annulée
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Erreur inscription: ' . $e->getMessage());

            // S'assurer que même en cas d'erreur, on renvoie du JSON
            return response()->json([
                'error' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Génère un email d'identification unique au format prenom.nom@fajma.sn
     * Gère les cas où plusieurs utilisateurs ont le même nom/prénom de façon conviviale
     *
     * @param string $firstName Le prénom de l'utilisateur
     * @param string $lastName Le nom de l'utilisateur
     * @return string L'email d'identification unique généré
     */
    private function generateUniqueEmail(string $firstName, string $lastName): string
    {
        // 1. Normaliser les noms (enlever accents, caractères spéciaux)
        $firstName = $this->normalizeString($firstName);
        $lastName = $this->normalizeString($lastName);

        // 2. Créer le slug de base
        $baseSlug = Str::slug($firstName . '.' . $lastName);

        // 3. Essayer d'abord avec le format standard
        $emailToTry = $baseSlug . '@fajma.sn';
        if (!User::where('email', $emailToTry)->exists()) {
            return $emailToTry;
        }

        // 4. Si l'email existe déjà, essayer avec un suffixe numérique incrémental
        $count = 1;
        do {
            $emailToTry = $baseSlug . $count . '@fajma.sn';
            $count++;
        } while (User::where('email', $emailToTry)->exists() && $count < 100);

        // 5. Si trop de collisions, essayer avec l'initiale du prénom + nom complet
        if ($count >= 100) {
            $firstInitial = Str::substr($firstName, 0, 1);
            $baseSlug = Str::slug($firstInitial . '.' . $lastName);

            $emailToTry = $baseSlug . '@fajma.sn';
            if (!User::where('email', $emailToTry)->exists()) {
                return $emailToTry;
            }

            // 6. Essayer avec l'initiale du prénom + nom + numéro
            $count = 1;
            do {
                $emailToTry = $baseSlug . $count . '@fajma.sn';
                $count++;
            } while (User::where('email', $emailToTry)->exists() && $count < 100);
        }

        // 7. Si encore trop de collisions, utiliser prénom + initiale du nom
        if ($count >= 100) {
            $lastInitial = Str::substr($lastName, 0, 1);
            $baseSlug = Str::slug($firstName . '.' . $lastInitial);

            $emailToTry = $baseSlug . '@fajma.sn';
            if (!User::where('email', $emailToTry)->exists()) {
                return $emailToTry;
            }

            // 8. Essayer avec prénom + initiale du nom + numéro
            $count = 1;
            do {
                $emailToTry = $baseSlug . $count . '@fajma.sn';
                $count++;
            } while (User::where('email', $emailToTry)->exists() && $count < 100);
        }

        // 9. Dernière solution: utiliser l'année en cours + un nombre aléatoire à 2 chiffres
        if ($count >= 100) {
            $year = date('y'); // Année sur 2 chiffres
            $randomNum = sprintf('%02d', mt_rand(1, 99));
            $emailToTry = $firstName . '.' . $lastName . $year . $randomNum . '@fajma.sn';

            // Vérifier que même cette dernière solution est unique
            while (User::where('email', $emailToTry)->exists()) {
                $randomNum = sprintf('%02d', mt_rand(1, 99));
                $emailToTry = $firstName . '.' . $lastName . $year . $randomNum . '@fajma.sn';
            }
        }

        return $emailToTry;
    }

    /**
     * Normalise une chaîne en supprimant les accents et caractères spéciaux
     * sans utiliser l'extension intl
     *
     * @param string $string La chaîne à normaliser
     * @return string La chaîne normalisée
     */
    private function normalizeString(string $string): string
    {
        // Table de correspondance pour les caractères accentués
        $unwanted_chars = [
            // Caractères accentués en minuscules
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'p',
            'ÿ' => 'y',

            // Caractères accentués en majuscules
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'P',

            // Caractères supplémentaires (français, allemand, etc.)
            'ß' => 'ss', 'œ' => 'oe', 'Œ' => 'OE',
        ];

        // Remplacer les caractères accentués
        $string = strtr($string, $unwanted_chars);

        // Supprimer les caractères spéciaux (garder lettres, chiffres et tirets)
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);

        // Convertir en minuscules
        return Str::lower($string);
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
        $email = $credentials['email'];
        $password = $credentials['password'];

        try {
            // 2. Rechercher l'utilisateur par email (dans les deux champs)
            $user = User::where('email', $email)
                        ->orWhere('contact_email', $email)
                        ->first();

            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'error' => 'Identifiants incorrects.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // 3. Vérifier si le compte est actif
            if (!$user->is_active) {
                return response()->json([
                    'error' => 'Ce compte a été désactivé. Veuillez contacter l\'administrateur.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // 4. Obtention du token
            $token = JWTAuth::fromUser($user);

            // 5. Mise à jour de la dernière connexion
            try {
                $user->forceFill(['last_login_at' => now()->format('Y-m-d H:i:s')]);
                $user->save();
            } catch (\Exception $e) {
                // Ignorer l'erreur et continuer
                Log::error('Erreur dernière connexion: ' . $e->getMessage());
            }

            // 6. Réponse JSON avec le token
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
