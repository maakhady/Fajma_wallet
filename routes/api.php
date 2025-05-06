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