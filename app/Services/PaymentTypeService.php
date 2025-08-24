<?php

namespace App\Services;

use App\Models\PaymentType;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log as LogFacade;

class PaymentTypeService
{
    /**
     * Récupérer tous les types de paiement
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPaymentTypes()
    {
        try {
            return PaymentType::all();
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la récupération des types de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer uniquement les types de paiement actifs
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActivePaymentTypes()
    {
        try {
            return PaymentType::where("is_active", true)->get();
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la récupération des types de paiement actifs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un type de paiement par son ID
     *
     * @param int $id
     * @return PaymentType
     */
    public function getPaymentTypeById($id)
    {
        try {
            return PaymentType::findOrFail($id);
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la récupération du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un type de paiement par son nom
     *
     * @param string $name
     * @return PaymentType|null
     */
    public function getPaymentTypeByName($name)
    {
        try {
            return PaymentType::where("name", $name)->first();
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la récupération du type de paiement par nom: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Créer un nouveau type de paiement
     *
     * @param array $data
     * @return PaymentType
     */
    public function createPaymentType(array $data)
    {
        try {
            // Traiter l'icône si elle est fournie
            if (isset($data["icon"]) && $data["icon"]) {
                // Si c'est un fichier uploadé, stocker l'icône
                if (is_a($data["icon"], \Illuminate\Http\UploadedFile::class)) {
                    $iconPath = $this->storeIcon($data["icon"]);
                    $data["icon"] = $iconPath;
                } 
                // Sinon, si c'est une chaîne de caractères (chemin statique), l'utiliser directement
                // Pas besoin de faire quoi que ce soit, la valeur est déjà le chemin
            }

            // Créer le type de paiement
            $paymentType = PaymentType::create($data);

            // Journaliser la création
            $this->logAction("create_payment_type", $paymentType->id, "Création du type de paiement: " . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la création du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un type de paiement existant
     *
     * @param int $id
     * @param array $data
     * @return PaymentType
     */
    public function updatePaymentType($id, array $data)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);

            // Traiter l'icône si elle est fournie
            if (isset($data["icon"]) && $data["icon"]) {
                // Si c'est un fichier uploadé, stocker l'icône et supprimer l'ancienne
                if (is_a($data["icon"], \Illuminate\Http\UploadedFile::class)) {
                    // Supprimer l'ancienne icône si elle existe
                    if ($paymentType->icon) {
                        Storage::delete($paymentType->icon);
                    }
                    $iconPath = $this->storeIcon($data["icon"]);
                    $data["icon"] = $iconPath;
                } 
                // Sinon, si c'est une chaîne de caractères (chemin statique), l'utiliser directement
                // Pas besoin de faire quoi que ce soit, la valeur est déjà le chemin
            }

            // Mettre à jour le type de paiement
            $paymentType->update($data);

            // Journaliser la mise à jour
            $this->logAction("update_payment_type", $paymentType->id, "Mise à jour du type de paiement: " . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la mise à jour du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activer un type de paiement
     *
     * @param int $id
     * @return PaymentType
     */
    public function activatePaymentType($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->is_active = true;
            $paymentType->save();

            // Journaliser l'activation
            $this->logAction("activate_payment_type", $paymentType->id, "Activation du type de paiement: " . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de l'activation du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Désactiver un type de paiement
     *
     * @param int $id
     * @return PaymentType
     */
    public function deactivatePaymentType($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->is_active = false;
            $paymentType->save();

            // Journaliser la désactivation
            $this->logAction("deactivate_payment_type", $paymentType->id, "Désactivation du type de paiement: " . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la désactivation du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Basculer le statut d'un type de paiement
     *
     * @param int $id
     * @return PaymentType
     */
    public function togglePaymentTypeStatus($id)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->is_active = !$paymentType->is_active;
            $paymentType->save();

            // Journaliser le changement de statut
            $status = $paymentType->is_active ? "activé" : "désactivé";
            $this->logAction("toggle_payment_type_status", $paymentType->id, "Statut du type de paiement " . $status . ": " . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors du changement de statut du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
 * Supprimer un type de paiement (soft delete)
 *
 * @param int $id
 * @return bool
 */
public function deletePaymentType($id)
{
    try {
        $paymentType = $this->getPaymentTypeById($id);

        // Vérifier si le type de paiement est utilisé par des moyens de paiement
        if ($paymentType->paymentMeans()->count() > 0) {
            throw new \Exception("Ce type de paiement est utilisé par des moyens de paiement et ne peut pas être supprimé.");
        }

        // Le modèle garde l'icône car elle pourrait être nécessaire en cas de restauration
        // Nous ne supprimons donc plus l'icône: Storage::delete($paymentType->icon);

        // Journaliser la suppression avant de supprimer
        $this->logAction("delete_payment_type", $paymentType->id, "Suppression logique du type de paiement: " . $paymentType->display_name);

        // Soft delete - cela définit juste deleted_at et conserve la ligne
        return $paymentType->delete();
    } catch (\Exception $e) {
        LogFacade::error("Erreur lors de la suppression du type de paiement: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Restaurer un type de paiement supprimé
 *
 * @param int $id
 * @return bool
 */
public function restorePaymentType($id)
{
    try {
        $paymentType = PaymentType::withTrashed()->findOrFail($id);

        // Journaliser la restauration
        $this->logAction("restore_payment_type", $paymentType->id, "Restauration du type de paiement: " . $paymentType->display_name);

        return $paymentType->restore();
    } catch (\Exception $e) {
        LogFacade::error("Erreur lors de la restauration du type de paiement: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Supprimer définitivement un type de paiement
 *
 * @param int $id
 * @return bool
 */
public function forceDeletePaymentType($id)
{
    try {
        $paymentType = PaymentType::withTrashed()->findOrFail($id);

        // Vérifier si le type de paiement est utilisé par des moyens de paiement
        if ($paymentType->paymentMeans()->count() > 0) {
            throw new \Exception("Ce type de paiement est utilisé par des moyens de paiement et ne peut pas être supprimé définitivement.");
        }

        // Supprimer l'icône car maintenant c'est une suppression définitive
        if ($paymentType->icon) {
            Storage::delete($paymentType->icon);
        }

        // Journaliser la suppression définitive
        $this->logAction("force_delete_payment_type", $paymentType->id, "Suppression définitive du type de paiement: " . $paymentType->display_name);

        // Suppression définitive
        return $paymentType->forceDelete();
    } catch (\Exception $e) {
        LogFacade::error("Erreur lors de la suppression définitive du type de paiement: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Récupérer les types de paiement supprimés
 *
 * @return \Illuminate\Database\Eloquent\Collection
 */
public function getTrashedPaymentTypes()
{
    try {
        return PaymentType::onlyTrashed()->get();
    } catch (\Exception $e) {
        LogFacade::error("Erreur lors de la récupération des types de paiement supprimés: " . $e->getMessage());
        throw $e;
    }
}
    /**
     * Mettre à jour la configuration d'un type de paiement
     *
     * @param int $id
     * @param array $config
     * @return PaymentType
     */
    public function updatePaymentTypeConfig($id, array $config)
    {
        try {
            $paymentType = $this->getPaymentTypeById($id);
            $paymentType->config = $config;
            $paymentType->save();

            // Journaliser la mise à jour de la configuration
            $this->logAction("update_payment_type_config", $paymentType->id, "Mise à jour de la configuration du type de paiement: " . $paymentType->display_name);

            return $paymentType;
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la mise à jour de la configuration du type de paiement: " . $e->getMessage());
            throw $e;
        }
    }
/**
 * Configurer le type de paiement Wave (Checkout)
 *
 * Champs attendus dans $data :
 * - api_key (string, requis)
 * - success_url (string, requis)
 * - error_url (string, requis)
 * - webhook_secret (string, optionnel si pas de webhook)
 * - base_url (string, optionnel, défaut https://api.wave.com)
 * - environment (string, optionnel: 'production' | 'sandbox' | 'custom')
 *
 * @param array $data
 * @return PaymentType
 */
public function configureWave(array $data)
{
    try {
        // 0) Normalisation + valeurs par défaut (fallback .env si fournis)
        $apiKey        = $data['api_key']        ?? env('WAVE_API_KEY');
        $successUrl    = $data['success_url']    ?? env('WAVE_SUCCESS_URL');
        $errorUrl      = $data['error_url']      ?? env('WAVE_ERROR_URL');
        $webhookSecret = $data['webhook_secret'] ?? env('WAVE_WEBHOOK_SECRET'); // optionnel
        $baseUrl       = rtrim($data['base_url'] ?? env('WAVE_API_BASE', 'https://api.wave.com'), '/');
        $environment   = $data['environment']    ?? (app()->isProduction() ? 'production' : 'sandbox');

        // Backward-compat: si ancien code passait api_secret/merchant_id, on ignore/convertit
        if (empty($webhookSecret) && !empty($data['api_secret'])) {
            // on réutilise l'ancienne clé comme secret de webhook par compat (pas idéal, mais évite de casser)
            $webhookSecret = $data['api_secret'];
        }

        // 1) Validation minimale
        if (!$apiKey || !$successUrl || !$errorUrl) {
            throw new \InvalidArgumentException("Config Wave incomplète : 'api_key', 'success_url' et 'error_url' sont requis.");
        }

        // 2) Payload de configuration standardisé
        $newConfig = [
            'api_key'        => $apiKey,
            'base_url'       => $baseUrl,
            'success_url'    => $successUrl,
            'error_url'      => $errorUrl,
            'webhook_url'    => url('/api/webhooks/wave'), // endpoint serveur
            'webhook_secret' => $webhookSecret,            // peut être null si pas de webhook
            'environment'    => $environment,
            // Optionnels utiles
            'enabled_apis'   => [
                'checkout' => true,
                'balance'  => (bool)($data['enable_balance'] ?? env('WAVE_BALANCE_ENABLED', false)),
                'payout'   => (bool)($data['enable_payout']  ?? false),
            ],
        ];

        // 3) Créer ou mettre à jour le PaymentType "wave"
        $paymentType = $this->getPaymentTypeByName('wave');

        if (!$paymentType) {
            return $this->createPaymentType([
                'name'         => 'wave',
                'display_name' => 'Wave',
                'description'  => 'Paiement via Wave (Checkout)',
                'icon'         => 'images/payment-types/wave.png',
                'is_active'    => true,
                'config'       => $newConfig,
            ]);
        }

        // Merge (on garde d’anciens champs si utiles, mais on remplace par la nouvelle conf)
        $merged = array_merge((array)($paymentType->config ?? []), $newConfig);

        // Nettoyage d’anciens champs obsolètes
        unset($merged['merchant_id'], $merged['api_secret']);

        return $this->updatePaymentTypeConfig($paymentType->id, $merged);

    } catch (\Throwable $e) {
        LogFacade::error("Erreur lors de la configuration de Wave: " . $e->getMessage());
        throw $e;
    }
}

    

    /**
     * Configurer le type de paiement Orange Money
     *
     * @param array $data
     * @return PaymentType
     */
    public function configureOrangeMoney(array $data)
    {
        try {
            // Récupérer ou créer le type de paiement Orange Money
            $paymentType = $this->getPaymentTypeByName("orange_money");

            // Définir les URLs de retour et d'annulation. Assurez-vous que ces routes existent !
            // Ces URLs doivent être accessibles publiquement pour qu'Orange Money puisse y rediriger l'utilisateur.
            $returnUrl = route("payment.success"); // Exemple: 'payment.success' est le nom de votre route
            $cancelUrl = route("payment.cancel");   // Exemple: 'payment.cancel' est le nom de votre route
            $notifUrl = route("webhooks.orange-money"); // L'URL de votre webhook

            if (!$paymentType) {
                return $this->createPaymentType([
                    "name" => "orange_money",
                    "display_name" => "Orange Money",
                    "description" => "Paiement via Orange Money",
                    "icon" => "images/payment-types/orange-money.png", // Chemin statique
                    "is_active" => true,
                    "config" => [
                        "api_key" => $data["api_key"],
                        "api_secret" => $data["api_secret"],
                        "merchant_id" => $data["merchant_id"],
                        "environment" => $data["environment"],
                        "return_url" => $returnUrl,
                        "cancel_url" => $cancelUrl,
                        "notif_url" => $notifUrl,
                    ]
                ]);
            } else {
                // Mettre à jour la configuration
                $config = $paymentType->config ?? [];
                $config["api_key"] = $data["api_key"];
                $config["api_secret"] = $data["api_secret"];
                $config["merchant_id"] = $data["merchant_id"];
                $config["environment"] = $data["environment"];
                $config["return_url"] = $returnUrl;
                $config["cancel_url"] = $cancelUrl;
                $config["notif_url"] = $notifUrl;

                return $this->updatePaymentTypeConfig($paymentType->id, $config);
            }
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la configuration d'Orange Money: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stocker l'icône du type de paiement
     *
     * @param string|\Illuminate\Http\UploadedFile $icon
     * @return string
     */
    private function storeIcon($icon)
    {
        // Si c'est un fichier uploadé, le stocker
        if (is_a($icon, \Illuminate\Http\UploadedFile::class)) {
            $fileName = time() . "_" . str_replace(" ", "_", $icon->getClientOriginalName());
            $path = $icon->storeAs("images/payment-types", $fileName, "public");
            return $path;
        }
        // Si c'est déjà une chaîne (chemin statique), la retourner telle quelle
        return $icon;
    }

    /**
     * Journaliser une action
     *
     * @param string $action
     * @param int $entityId
     * @param string $description
     * @return void
     */
    private function logAction($action, $entityId, $description)
    {
        try {
            Log::create([
                "user_id" => Auth::id(),
                "action" => $action,
                "entity_type" => "payment_type",
                "entity_id" => $entityId,
                "description" => $description
            ]);
        } catch (\Exception $e) {
            LogFacade::error("Erreur lors de la journalisation: " . $e->getMessage());
        }
    }
}
