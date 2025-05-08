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
}
