<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserByAdminRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\User;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Liste de tous les utilisateurs (Admin uniquement)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Paramètres de pagination et filtres
            $perPage = $request->input('per_page', 10);
            $role = $request->input('role');
            $search = $request->input('search');
            $isActive = $request->input('is_active');

            // Construire la requête
            $query = User::query();

            // Exclure l'utilisateur actuellement connecté de la liste
            $query->where('id', '!=', $currentUser->id);


            // Appliquer les filtres si présents
            if ($role) {
                $query->where('role', $role);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', '%' . $search . '%')
                      ->orWhere('last_name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('contact_email', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

            if ($isActive !== null) {
                $query->where('is_active', $isActive == 'true' ? true : false);
            }

            // Ordonner et paginer les résultats
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'list_users',
                'entity_type' => 'user',
                'entity_id' => null,
                'description' => 'Consultation de la liste des utilisateurs avec filtres: ' .
                    ($role ? 'rôle=' . $role . ', ' : '') .
                    ($search ? 'recherche=' . $search . ', ' : '') .
                    ($isActive !== null ? 'actif=' . $isActive : '')
            ]);

            return response()->json([
                'users' => $users,
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage()
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération des utilisateurs: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher un utilisateur spécifique (Admin uniquement)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::with(['cards', 'transactions', 'paymentMeans'])->findOrFail($id);

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'view_user',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Consultation du profil utilisateur de ' . $user->first_name . ' ' . $user->last_name
            ]);

            return response()->json([
                'user' => $user
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la récupération de l\'utilisateur: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération de l\'utilisateur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour un utilisateur par un administrateur
     *
     * @param \App\Http\Requests\UpdateUserByAdminRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUserByAdminRequest $request, $id)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::findOrFail($id);
            $data = $request->validated();
            $changedFields = [];

            // Si l'email d'authentification est modifié, vérifier qu'il se termine par @fajma.sn
            if (isset($data['email']) && $data['email'] !== $user->email) {
                if (!Str::endsWith($data['email'], '@fajma.sn')) {
                    return response()->json([
                        'error' => 'L\'email d\'authentification doit se terminer par @fajma.sn.'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $changedFields[] = 'email';
            }

            // Enregistrer les champs modifiés pour la journalisation
            foreach ($data as $field => $value) {
                if ($field !== 'password' && isset($user->$field) && $user->$field !== $value) {
                    $changedFields[] = $field;
                }
            }

            // Si le mot de passe est fourni, le hasher
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                $changedFields[] = 'password';
            }

            // Gestion de la photo de profil
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = Str::slug($user->first_name . '-' . $user->last_name) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('profile-photos', $filename, 'public');
                $data['profile_photo'] = 'profile-photos/' . $filename;
                $changedFields[] = 'profile_photo';
            }

            // Mise à jour des données
            $user->update($data);

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'update_user_by_admin',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Mise à jour du profil utilisateur par administrateur. Champs modifiés: ' . implode(', ', $changedFields)
            ]);

            return response()->json([
                'message' => 'Utilisateur mis à jour avec succès.',
                'user' => $user
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activer/désactiver un utilisateur (Admin uniquement)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($id)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::findOrFail($id);

            // Un admin ne peut pas se désactiver lui-même
            if ($user->id === $currentUser->id) {
                return response()->json([
                    'error' => 'Vous ne pouvez pas modifier votre propre statut d\'activation.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Inverser le statut
            $user->is_active = !$user->is_active;
            $user->save();

            $status = $user->is_active ? 'activé' : 'désactivé';

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'toggle_user_status',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Utilisateur ' . $status . ' par administrateur'
            ]);

            return response()->json([
                'message' => 'Utilisateur ' . $status . ' avec succès.',
                'user' => $user
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la modification du statut: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la modification du statut: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Changer le rôle d'un utilisateur (Admin uniquement)
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeRole(Request $request, $id)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Validation
            $request->validate([
                'role' => 'required|in:patient,admin,medecin,prestataire'
            ]);

            $user = User::findOrFail($id);

            // Un admin ne peut pas changer son propre rôle
            if ($user->id === $currentUser->id) {
                return response()->json([
                    'error' => 'Vous ne pouvez pas modifier votre propre rôle.'
                ], Response::HTTP_FORBIDDEN);
            }

            $oldRole = $user->role;

            // Changer le rôle
            $user->role = $request->role;
            $user->save();

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'change_user_role',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Rôle de l\'utilisateur modifié de ' . $oldRole . ' à ' . $user->role . ' par administrateur'
            ]);

            return response()->json([
                'message' => 'Rôle de l\'utilisateur modifié avec succès.',
                'user' => $user
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors du changement de rôle: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du changement de rôle: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Réinitialiser le mot de passe d'un utilisateur
     * Les utilisateurs peuvent réinitialiser leur propre mot de passe
     * Les administrateurs peuvent réinitialiser le mot de passe de n'importe quel utilisateur
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $currentUser = auth('api')->user();
            if (!$currentUser) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Récupérer l'utilisateur cible
            $user = User::findOrFail($id);

            // Vérifier si l'utilisateur actuel peut réinitialiser le mot de passe
            // Soit c'est son propre compte, soit c'est un administrateur
            if ($currentUser->id !== $user->id && !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Vous ne pouvez pas réinitialiser le mot de passe d\'un autre utilisateur.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Validation différente selon qui fait la demande
            if ($currentUser->id === $user->id) {
                // L'utilisateur réinitialise son propre mot de passe
                $request->validate([
                    'current_password' => 'required|string',
                    'password' => 'required|string|min:8|confirmed'
                ]);

                // Vérifier que l'ancien mot de passe est correct
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'error' => 'Le mot de passe actuel est incorrect.'
                    ], Response::HTTP_UNAUTHORIZED);
                }
            } else {
                // Un admin réinitialise le mot de passe d'un autre utilisateur
                $request->validate([
                    'password' => 'required|string|min:8|confirmed'
                ]);
            }

            // Réinitialiser le mot de passe
            $user->password = Hash::make($request->password);

            // Générer un nouveau code de vérification
            $user->verification_code = sprintf("%05d", mt_rand(0, 99999));
            $user->save();

            // Journaliser l'action
            $action = ($currentUser->id === $user->id) ? 'reset_own_password' : 'reset_user_password';
            $description = ($currentUser->id === $user->id)
                ? 'Réinitialisation du mot de passe par l\'utilisateur lui-même'
                : 'Réinitialisation du mot de passe utilisateur par administrateur';

            Log::create([
                'user_id' => $currentUser->id,
                'action' => $action,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => $description
            ]);

            // TODO: Envoyer un email à l'utilisateur avec son nouveau mot de passe (si réinitialisé par admin)

            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès.',
                'verification_code' => $user->verification_code
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mise à jour du profil par l'utilisateur connecté
     *
     * @param \App\Http\Requests\UpdateUserProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(UpdateUserProfileRequest $request)
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $data = $request->validated();
            $changedFields = [];

            // Enregistrer les champs modifiés pour la journalisation
            foreach ($data as $field => $value) {
                if (isset($user->$field) && $user->$field !== $value) {
                    $changedFields[] = $field;
                }
            }

            // L'utilisateur ne peut pas modifier son email d'authentification
            if (isset($data['email'])) {
                unset($data['email']);
            }

            // L'utilisateur ne peut pas modifier son rôle
            if (isset($data['role'])) {
                unset($data['role']);
            }

            // L'utilisateur ne peut pas modifier son statut d'activation
            if (isset($data['is_active'])) {
                unset($data['is_active']);
            }

            // Gestion de la photo de profil
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = Str::slug($user->first_name . '-' . $user->last_name) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('profile-photos', $filename, 'public');
                $data['profile_photo'] = 'profile-photos/' . $filename;
                $changedFields[] = 'profile_photo';
            }

            // Mise à jour des données
            $user->update($data);

            // Journaliser l'action
            Log::create([
                'user_id' => $user->id,
                'action' => 'update_profile',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Mise à jour du profil par l\'utilisateur. Champs modifiés: ' . implode(', ', $changedFields)
            ]);

            return response()->json([
                'message' => 'Profil mis à jour avec succès.',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors de la mise à jour du profil: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour du profil: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Changement de mot de passe par l'utilisateur connecté
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Validation
            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed'
            ]);

            // Vérifier que l'ancien mot de passe est correct
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'error' => 'Le mot de passe actuel est incorrect.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Mise à jour du mot de passe
            $user->password = Hash::make($request->password);
            $user->save();

            // Journaliser l'action
            Log::create([
                'user_id' => $user->id,
                'action' => 'change_password',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Changement de mot de passe par l\'utilisateur'
            ]);

            return response()->json([
                'message' => 'Mot de passe modifié avec succès.'
            ]);
        } catch (\Exception $e) {
            LogFacade::error('Erreur lors du changement de mot de passe: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du changement de mot de passe: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Archiver un utilisateur (Soft Delete) - Admin uniquement
     * L'utilisateur sera marqué comme supprimé mais toutes ses données/actions restent dans le système
     *on marque supprimer mais en réalité c'est archivé
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
{
    try {
        // Vérifier que l'utilisateur est admin
        $currentUser = auth('api')->user();
        if (!$currentUser || !$currentUser->hasRole('admin')) {
            return response()->json([
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent supprimer des utilisateurs.'
            ], Response::HTTP_FORBIDDEN);
        }

        $user = User::findOrFail($id);

        // Un admin ne peut pas se supprimer lui-même
        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => 'Vous ne pouvez pas supprimer votre propre compte.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Information sur l'utilisateur pour la réponse et le log
        $userInfo = [
            'id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'contact_email' => $user->contact_email,
            'role' => $user->role
        ];

        // Soft delete l'utilisateur
        $user->delete();

        // Journaliser l'action
        Log::create([
            'user_id' => $currentUser->id,
            'action' => 'delete_user',
            'entity_type' => 'user',
            'entity_id' => $userInfo['id'],
            'description' => 'Suppression de l\'utilisateur ' . $userInfo['name'] . ' (' . $userInfo['email'] . ') par administrateur'
        ]);

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès. Ses données historiques restent accessibles.',
            'user_details' => $userInfo
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'error' => 'Utilisateur non trouvé.'
        ], Response::HTTP_NOT_FOUND);
    } catch (\Exception $e) {
        Log::error('Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Restaurer un utilisateur archivé - Admin uniquement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent restaurer des utilisateurs.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Trouver l'utilisateur archivé
            $user = User::onlyTrashed()->findOrFail($id);

            // Restaurer l'utilisateur
            $user->restore();

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'restore_user',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Restauration de l\'utilisateur ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ') par administrateur'
            ]);

            return response()->json([
                'message' => 'Utilisateur restauré avec succès.',
                'user' => $user
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur archivé non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la restauration de l\'utilisateur: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la restauration de l\'utilisateur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Liste des utilisateurs archivés - Admin uniquement
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trashed(Request $request)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent voir les utilisateurs archivés.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Paramètres de pagination
            $perPage = $request->input('per_page', 15);

            // Récupérer uniquement les utilisateurs archivés
            $trashedUsers = User::onlyTrashed()
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'view_archived_users',
                'entity_type' => 'user',
                'entity_id' => null,
                'description' => 'Consultation de la liste des utilisateurs archivés'
            ]);

            return response()->json([
                'archived_users' => $trashedUsers,
                'total' => $trashedUsers->total(),
                'current_page' => $trashedUsers->currentPage(),
                'per_page' => $trashedUsers->perPage(),
                'last_page' => $trashedUsers->lastPage()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des utilisateurs archivés: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération des utilisateurs archivés: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Voir toutes les actions d'un utilisateur (même archivé) - Admin uniquement
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function userHistory($id)
    {
        try {
            // Vérifier que l'utilisateur est admin
            $currentUser = auth('api')->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                return response()->json([
                    'error' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder à cette ressource.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Trouver l'utilisateur (même s'il est archivé)
            $user = User::withTrashed()->findOrFail($id);

            // Récupérer les données historiques
            $cards = $user->cards()->get();
            $transactions = $user->transactions()->get();
            $logs = $user->logs()->get();
            $paymentMeans = $user->paymentMeans()->get();

            // Journaliser l'action
            Log::create([
                'user_id' => $currentUser->id,
                'action' => 'view_user_history',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'description' => 'Consultation de l\'historique complet de l\'utilisateur ' . $user->first_name . ' ' . $user->last_name
            ]);

            return response()->json([
                'user' => $user,
                'is_archived' => $user->trashed(),
                'cards' => $cards,
                'transactions' => $transactions,
                'payment_means' => $paymentMeans,
                'logs' => $logs
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'historique utilisateur: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la récupération de l\'historique utilisateur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

/**
 * Supprimer définitivement un utilisateur
 *
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function forceDelete($id)
{
    try {
        // Vérifier que l'utilisateur est admin
        $currentUser = auth('api')->user();
        if (!$currentUser || !$currentUser->hasRole('admin')) {
            return response()->json([
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent supprimer définitivement des utilisateurs.'
            ], Response::HTTP_FORBIDDEN);
        }

        $user = User::withTrashed()->findOrFail($id);

        // Un admin ne peut pas se supprimer lui-même
        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => 'Vous ne pouvez pas supprimer définitivement votre propre compte.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Vérification supplémentaire : éviter de supprimer le dernier admin
        if ($user->hasRole('admin')) {
            $adminCount = User::whereHas('roles', function($query) {
                $query->where('name', 'admin');
            })->count();
            
            if ($adminCount <= 1) {
                return response()->json([
                    'error' => 'Impossible de supprimer le dernier administrateur du système.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Si l'utilisateur a des transactions ou cartes, on l'anonymise au lieu de le supprimer
        $hasTransactions = $user->transactions()->count() > 0;
        $hasCards = $user->cards()->count() > 0; // Changé de healthCards() à cards()

        if ($hasTransactions || $hasCards) {
            return $this->anonymizeUser($user, $hasTransactions, $hasCards);
        }

        // Pas de données liées : suppression complète possible
        if ($user->profile_photo) {
            Storage::delete($user->profile_photo);
        }

        if ($user->avatar) {
            Storage::delete($user->avatar);
        }

        $this->logAction('force_delete_user', $user->id, 
            'Suppression définitive de l\'utilisateur: ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ')');

        $user->forceDelete();

        return response()->json([
            'message' => 'Utilisateur supprimé définitivement avec succès'
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'error' => 'Utilisateur non trouvé (même dans les archives).'
        ], Response::HTTP_NOT_FOUND);
    } catch (\Exception $e) {
        LogFacade::error('Erreur suppression définitive utilisateur: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de la suppression définitive de l\'utilisateur: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
/**
 * Apres suppression pour eviter de perdre les données de l'utilisateur 
 * supprimer definitive je l'anomyse dans le systeme pour ne pas perdre sa traçabilité
 */

private function anonymizeUser($user, $hasTransactions, $hasCards)
{
    // Sauvegarder les infos pour le log
    $originalEmail = $user->email;
    $originalName = $user->first_name . ' ' . $user->last_name;

    // Anonymiser les données personnelles
    // $user->first_name = 'Utilisateur';
    // $user->last_name = 'Supprimé';
    $anonymizedEmail = 'deleted_' . $user->id . '_' . uniqid() . '@anonymized.local';
    $user->email = $anonymizedEmail;
    $user->contact_email = $anonymizedEmail;
    $user->phone = null;
    
    // Supprimer la photo de profil
    if ($user->profile_photo) {
        Storage::delete($user->profile_photo);
        $user->profile_photo = null;
    }

    // S'assurer que le soft delete est appliqué
    if (!$user->trashed()) {
        $user->deleted_at = now();
    }

    $user->save();

    // Préparer le message de retour
    $keptData = [];
    if ($hasTransactions) $keptData[] = 'transactions';
    if ($hasCards) $keptData[] = 'cartes';
    $keptDataStr = implode(' et ', $keptData);

    // Remplacer logAction par LogFacade
    LogFacade::info("Anonymisation de l'utilisateur", [
        'user_id' => $user->id,
        'original_name' => $originalName,
        'original_email' => $originalEmail,
        'kept_data' => $keptDataStr,
        'action' => 'anonymize_user'
    ]);

    return response()->json([
        'message' => "Utilisateur Supprimé  avec succès. Les $keptDataStr ont été conservées pour la traçabilité.",
        'action' => 'anonymized'
    ]);
}

/**
 * Récupère la liste de tous les utilisateurs anonymisés
 */
public function getAnonymizedUsers()
{
    try {
        // Vérifier que l'utilisateur est admin
        $currentUser = auth('api')->user();
        if (!$currentUser || !$currentUser->hasRole('admin')) {
            return response()->json([
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent consulter ces données.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Récupérer les utilisateurs anonymisés (soft deleted avec email anonymisé)
        $anonymizedUsers = User::onlyTrashed()
            ->where('email', 'LIKE', 'deleted_%@anonymized.local')
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'role',
                'deleted_at',
                'updated_at'
            ])
            ->withCount(['transactions', 'cards']) // Compte les relations
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'Liste des utilisateurs anonymisés récupérée avec succès',
            'data' => $anonymizedUsers
        ]);

    } catch (\Exception $e) {
        LogFacade::error('Erreur récupération utilisateurs anonymisés: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de la récupération des utilisateurs anonymisés'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * Récupère les détails complets d'un utilisateur anonymisé
 * avec toutes ses transactions et cartes
 */
public function getAnonymizedUserDetails($id)
{
    try {
        // Vérifier que l'utilisateur est admin
        $currentUser = auth('api')->user();
        if (!$currentUser || !$currentUser->hasRole('admin')) {
            return response()->json([
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent consulter ces données.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Récupérer l'utilisateur anonymisé avec ses relations
        $user = User::onlyTrashed()
            ->where('id', $id)
            ->where('email', 'LIKE', 'deleted_%@anonymized.local')
            ->with([
                'transactions' => function($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'transactions.paymentMean', // Si tu veux voir le moyen de paiement
                'cards',
                'logs' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(50);
                }
            ])
            ->firstOrFail();

        // Calculer des statistiques
        $statistics = [
            'total_transactions' => $user->transactions->count(),
            'total_amount' => $user->transactions->sum('amount'),
            'total_cards' => $user->cards->count(),
            'first_transaction' => $user->transactions->last()?->created_at,
            'last_transaction' => $user->transactions->first()?->created_at,
        ];

        return response()->json([
            'message' => 'Détails de l\'utilisateur anonymisé récupérés avec succès',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'contact_email' => $user->contact_email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'deleted_at' => $user->deleted_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'statistics' => $statistics,
                'transactions' => $user->transactions,
                'cards' => $user->cards,
                'logs' => $user->logs
            ]
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'error' => 'Utilisateur anonymisé non trouvé'
        ], Response::HTTP_NOT_FOUND);
    } catch (\Exception $e) {
        LogFacade::error('Erreur récupération détails utilisateur anonymisé: ' . $e->getMessage());

        return response()->json([
            'error' => 'Erreur lors de la récupération des détails de l\'utilisateur anonymisé'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

}
