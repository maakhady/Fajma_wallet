<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

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

