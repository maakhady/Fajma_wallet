<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes for Authentification
|--------------------------------------------------------------------------
*/



// Routes d'authentification publiques (sans authentification)
Route::prefix('auth')->group(function () {
    // Inscription d'un nouvel utilisateur
    Route::post('register', [AuthController::class, 'register']);

    // Connexion d'un utilisateur existant
    Route::post('login', [AuthController::class, 'login']);
});

// Routes d'authentification protégées (nécessitent une authentification JWT)
Route::middleware('auth:api')->prefix('auth')->group(function () {
    // Déconnexion (révocation du token)
    Route::post('logout', [AuthController::class, 'logout']);

    // Rafraîchissement du token expiré
    Route::post('refresh', [AuthController::class, 'refresh']);

    // Récupération des informations de l'utilisateur connecté
    Route::get('me', [AuthController::class, 'me']);

    // Régénération du code de vérification
    Route::post('regenerate-code', [AuthController::class, 'regenerateVerificationCode']);
});


/*
|--------------------------------------------------------------------------
| API Routes for Cards
|--------------------------------------------------------------------------
*/


//CARTE
use App\Http\Controllers\CardController;

// Routes pour la gestion des cartes (protégées par auth:api)
Route::middleware('auth:api')->prefix('cards')->group(function () {
    // Récupérer toutes les cartes de l'utilisateur connecté
    Route::get('/', [CardController::class, 'index']);
    // pour récupérer la carte de l'utilisateur connecté
    Route::get('/user', [CardController::class, 'getUserCards']);
     // Vérifier l'accès à une carte via le code de vérification
     Route::post('/verifycode', [CardController::class, 'verifyAccess']);
     //Pour connaitre son solde
     Route::get('/balance', [CardController::class, 'getUserCardBalance']); // route pour le solde

    // Bloquer une carte pour des raisons de sécurité/fraude par l'utilisateur
    Route::post('/block-security', [CardController::class, 'blockUserCardForSecurity']);

    // Récupérer les détails d'une carte spécifique
    Route::get('/{card}', [CardController::class, 'show']);

    // pour récupérer la carte de l'utilisateur connecté
    // Route::get('/user', [CardController::class, 'getUserCards']);

    // Récupérer le solde d'une carte specifique
    Route::get('/{card}/balance', [CardController::class, 'getBalance']);



    // Routes de blocage pour différentes raisons
    Route::post('/{card}/block-administrative', [CardController::class, 'blockForAdministrative']);
    Route::post('/{card}/block-suspicious', [CardController::class, 'blockForSuspiciousActivity']);

    // Débloquer une carte
    Route::post('/{card}/unblock', [CardController::class, 'unblock']);
});

/*
|--------------------------------------------------------------------------
| API Routes for Users
|--------------------------------------------------------------------------
*/


// Routes pour la gestion des utilisateurs (protégées par auth:api)
use App\Http\Controllers\UserController;
Route::group(['middleware' => ['auth:api'], 'prefix' => 'users'], function () {
    // Routes accessibles par tous les utilisateurs authentifiés
    Route::put('/profile', [UserController::class, 'updateProfile']); // Mettre cette route en premier
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::get('/archived', [UserController::class, 'trashed']); // Liste des utilisateurs archivés


    // Routes accessibles uniquement par l'admin
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::patch('/{id}/toggle-active', [UserController::class, 'toggleActive']);
    Route::patch('/{id}/change-role', [UserController::class, 'changeRole']);
    Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);

    // Routes standard
    Route::delete('/{id}', [UserController::class, 'destroy']); // Archiver un utilisateur (soft delete)

    // Routes pour les utilisateurs archivés
    Route::get('/archived', [UserController::class, 'trashed']); // Liste des utilisateurs archivés
    Route::post('/{id}/restore', [UserController::class, 'restore']); // Restaurer un utilisateur archivé
    Route::get('/{id}/history', [UserController::class, 'userHistory']); // Voir l'historique complet d'un utilisateur
});


/*
|--------------------------------------------------------------------------
| API Routes for Payment Types
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\PaymentTypeController;

// Routes pour la gestion des types de paiement (protégées par auth:api)
// Routes pour les types de paiement
Route::prefix('payment-types')->group(function () {
    // Routes publiques (sans authentification requise)
    Route::get('/', [PaymentTypeController::class, 'index']);
    Route::get('/{id}', [PaymentTypeController::class, 'show'])->where('id', '[0-9]+');

    // Routes nécessitant une authentification
    // (le contrôleur gère déjà la vérification du rôle admin)
    Route::middleware('auth:api')->group(function () {
        // Opérations administratives - segments fixes d'abord
        Route::get('/admin/all', [PaymentTypeController::class, 'indexAdmin']);
        Route::post('/configure-wave', [PaymentTypeController::class, 'configureWave']); //a tester avec le webhook.wave
        Route::post('/configure-orange-money', [PaymentTypeController::class, 'configureOrangeMoney']); //a tester avec le webhook.orange_money
        Route::post('/', [PaymentTypeController::class, 'store']);

        // Routes avec paramètres
        Route::put('/{id}', [PaymentTypeController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [PaymentTypeController::class, 'destroy'])->where('id', '[0-9]+'); // Supprimer un type de paiement (soft delete)
        Route::get('/trashed', [PaymentTypeController::class, 'trashed']); // recupere tous les types de paiement supprimés
        Route::post('/{id}/restore', [PaymentTypeController::class, 'restore'])->where('id', '[0-9]+'); // restaurer un type de paiement supprimé
        Route::delete('/{id}/forcedelete', [PaymentTypeController::class, 'forceDelete'])->where('id', '[0-9]+'); // supprimer définitivement un type de paiement
        Route::post('/{id}/activate', [PaymentTypeController::class, 'activate'])->where('id', '[0-9]+');
        Route::post('/{id}/deactivate', [PaymentTypeController::class, 'deactivate'])->where('id', '[0-9]+');
        Route::post('/{id}/toggle-status', [PaymentTypeController::class, 'toggleStatus'])->where('id', '[0-9]+');
        Route::put('/{id}/config', [PaymentTypeController::class, 'updateConfig'])->where('id', '[0-9]+');
    });
});


// Routes pour les webhooks de paiement (sans authentification)
// Route::post('/webhooks/wave', [WebhookController::class, 'handleWave'])->name('webhooks.wave');
// Route::post('/webhooks/orange-money', [WebhookController::class, 'handleOrangeMoney'])->name('webhooks.orange_money');
