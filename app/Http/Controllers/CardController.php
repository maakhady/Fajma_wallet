<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CardController extends Controller
{
    /**
     * Constructeur avec middleware d'authentification
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except(['verifyAccess']);
    }

    /**
     * Récupérer toutes les cartes de l'utilisateur connecté
     */
    public function index()
    {
        try {
            $user = auth('api')->user();
            $cards = $user->cards()->with('transactions')->get();

            return response()->json([
                'cards' => $cards
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération cartes: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des cartes'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Créer automatiquement une carte à l'inscription de l'utilisateur
     * Cette méthode peut être appelée après la création d'un utilisateur
     */
    public function createCardForUser(User $user)
    {
        try {
            // Génération d'un numéro de carte unique
            $cardNumber = $this->generateUniqueCardNumber();
            
            // Création de la carte
            $card = new Card();
            $card->card_number = $cardNumber;
            $card->type_card = 'virtual'; // Par défaut virtuelle
            $card->status = 'activated'; // Activée automatiquement
            $card->balance = 0.00; // Solde initial à zéro
            $card->user_id = $user->id;
            
            // Date d'expiration (par défaut 3 ans)
            $card->expires_at = date('Y-m-d', strtotime('+3 years'));
            
            $card->save();
            
            return $card;
            
        } catch (\Exception $e) {
            Log::error('Erreur création carte automatique: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer les détails d'une carte spécifique
     */
    public function show($id)
    {
        try {
            $user = auth('api')->user();
            $card = $user->cards()->with('transactions')->findOrFail($id);
            
            return response()->json([
                'card' => $card
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur détails carte: ' . $e->getMessage());
            return response()->json([
                'error' => 'Carte introuvable ou accès non autorisé'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Bloquer une carte
     */
    public function block($id)
    {
        try {
            $user = auth('api')->user();
            $card = $user->cards()->findOrFail($id);
            
            if ($card->status === 'blocked') {
                return response()->json([
                    'message' => 'La carte est déjà bloquée'
                ]);
            }
            
            $card->status = 'blocked';
            $card->save();
            
            return response()->json([
                'message' => 'Carte bloquée avec succès',
                'card' => $card
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur blocage carte: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du blocage de la carte'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifier l'accès à une carte via le code de vérification
     * Cette méthode est publique pour permettre aux prestataires de vérifier la carte
     */
    public function verifyAccess(Request $request)
    {
        $request->validate([
            'card_number' => 'required|exists:cards,card_number',
            'verification_code' => 'required|size:5',
        ]);

        try {
            $card = Card::where('card_number', $request->card_number)->first();
            
            if (!$card) {
                return response()->json([
                    'error' => 'Carte introuvable'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $user = User::find($card->user_id);
            
            if ($user->verification_code !== $request->verification_code) {
                return response()->json([
                    'error' => 'Code de vérification incorrect'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Si la vérification réussit, récupérer les informations de base
            $basicInfo = [
                'name' => $user->first_name . ' ' . $user->last_name,
                'card_status' => $card->status,
                'card_balance' => $card->balance,
                'card_number' => $card->card_number
            ];
            
            return response()->json([
                'message' => 'Vérification réussie',
                'user_info' => $basicInfo,
                // Ajouter un identifiant pour le microservice de santé
                'health_service_id' => $user->id // Cet ID pourra être utilisé par le microservice
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur vérification carte: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la vérification de la carte'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer le solde d'une carte
     */
    public function getBalance($id)
    {
        try {
            $user = auth('api')->user();
            $card = $user->cards()->findOrFail($id);
            
            return response()->json([
                'card_number' => $card->card_number,
                'balance' => $card->balance,
                'currency' => 'XOF' // Devise par défaut (FCFA)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération solde: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération du solde'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Générer un numéro de carte unique
     */
    private function generateUniqueCardNumber()
    {
        $prefix = '5392'; // Préfixe personnalisable
        
        do {
            // Génération d'un nombre aléatoire à 12 chiffres
            $randomNumber = '';
            for ($i = 0; $i < 12; $i++) {
                $randomNumber .= mt_rand(0, 9);
            }
            
            $cardNumber = $prefix . $randomNumber;
            
            // Vérification de l'unicité
            $exists = Card::where('card_number', $cardNumber)->exists();
            
        } while ($exists);
        
        return $cardNumber;
    }
}