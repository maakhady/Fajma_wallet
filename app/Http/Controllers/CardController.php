<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\User;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\VerifyCardRequest;

/**
 * Contrôleur pour la gestion des cartes de wallet santé
 *
 * Ce contrôleur gère toutes les opérations liées aux cartes virtuelles et physiques
 * des utilisateurs, y compris la création, la consultation, le blocage et la vérification.
 */
class CardController extends Controller
{
    /**
     * Constructeur avec middleware d'authentification
     *
     * Toutes les méthodes nécessitent une authentification sauf verifyAccess qui est publique
     */
    public function __construct()
    {
        if (method_exists($this, 'middleware')) {
            $this->middleware('auth:api');
        }
    }

    /**
 * Récupérer toutes les cartes existantes dans le système
 * Réservé aux administrateurs
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function index()
{
    try {
        // Récupérer l'utilisateur authentifié
        $user = auth('api')->user();

        // Vérifier si l'utilisateur est admin
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent voir toutes les cartes.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Récupérer toutes les cartes avec les utilisateurs et transactions associés
        $cards = Card::with(['user', 'transactions'])->get();

        // Retourner les cartes en réponse
        return response()->json([
            'cards' => $cards
        ]);
    } catch (\Exception $e) {
        // Journaliser l'erreur
        LogFacade::error('Erreur récupération cartes: ' . $e->getMessage());

        // Retourner une erreur
        return response()->json([
            'error' => 'Erreur lors de la récupération des cartes'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Créer automatiquement une carte à l'inscription de l'utilisateur
     * Cette méthode peut être appelée après la création d'un utilisateur
     *
     * @param \App\Models\User $user L'utilisateur pour lequel créer une carte
     * @return \App\Models\Card|null La carte créée ou null en cas d'erreur
     */
    public static function createCardForNewUser(User $user)
    {
        try {
            // Génération d'un numéro de carte unique
            $cardNumber = self::generateUniqueCardNumber();

            // Création de la carte
            $card = new Card();
            $card->card_number = $cardNumber; // Le numéro est généré à la création
            $card->type_card = 'virtual'; // Par défaut virtuelle
            $card->status = 'activated'; // Activée automatiquement
            $card->balance = 0.00; // Solde initial à zéro
            $card->user_id = $user->id;

            // Date d'expiration (par défaut 1 ans)
            $card->expires_at = date('Y-m-d', strtotime('+1 years'));

            $card->save();

            // Journaliser la création
            Log::create([
                'user_id' => $user->id,
                'action' => 'create_card',
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => 'Carte créée automatiquement'
            ]);

            return $card;

        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur création carte automatique: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Méthode d'instance pour créer une carte pour un utilisateur
     * (Pour maintenir la compatibilité avec le code existant)
     *
     * @param \App\Models\User $user L'utilisateur pour lequel créer une carte
     * @return \App\Models\Card|null La carte créée ou null en cas d'erreur
     */
    public function createCardForUser(User $user)
    {
        return self::createCardForNewUser($user);
    }

    /**
     * Récupérer les détails d'une carte spécifique
     *
     * @param int $id Identifiant de la carte
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = auth('api')->user();

            // Récupérer la carte avec ses transactions
            $card = $user->cards()->with('transactions')->findOrFail($id);

            // Journaliser la consultation
            Log::create([
                'user_id' => $user->id,
                'action' => 'view_card',
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => 'Consultation des détails de la carte'
            ]);

            return response()->json([
                'card' => $card
            ]);
        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur détails carte: ' . $e->getMessage());

            return response()->json([
                'error' => 'Carte introuvable ou accès non autorisé'
            ], Response::HTTP_NOT_FOUND);
        }
    }


        /**
     * Récupérer les cartes de l'utilisateur connecté
     * Par défaut, ne retourne que les cartes actives
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserCards(Request $request)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = auth('api')->user();

            // Créer une requête de base pour les cartes
            $cardsQuery = $user->cards();

            // Par défaut, ne montrer que les cartes actives
            // Si show_all est spécifié et est true, montrer toutes les cartes
            $showAll = $request->has('show_all') && $request->show_all === 'true';

            if (!$showAll) {
                $cardsQuery->where('status', 'activated');
            }

            // Récupérer les cartes avec les transactions associées
            $cards = $cardsQuery->with('transactions')->get();

            // Journaliser la consultation
            Log::create([
                'user_id' => $user->id,
                'action' => 'view_user_cards',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Consultation des cartes de l\'utilisateur' . ($showAll ? ' (toutes)' : ' (actives uniquement)')
            ]);

            return response()->json([
                'cards' => $cards
            ]);
        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur récupération cartes utilisateur: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération des cartes de l\'utilisateur'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Récupérer le solde d'une carte (admin uniquement)
     *
     * @param int $id Identifiant de la carte
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBalance($id)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = auth('api')->user();

            // Vérifier si l'utilisateur est admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'error' => 'Accès non autorisé. Cette fonctionnalité est réservée aux administrateurs.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Pour un admin, récupérer la carte sans vérifier le propriétaire
            $card = Card::findOrFail($id);

            // Journaliser la consultation du solde
            Log::create([
                'user_id' => $user->id,
                'action' => 'check_balance_admin',
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => 'Consultation du solde de la carte par un administrateur'
            ]);

            return response()->json([
                'card_number' => $card->card_number,
                'balance' => $card->balance,
                'currency' => 'XOF', // Devise par défaut (FCFA)
                'user_id' => $card->user_id, // Inclure l'ID du propriétaire pour l'admin
                'card_status' => $card->status // Inclure le statut de la carte
            ]);
        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur récupération solde: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération du solde'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Récupérer le solde de la carte de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserCardBalance()
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = auth('api')->user();

            // Récupérer la carte de l'utilisateur
            $card = $user->cards()->first();

            if (!$card) {
                return response()->json([
                    'error' => 'Aucune carte trouvée pour cet utilisateur'
                ], Response::HTTP_NOT_FOUND);
            }

            // Journaliser la consultation du solde
            Log::create([
                'user_id' => $user->id,
                'action' => 'check_balance',
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => 'Consultation du solde de la carte'
            ]);

            return response()->json([
                'card_number' => $card->card_number,
                'balance' => $card->balance,
                'currency' => 'XOF' // Devise par défaut (FCFA)
            ]);
        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur récupération solde: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la récupération du solde'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Vérifier l'accès à une carte via le code de vérification
     * Cette méthode est publique pour permettre aux prestataires de vérifier la carte
     *
     * @param VerifyCardRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyAccess(VerifyCardRequest $request)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Vérifier le code de sécurité
            if ($user->verification_code !== $request->verification_code) {
                // Journaliser la tentative échouée
                Log::create([
                    'user_id' => $user->id,
                    'action' => 'verify_card_failed',
                    'entity_type' => 'user',
                    'entity_id' => $user->id,
                    'description' => 'Tentative de vérification avec code incorrect',
                    'ip_address' => $request->ip()
                ]);

                return response()->json([
                    'error' => 'Code de vérification incorrect'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Récupérer la carte active de l'utilisateur
            $card = $user->cards()->where('status', 'activated')->first();

            if (!$card) {
                return response()->json([
                    'error' => 'Aucune carte active trouvée pour cet utilisateur'
                ], Response::HTTP_NOT_FOUND);
            }

            // Si la vérification réussit, récupérer les informations de base
            $basicInfo = [
                'name' => $user->first_name . ' ' . $user->last_name,
                'card_status' => $card->status,
                'card_balance' => $card->balance,
                'card_number' => $card->card_number
            ];

            // Journaliser la vérification réussie
            Log::create([
                'user_id' => $user->id,
                'action' => 'verify_card_success',
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => 'Vérification réussie de la carte',
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => 'Vérification réussie',
                'user_info' => $basicInfo,
                'health_service_id' => $user->id // ID pour le microservice de santé
            ]);

        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur vérification carte: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la vérification de la carte'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Bloquer la carte de l'utilisateur connecté pour raison de sécurité/fraude
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockUserCardForSecurity(Request $request)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = auth('api')->user();

            // Récupérer la carte de l'utilisateur
            $card = $user->cards()->first();

            if (!$card) {
                return response()->json([
                    'error' => 'Aucune carte trouvée pour cet utilisateur'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($card->status === 'blocked') {
                return response()->json([
                    'message' => 'La carte est déjà bloquée'
                ]);
            }

            // Mise à jour du statut
            $card->status = 'blocked';
            $card->save();

            // Journalisation avec détails
            Log::create([
                'user_id' => $user->id,
                'action' => 'block_card_security',
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => 'Blocage de la carte pour raison de sécurité: ' . ($request->reason ?? 'Non spécifié')
            ]);

            return response()->json([
                'message' => 'Carte bloquée pour raison de sécurité',
                'card' => $card
            ]);
        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur blocage carte sécurité: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors du blocage de la carte'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Bloquer une carte pour diverses raisons (réservé aux administrateurs)
     *
     * @param int $id Identifiant de la carte
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockForAdministrative($id, Request $request)
    {
        // Valider les données entrantes
        $request->validate([
            'block_type' => 'required|in:administrative,request,lost,stolen,suspicious',
            'reason' => 'required|string',
            'create_new' => 'boolean'
        ]);

        try {
            // Vérifier si l'utilisateur est admin
            $user = auth('api')->user();
            if ($user->role !== 'admin') {
                return response()->json([
                    'error' => 'Action non autorisée'
                ], Response::HTTP_FORBIDDEN);
            }

            // Récupérer la carte
            $card = Card::findOrFail($id);

            if ($card->status === 'blocked') {
                return response()->json([
                    'message' => 'La carte est déjà bloquée'
                ]);
            }

            // Récupérer l'utilisateur propriétaire de la carte (en tant qu'objet unique)
            $cardOwner = User::find($card->user_id);

            if (!$cardOwner) {
                return response()->json([
                    'error' => 'Utilisateur propriétaire de la carte introuvable'
                ], Response::HTTP_NOT_FOUND);
            }

            // Mise à jour du statut
            $card->status = 'blocked';
            $card->save();

            // Déterminer l'action et la description en fonction du type de blocage
            $action = '';
            $description = '';
            $message = '';

            switch ($request->block_type) {
                case 'administrative':
                    $action = 'block_card_administrative';
                    $description = 'Blocage administratif de la carte: ' . $request->reason;
                    $message = 'Carte bloquée pour raison administrative';
                    break;

                case 'request':
                    $action = 'block_card_user_request_by_admin';
                    $description = 'Blocage à la demande de l\'utilisateur (effectué par admin): ' . $request->reason;
                    $message = 'Carte bloquée à la demande de l\'utilisateur';
                    break;

                case 'lost':
                    $action = 'card_lost';
                    $description = 'Carte signalée comme perdue: ' . $request->reason;
                    $message = 'Carte signalée comme perdue';
                    break;

                case 'stolen':
                    $action = 'card_stolen';
                    $description = 'Carte signalée comme volée: ' . $request->reason;
                    $message = 'Carte signalée comme volée';
                    break;

                case 'suspicious':
                    $action = 'block_card_suspicious';
                    $description = 'Blocage pour activité suspecte: ' . $request->reason;
                    $message = 'Carte bloquée pour activité suspecte';
                    break;
            }

            // Journalisation avec détails
            Log::create([
                'user_id' => $user->id,
                'action' => $action,
                'entity_type' => 'card',
                'entity_id' => $card->id,
                'description' => $description
            ]);

            // Créer automatiquement une nouvelle carte pour l'utilisateur si demandé
            // et si le type de blocage est 'lost' ou 'stolen'
            if (($request->block_type === 'lost' || $request->block_type === 'stolen') &&
                $request->has('create_new') && $request->create_new) {

                // Utiliser la méthode statique pour créer une nouvelle carte
                $newCard = self::createCardForNewUser($cardOwner);

                // Vérifier si la création a réussi
                if (!$newCard) {
                    return response()->json([
                        'message' => $message,
                        'old_card' => $card,
                        'error' => 'Impossible de créer une nouvelle carte',
                        'owner' => [
                            'id' => $cardOwner->id,
                            'name' => $cardOwner->first_name . ' ' . $cardOwner->last_name,
                            'email' => $cardOwner->email
                        ]
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                return response()->json([
                    'message' => $message . ' et nouvelle carte créée',
                    'old_card' => $card,
                    'new_card' => $newCard,
                    'owner' => [
                        'id' => $cardOwner->id,
                        'name' => $cardOwner->first_name . ' ' . $cardOwner->last_name,
                        'email' => $cardOwner->email
                    ]
                ]);
            }

            return response()->json([
                'message' => $message,
                'card' => $card,
                'owner' => [
                    'id' => $cardOwner->id,
                    'name' => $cardOwner->first_name . ' ' . $cardOwner->last_name,
                    'email' => $cardOwner->email
                ]
            ]);

        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur blocage carte par admin: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors du blocage de la carte: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
 * Débloquer une carte (réservé aux administrateurs)
 *
 * @param int $id Identifiant de la carte
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function unblock($id, Request $request)
{
    try {
        // Récupérer l'utilisateur authentifié
        $user = auth('api')->user();

        // Vérifier si l'utilisateur est admin
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => 'Action non autorisée. Seuls les administrateurs peuvent débloquer les cartes.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Récupérer la carte
        $card = Card::findOrFail($id);

        // Vérifier si la carte est déjà active
        if ($card->status === 'activated') {
            return response()->json([
                'message' => 'La carte est déjà active'
            ]);
        }

        // Récupérer l'utilisateur propriétaire de la carte
        $cardOwner = User::find($card->user_id);

        if (!$cardOwner) {
            return response()->json([
                'error' => 'Propriétaire de la carte introuvable'
            ], Response::HTTP_NOT_FOUND);
        }

        // Mise à jour du statut
        $card->status = 'activated';
        $card->save();

        // Journalisation
        Log::create([
            'user_id' => $user->id,
            'action' => 'unblock_card_by_admin',
            'entity_type' => 'card',
            'entity_id' => $card->id,
            'description' => 'Déblocage administratif de la carte: ' . ($request->reason ?? 'Non spécifié')
        ]);

        return response()->json([
            'message' => 'Carte débloquée avec succès',
            'card' => $card,
            'owner' => [
                'id' => $cardOwner->id,
                'name' => $cardOwner->first_name . ' ' . $cardOwner->last_name,
                'email' => $cardOwner->email
            ]
        ]);
    } catch (\Exception $e) {
        // Journaliser l'erreur
        LogFacade::error('Erreur déblocage carte: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors du déblocage de la carte'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Vérifier si une carte est expirée et la bloquer si nécessaire
     * Cette méthode serait appelée par un job planifié
     *
     * @param int|null $id Identifiant de la carte (optionnel)
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAndBlockExpired($id = null)
    {
        try {
            // Rechercher les cartes expirées
            $query = Card::where('status', '!=', 'blocked')
                ->where('expires_at', '<', date('Y-m-d'));

            // Si un ID est spécifié, filtrer sur cet ID
            if ($id) {
                $query->where('id', $id);
            }

            // Récupérer les cartes expirées
            $expiredCards = $query->get();

            $count = 0;
            foreach ($expiredCards as $card) {
                // Bloquer la carte
                $card->status = 'blocked';
                $card->save();

                // Journalisation
                Log::create([
                    'user_id' => $card->user_id,
                    'action' => 'block_card_expired',
                    'entity_type' => 'card',
                    'entity_id' => $card->id,
                    'description' => 'Carte bloquée pour expiration'
                ]);

                $count++;
            }

            return response()->json([
                'message' => $count . ' carte(s) expirée(s) bloquée(s)'
            ]);
        } catch (\Exception $e) {
            // Journaliser l'erreur
            LogFacade::error('Erreur vérification cartes expirées: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erreur lors de la vérification des cartes expirées'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Générer un numéro de carte unique
     *
     * @return string Numéro de carte généré
     */
    private static function generateUniqueCardNumber()
    {
        $prefix = 'FAJMA'; // Préfixe personnalisable

        do {
            // Génération d'un nombre aléatoire à 6 chiffres
            $randomNumber = '';
            for ($i = 0; $i < 6; $i++) {
                $randomNumber .= mt_rand(0, 9);
            }

            $cardNumber = $prefix . $randomNumber;

            // Vérification de l'unicité
            $exists = Card::where('card_number', $cardNumber)->exists();

        } while ($exists);

        return $cardNumber;
    }
}
