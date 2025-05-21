<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Log;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\PaymentStatus;
use App\Models\Provider;
use App\Models\PaymentMean;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TransactionService
{
    /**
     * Récupérer toutes les transactions (admin)
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllTransactions(array $filters = [])
    {
        $query = Transaction::with([
            'user',
            'card',
            'transactionType',
            'provider',
            'paymentMean',
            'paymentStatus'
        ]);

        return $this->applyFilters($query, $filters)->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Récupérer les transactions d'un utilisateur
     *
     * @param int $userId
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserTransactions(int $userId, array $filters = [])
    {
        $query = Transaction::with([
            'card',
            'transactionType',
            'provider',
            'paymentMean',
            'paymentStatus'
        ])->where('user_id', $userId);

        return $this->applyFilters($query, $filters)->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Récupérer une transaction par son ID
     *
     * @param int $id
     * @return Transaction|null
     */
    public function getTransactionById(int $id)
    {
        return Transaction::with([
            'user',
            'card',
            'transactionType',
            'provider',
            'paymentMean',
            'paymentStatus'
        ])->find($id);
    }

    /**
     * Récupérer une transaction utilisateur par son UID
     *
     * @param int $userId
     * @param string $transactionUid
     * @return Transaction|null
     */
    public function getUserTransactionByUid(int $userId, string $transactionUid)
    {
        return Transaction::with([
            'card',
            'transactionType',
            'provider',
            'paymentMean',
            'paymentStatus'
        ])
        ->where('user_id', $userId)
        ->where('transaction_uid', $transactionUid)
        ->first();
    }

    /**
     * Traiter un dépôt
     *
     * @param array $data
     * @return array
     */
    public function processDeposit(array $data)
    {
        try {
            // Démarrer une transaction de base de données
            DB::beginTransaction();

            // Récupérer la carte
            $card = Card::where('id', $data['card_id'])
                ->where('user_id', $data['user_id'])
                ->first();

            if (!$card) {
                return [
                    'success' => false,
                    'message' => 'Carte introuvable'
                ];
            }

            // Vérifier si la carte est active
            if ($card->status !== 'activated') {
                return [
                    'success' => false,
                    'message' => 'Cette carte n\'est pas active et ne peut pas recevoir de dépôt'
                ];
            }

            // Récupérer le moyen de paiement
            $paymentMean = PaymentMean::where('id', $data['payment_mean_id'])
                ->where('user_id', $data['user_id'])
                ->first();

            if (!$paymentMean) {
                return [
                    'success' => false,
                    'message' => 'Moyen de paiement introuvable'
                ];
            }

            // Vérifier si le moyen de paiement est actif
            if ($paymentMean->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Ce moyen de paiement n\'est pas actif'
                ];
            }

            // Récupérer le type de transaction "dépôt"
            $transactionType = TransactionType::where('name', 'deposit')->first();

            if (!$transactionType) {
                return [
                    'success' => false,
                    'message' => 'Type de transaction introuvable'
                ];
            }

            // Récupérer le statut "en attente"
            $pendingStatus = PaymentStatus::where('name', 'pending')->first();

            if (!$pendingStatus) {
                return [
                    'success' => false,
                    'message' => 'Statut de paiement introuvable'
                ];
            }

            // Créer la transaction en base
            $transaction = new Transaction([
                'card_id' => $card->id,
                'user_id' => $data['user_id'],
                'amount' => $data['amount'],
                'transaction_date' => now(),
                'transaction_type_id' => $transactionType->id,
                'payment_mean_id' => $paymentMean->id,
                'provider_id' => null,  // Un dépôt n'a pas de prestataire
                'payment_status_id' => $pendingStatus->id,
                'previous_balance' => $card->balance,
                'current_balance' => $card->balance,  // Sera mis à jour après traitement
                'transaction_uid' => Str::uuid(),
                'description' => $data['description'] ?? 'Dépôt sur la carte',
                'metadata' => $data['metadata'] ?? null
            ]);

            $transaction->save();

            // TODO: Intégration avec un service de paiement réel
            // Pour l'instant, simulons une réponse positive
            $paymentSuccessful = true;

            if ($paymentSuccessful) {
                // Mettre à jour le solde de la carte
                $card->balance += $data['amount'];
                $card->save();

                // Mettre à jour la transaction
                $paidStatus = PaymentStatus::where('name', 'paid')->first();
                $transaction->payment_status_id = $paidStatus->id;
                $transaction->current_balance = $card->balance;
                $transaction->save();

                // Journaliser l'action
                Log::create([
                    'user_id' => $data['user_id'],
                    'action' => 'deposit',
                    'entity_type' => 'transaction',
                    'entity_id' => $transaction->id,
                    'description' => "Dépôt de {$data['amount']} FCFA sur la carte #{$card->card_number}",
                ]);

                DB::commit();

                // Recharger les relations
                $transaction->load(['transactionType', 'paymentStatus', 'card']);

                return [
                    'success' => true,
                    'transaction' => $transaction
                ];
            } else {
                // Mettre à jour la transaction pour indiquer l'échec
                $failedStatus = PaymentStatus::where('name', 'failed')->first();
                $transaction->payment_status_id = $failedStatus->id;
                $transaction->save();

                DB::commit();

                return [
                    'success' => false,
                    'message' => 'Le paiement a échoué'
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            LogFacade::error('Erreur processDeposit: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors du traitement du dépôt',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Traiter un paiement
     *
     * @param array $data
     * @return array
     */
    public function processPayment(array $data)
    {
        try {
            // Démarrer une transaction de base de données
            DB::beginTransaction();

            // Récupérer la carte
            $card = Card::where('id', $data['card_id'])
                ->where('user_id', $data['user_id'])
                ->first();

            if (!$card) {
                return [
                    'success' => false,
                    'message' => 'Carte introuvable'
                ];
            }

            // Vérifier si la carte est active
            if ($card->status !== 'activated') {
                return [
                    'success' => false,
                    'message' => 'Cette carte n\'est pas active et ne peut pas être utilisée pour un paiement'
                ];
            }

            // Récupérer l'utilisateur
            $user = User::find($data['user_id']);

            // Vérifier le code de vérification
            if ($user->verification_code !== $data['verification_code']) {
                return [
                    'success' => false,
                    'message' => 'Code de vérification incorrect'
                ];
            }

            // Récupérer le prestataire
            $provider = Provider::where('id', $data['provider_id'])
                ->where('status', 'active')
                ->first();

            if (!$provider) {
                return [
                    'success' => false,
                    'message' => 'Prestataire introuvable ou inactif'
                ];
            }

            // Vérifier le solde suffisant
            if ($card->balance < $data['amount']) {
                return [
                    'success' => false,
                    'message' => 'Solde insuffisant sur la carte'
                ];
            }

            // Récupérer le type de transaction "paiement"
            $transactionType = TransactionType::where('name', 'payment')->first();

            if (!$transactionType) {
                return [
                    'success' => false,
                    'message' => 'Type de transaction introuvable'
                ];
            }

            // Récupérer le statut "en attente"
            $pendingStatus = PaymentStatus::where('name', 'pending')->first();

            if (!$pendingStatus) {
                return [
                    'success' => false,
                    'message' => 'Statut de paiement introuvable'
                ];
            }

            // Créer la transaction en base
            $transaction = new Transaction([
                'card_id' => $card->id,
                'user_id' => $data['user_id'],
                'amount' => $data['amount'],
                'transaction_date' => now(),
                'transaction_type_id' => $transactionType->id,
                'payment_mean_id' => null,  // Un paiement n'utilise pas de moyen de paiement
                'provider_id' => $provider->id,
                'payment_status_id' => $pendingStatus->id,
                'previous_balance' => $card->balance,
                'current_balance' => $card->balance,  // Sera mis à jour après traitement
                'transaction_uid' => Str::uuid(),
                'description' => $data['description'] ?? 'Paiement santé',
                'metadata' => $data['metadata'] ?? null
            ]);

            $transaction->save();

            // TODO: Intégration avec un service de validation réel
            // Pour l'instant, simulons une réponse positive
            $paymentSuccessful = true;

            if ($paymentSuccessful) {
                // Mettre à jour le solde de la carte
                $card->balance -= $data['amount'];
                $card->save();

                // Mettre à jour la transaction
                $paidStatus = PaymentStatus::where('name', 'paid')->first();
                $transaction->payment_status_id = $paidStatus->id;
                $transaction->current_balance = $card->balance;
                $transaction->save();

                // Journaliser l'action
                Log::create([
                    'user_id' => $data['user_id'],
                    'action' => 'payment',
                    'entity_type' => 'transaction',
                    'entity_id' => $transaction->id,
                    'description' => "Paiement de {$data['amount']} FCFA au prestataire {$provider->structure_name}",
                ]);

                DB::commit();

                // Recharger les relations
                $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider']);

                return [
                    'success' => true,
                    'transaction' => $transaction
                ];
            } else {
                // Mettre à jour la transaction pour indiquer l'échec
                $failedStatus = PaymentStatus::where('name', 'failed')->first();
                $transaction->payment_status_id = $failedStatus->id;
                $transaction->save();

                DB::commit();

                return [
                    'success' => false,
                    'message' => 'Le paiement a échoué'
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            LogFacade::error('Erreur processPayment: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors du traitement du paiement',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    // /**
    //  * Annuler une transaction
    //  *
    //  * @param string $transactionUid
    //  * @param int $userId
    //  * @param bool $isAdmin
    //  * @return array
    //  */
    // public function cancelTransaction(string $transactionUid, int $userId, bool $isAdmin = false)
    // {
    //     try {
    //         DB::beginTransaction();

    //         // Récupérer la transaction
    //         $query = Transaction::where('transaction_uid', $transactionUid);

    //         // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
    //         if (!$isAdmin) {
    //             $query->where('user_id', $userId);
    //         }

    //         $transaction = $query->first();

    //         if (!$transaction) {
    //             return [
    //                 'success' => false,
    //                 'message' => 'Transaction introuvable'
    //             ];
    //         }

    //         // Vérifier si la transaction peut être annulée (statut, délai, etc.)
    //         $cancelableStatuses = PaymentStatus::whereIn('name', ['pending', 'processing'])->pluck('id')->toArray();

    //         if (!in_array($transaction->payment_status_id, $cancelableStatuses)) {
    //             return [
    //                 'success' => false,
    //                 'message' => 'Cette transaction ne peut pas être annulée'
    //             ];
    //         }

    //         // Vérifier le délai (ex: 30 minutes maximum pour annuler)
    //         $maxCancellationTime = Carbon::now()->subMinutes(30);
    //         if ($transaction->created_at < $maxCancellationTime && !$isAdmin) {
    //             return [
    //                 'success' => false,
    //                 'message' => 'Le délai d\'annulation est dépassé'
    //             ];
    //         }

    //         // Récupérer le statut "annulé"
    //         $canceledStatus = PaymentStatus::where('name', 'cancelled')->first();

    //         if (!$canceledStatus) {
    //             return [
    //                 'success' => false,
    //                 'message' => 'Statut d\'annulation introuvable'
    //             ];
    //         }

    //         // Si le solde a déjà été modifié, le restaurer
    //         if ($transaction->current_balance != $transaction->previous_balance) {
    //             $card = $transaction->card;

    //             if ($transaction->transactionType->is_credit) {
    //                 // Si c'était un crédit (dépôt), retirer le montant
    //                 $card->balance -= $transaction->amount;
    //             } else {
    //                 // Si c'était un débit (paiement), restaurer le montant
    //                 $card->balance += $transaction->amount;
    //             }

    //             $card->save();

    //             // Restaurer le solde dans la transaction
    //             $transaction->current_balance = $transaction->previous_balance;
    //         }

    //         // Mettre à jour le statut de la transaction
    //         $transaction->payment_status_id = $canceledStatus->id;
    //         $transaction->save();

    //         // Journaliser l'action
    //         Log::create([
    //             'user_id' => $userId,
    //             'action' => 'cancel_transaction',
    //             'entity_type' => 'transaction',
    //             'entity_id' => $transaction->id,
    //             'description' => "Annulation de la transaction #{$transaction->transaction_uid}",
    //         ]);

    //         DB::commit();

    //         // Recharger les relations
    //         $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider', 'paymentMean']);

    //         return [
    //             'success' => true,
    //             'transaction' => $transaction
    //         ];
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         LogFacade::error('Erreur cancelTransaction: ' . $e->getMessage());

    //         return [
    //             'success' => false,
    //             'message' => 'Une erreur est survenue lors de l\'annulation de la transaction',
    //             'errors' => [
    //                 'exception' => $e->getMessage()
    //             ]
    //         ];
    //     }
    // }



    /**
 * Annuler une transaction
 *
 * @param string $transactionUid
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function cancelTransaction(string $transactionUid, int $userId, bool $isAdmin = false)
{
    try {
        DB::beginTransaction();

        // Récupérer la transaction
        $query = Transaction::where('transaction_uid', $transactionUid);

        // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }

        $transaction = $query->with(['transactionType', 'paymentStatus', 'card'])->first();

        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction introuvable'
            ];
        }

        // Est-ce un dépôt?
        $isDeposit = $transaction->transactionType->is_credit;
        $isPaid = $transaction->paymentStatus->name === 'paid';

        // Récupérer les statuts pour la logique
        $paidStatus = PaymentStatus::where('name', 'paid')->first();
        $pendingStatus = PaymentStatus::where('name', 'pending')->first();
        $cancelledStatus = PaymentStatus::where('name', 'cancelled')->first();

        if (!$cancelledStatus) {
            return [
                'success' => false,
                'message' => 'Statut d\'annulation introuvable'
            ];
        }

        // Logique spéciale pour les dépôts payés
        if ($isDeposit && $isPaid) {
            // Si c'est un dépôt payé, vérifier le délai
            $directCancellationTime = Carbon::now()->subHours(2); // 2h pour annulation directe
            $maxCancellationTime = Carbon::now()->subHours(24);  // 24h pour inversion

            // Si le dépôt a été fait il y a moins de 2h, annulation directe
            if ($transaction->created_at >= $directCancellationTime) {
                // Annulation directe possible: continuer normalement
            }
            // Si le dépôt a été fait entre 2h et 24h, créer une transaction inverse
            else if ($transaction->created_at >= $maxCancellationTime || $isAdmin) {
                // Créer une transaction de retrait pour inverser le dépôt
                $withdrawalType = TransactionType::where('name', 'withdrawal')->first();

                if (!$withdrawalType) {
                    return [
                        'success' => false,
                        'message' => 'Type de transaction "withdrawal" introuvable'
                    ];
                }

                $card = $transaction->card;

                // Vérifier solde suffisant
                if ($card->balance < $transaction->amount) {
                    return [
                        'success' => false,
                        'message' => 'Solde insuffisant pour annuler ce dépôt'
                    ];
                }

                $newTransaction = new Transaction([
                    'card_id' => $transaction->card_id,
                    'user_id' => $userId,
                    'amount' => $transaction->amount,
                    'transaction_date' => now(),
                    'transaction_type_id' => $withdrawalType->id,
                    'payment_mean_id' => $transaction->payment_mean_id,
                    'provider_id' => null,
                    'payment_status_id' => $paidStatus->id,
                    'previous_balance' => $card->balance,
                    'current_balance' => $card->balance - $transaction->amount,
                    'transaction_uid' => Str::uuid(),
                    'description' => "Annulation du dépôt #{$transaction->transaction_uid}",
                    'metadata' => ['original_transaction_id' => $transaction->id]
                ]);

                // Mettre à jour le solde de la carte
                $card->balance -= $transaction->amount;
                $card->save();

                $newTransaction->save();

                // Journaliser l'action
                Log::create([
                    'user_id' => $userId,
                    'action' => 'reverse_transaction',
                    'entity_type' => 'transaction',
                    'entity_id' => $newTransaction->id,
                    'description' => "Création d'une transaction inverse pour annuler le dépôt #{$transaction->transaction_uid}",
                ]);

                // Charger les relations
                $newTransaction->load(['transactionType', 'paymentStatus', 'card', 'paymentMean']);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Transaction inverse créée pour annuler le dépôt',
                    'transaction' => $newTransaction,
                    'is_reversal' => true
                ];
            } else {
                // Dépôt trop ancien
                return [
                    'success' => false,
                    'message' => 'Ce dépôt ne peut plus être annulé car il date de plus de 24 heures'
                ];
            }
        } else {
            // Pour les paiements ou autres transactions non-dépôt
            $cancelableStatuses = PaymentStatus::whereIn('name', ['pending', 'processing'])->pluck('id')->toArray();

            // Les admins peuvent annuler plus de types de statuts
            if ($isAdmin) {
                $cancelableStatuses = PaymentStatus::where('name', '!=', 'cancelled')->pluck('id')->toArray();
            }

            if (!in_array($transaction->payment_status_id, $cancelableStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Cette transaction ne peut pas être annulée'
                ];
            }

            // Vérifier le délai pour les paiements (sauf admin)
            $maxCancellationTime = Carbon::now()->subMinutes(30);
            if (!$isDeposit && $transaction->created_at < $maxCancellationTime && !$isAdmin) {
                return [
                    'success' => false,
                    'message' => 'Le délai d\'annulation est dépassé (30 minutes maximum)'
                ];
            }
        }

        // Code d'annulation standard
        // Si le solde a déjà été modifié, le restaurer
        if ($transaction->current_balance != $transaction->previous_balance) {
            $card = $transaction->card;

            if ($transaction->transactionType->is_credit) {
                // Si c'était un crédit (dépôt), retirer le montant
                $card->balance -= $transaction->amount;
            } else {
                // Si c'était un débit (paiement), restaurer le montant
                $card->balance += $transaction->amount;
            }

            $card->save();

            // Restaurer le solde dans la transaction
            $transaction->current_balance = $transaction->previous_balance;
        }

        // Mettre à jour le statut de la transaction
        $transaction->payment_status_id = $cancelledStatus->id;
        $transaction->save();

        // Journaliser l'action
        $actionType = $isDeposit ? 'dépôt' : 'paiement';
        $userType = $isAdmin ? 'admin' : 'utilisateur';

        Log::create([
            'user_id' => $userId,
            'action' => 'cancel_transaction',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'description' => "Annulation du {$actionType} #{$transaction->transaction_uid} par {$userType}",
        ]);

        DB::commit();

        // Recharger les relations
        $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider', 'paymentMean']);

        return [
            'success' => true,
            'transaction' => $transaction,
            'message' => $isDeposit
                ? 'Dépôt annulé avec succès'
                : 'Paiement annulé avec succès'
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        LogFacade::error('Erreur cancelTransaction: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de l\'annulation de la transaction',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

    /**
     * Mettre à jour le statut d'une transaction (admin uniquement)
     *
     * @param int $id
     * @param int $statusId
     * @param string $reason
     * @return array
     */
    public function updateTransactionStatus(int $id, int $statusId, string $reason)
    {
        try {
            DB::beginTransaction();

            // Récupérer la transaction
            $transaction = Transaction::findOrFail($id);

            // Récupérer le nouveau statut
            $newStatus = PaymentStatus::findOrFail($statusId);

            // Vérifier si le statut est différent
            if ($transaction->payment_status_id == $statusId) {
                return [
                    'success' => false,
                    'message' => 'La transaction a déjà ce statut'
                ];
            }

            // Récupérer l'ancien statut pour la journalisation
            $oldStatus = $transaction->paymentStatus;

            // Actions spécifiques selon le statut
            if ($newStatus->name === 'paid' && $oldStatus->name !== 'paid') {
                // Si on passe de non payé à payé, mettre à jour le solde
                $card = $transaction->card;

                if ($transaction->transactionType->is_credit) {
                    // Pour un crédit (dépôt), ajouter le montant
                    $card->balance += $transaction->amount;
                } else {
                    // Pour un débit (paiement), retirer le montant
                    if ($card->balance < $transaction->amount) {
                        return [
                            'success' => false,
                            'message' => 'Solde insuffisant sur la carte'
                        ];
                    }
                    $card->balance -= $transaction->amount;
                }

                $card->save();
                $transaction->current_balance = $card->balance;
            } else if ($oldStatus->name === 'paid' && $newStatus->name !== 'paid') {
                // Si on passe de payé à non payé, restaurer le solde
                $card = $transaction->card;

                if ($transaction->transactionType->is_credit) {
                    // Pour un crédit (dépôt), retirer le montant
                    if ($card->balance < $transaction->amount) {
                        return [
                            'success' => false,
                            'message' => 'Solde insuffisant sur la carte pour annuler ce dépôt'
                        ];
                    }
                    $card->balance -= $transaction->amount;
                } else {
                    // Pour un débit (paiement), restaurer le montant
                    $card->balance += $transaction->amount;
                }

                $card->save();
                $transaction->current_balance = $card->balance;
            }

            // Mettre à jour le statut
            $transaction->payment_status_id = $statusId;
            $transaction->save();

            // Journaliser l'action
            Log::create([
                'user_id' => Auth::id(),
                'action' => 'update_transaction_status',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => "Modification du statut de la transaction de '{$oldStatus->display_name}' à '{$newStatus->display_name}'. Raison: {$reason}",
            ]);

            DB::commit();

            // Recharger les relations
            $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider', 'paymentMean', 'user']);

            return [
                'success' => true,
                'transaction' => $transaction
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            LogFacade::error('Erreur updateTransactionStatus: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du statut de la transaction',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Obtenir les statistiques
     *
     * @param string $period
     * @param int|null $userId
     * @return array
     */
    public function getStatistics(string $period = 'month', ?int $userId = null)
    {
        try {
            $now = Carbon::now();
            $startDate = null;

            // Déterminer la période
            switch ($period) {
                case 'day':
                    $startDate = $now->copy()->startOfDay();
                    break;
                case 'week':
                    $startDate = $now->copy()->startOfWeek();
                    break;
                case 'month':
                    $startDate = $now->copy()->startOfMonth();
                    break;
                case 'quarter':
                    $startDate = $now->copy()->startOfQuarter();
                    break;
                case 'year':
                    $startDate = $now->copy()->startOfYear();
                    break;
                default:
                    $startDate = $now->copy()->subDays(30);
            }

            // Construire la requête de base
            $query = Transaction::where('transaction_date', '>=', $startDate)
                ->whereHas('paymentStatus', function ($q) {
                    $q->where('name', 'paid');
                });

            // Filtrer par utilisateur si spécifié
            if ($userId) {
                $query->where('user_id', $userId);
            }

            // Récupérer les transactions
            $transactions = $query->with(['transactionType', 'provider'])->get();

            // Calculer les totaux
            $totalDeposits = $transactions->filter(function ($t) {
                return $t->transactionType->is_credit;
            })->sum('amount');

            $totalPayments = $transactions->filter(function ($t) {
                return !$t->transactionType->is_credit;
            })->sum('amount');

            // Grouper par type de transaction
            $byType = [];
            $transactionTypes = TransactionType::all()->keyBy('id');

            foreach ($transactions->groupBy('transaction_type_id') as $typeId => $typeTransactions) {
                $typeName = $transactionTypes[$typeId]->display_name ?? 'Inconnu';
                $byType[$typeName] = $typeTransactions->sum('amount');
            }

            // Grouper par prestataire (pour les paiements)
            $byProvider = [];
            $payments = $transactions->filter(function ($t) {
                return !$t->transactionType->is_credit && $t->provider_id;
            });

            foreach ($payments->groupBy('provider_id') as $providerId => $providerTransactions) {
                $providerName = $providerTransactions->first()->provider->structure_name ?? 'Inconnu';
                $byProvider[$providerName] = $providerTransactions->sum('amount');
            }

            // Statistiques par jour
            $byDate = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $now) {
                $date = $currentDate->format('Y-m-d');
                $dayTransactions = $transactions->filter(function ($t) use ($date) {
                    return Carbon::parse($t->transaction_date)->format('Y-m-d') === $date;
                });

                $deposits = $dayTransactions->filter(function ($t) {
                    return $t->transactionType->is_credit;
                })->sum('amount');

                $payments = $dayTransactions->filter(function ($t) {
                    return !$t->transactionType->is_credit;
                })->sum('amount');

                $byDate[] = [
                    'date' => $date,
                    'deposits' => $deposits,
                    'payments' => $payments,
                    'total' => $deposits - $payments
                ];

                $currentDate->addDay();
            }

            return [
                'period' => $period,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $now->format('Y-m-d'),
                'total_transactions' => $transactions->count(),
                'total_deposits' => $totalDeposits,
                'total_payments' => $totalPayments,
                'net_balance' => $totalDeposits - $totalPayments,
                'by_type' => $byType,
                'by_provider' => $byProvider,
                'by_date' => $byDate
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur getStatistics: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Obtenir les statistiques d'un utilisateur
     *
     * @param int $userId
     * @param string $period
     * @return array
     */
    public function getUserStatistics(int $userId, string $period = 'month')
    {
        return $this->getStatistics($period, $userId);
    }

    /**
     * Générer un reçu de transaction
     *
     * @param string $transactionUid
     * @param int $userId
     * @param bool $isAdmin
     * @return array
     */
    public function generateReceipt(string $transactionUid, int $userId, bool $isAdmin = false)
    {
        try {
            // Récupérer la transaction
            $query = Transaction::where('transaction_uid', $transactionUid);

            // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
            if (!$isAdmin) {
                $query->where('user_id', $userId);
            }

            $transaction = $query->with([
                'user',
                'card',
                'transactionType',
                'provider',
                'paymentMean',
                'paymentStatus'
            ])->first();

            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction introuvable'
                ];
            }

            // Préparer les données
            $data = [
                'transaction' => $transaction,
                'date' => Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i'),
                'company_name' => 'FAJMA Health Wallet',
                'company_address' => 'Dakar, Sénégal',
                'company_phone' => '+221 XX XXX XX XX',
                'company_email' => 'contact@fajma.sn',
                'receipt_date' => now()->format('d/m/Y H:i'),
                'receipt_number' => 'FAJMA-' . str_pad($transaction->id, 8, '0', STR_PAD_LEFT),
            ];

            // Générer le PDF
            $pdf = PDF::loadView('receipts.transaction', $data);

            // Journaliser l'action
            Log::create([
                'user_id' => $userId,
                'action' => 'generate_receipt',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => "Génération d'un reçu pour la transaction #{$transaction->transaction_uid}",
            ]);

            return [
                'success' => true,
                'pdf' => $pdf,
                'transaction' => $transaction
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur generateReceipt: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la génération du reçu',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

   /**
 * Exporter les transactions
 *
 * @param array $filters
 * @param string $format
 * @return array
 */
public function exportTransactions(array $filters = [], string $format = 'csv')
{
    try {
        // Construire la requête
        $query = Transaction::with([
            'user',
            'card',
            'transactionType',
            'provider',
            'paymentMean',
            'paymentStatus'
        ]);

        // Appliquer les filtres
        $query = $this->applyFilters($query, $filters);

        // Récupérer les transactions
        $transactions = $query->get();

        // Formater les données pour l'export
        $exportData = [];

        foreach ($transactions as $transaction) {
            $exportData[] = [
                // 'ID' => $transaction->id,
                'UID' => $transaction->transaction_uid,
                'Date' => Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i'),
                'Utilisateur' => $transaction->user->first_name . ' ' . $transaction->user->last_name,
                'Carte' => $transaction->card->card_number,
                'Type' => $transaction->transactionType->display_name,
                'Montant' => $transaction->amount,
                'Prestataire' => $transaction->provider ? $transaction->provider->structure_name : 'N/A',
                'Moyen de paiement' => $transaction->paymentMean ? $transaction->paymentMean->paymentType->display_name : 'N/A',
                'Statut' => $transaction->paymentStatus->display_name,
                'Solde précédent' => $transaction->previous_balance,
                'Solde actuel' => $transaction->current_balance,
                'Description' => $transaction->description,
                'Créé le' => $transaction->created_at->format('d/m/Y H:i'),
            ];
        }

        // Journaliser l'action
        Log::create([
            'user_id' => Auth::id(),
            'action' => 'export_transactions',
            'description' => "Export des transactions au format {$format}",
        ]);

        // Retourner selon le format demandé
        if ($format === 'csv') {
            // Générer le contenu CSV
            $csv = $this->arrayToCsv($exportData);

            return [
                'success' => true,
                'content' => $csv,
            ];
        } else if ($format === 'excel') {
            // Créer un fichier Excel
            try {
                // Vérifier si les dépendances sont installées
                if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    throw new \Exception("La bibliothèque PhpSpreadsheet n'est pas installée. Exécutez 'composer require phpoffice/phpspreadsheet'");
                }

                // Créer un spreadsheet
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Ajouter les en-têtes
                if (!empty($exportData)) {
                    $headers = array_keys($exportData[0]);
                    $column = 'A'; // Commencer à la colonne A
                    foreach ($headers as $header) {
                        $sheet->setCellValue($column . '1', $header);
                        $column++; // Passer à la colonne suivante (B, C, etc.)
                    }

                    // Ajouter les données
                    $row = 2;
                    foreach ($exportData as $rowData) {
                        $column = 'A';
                        foreach ($rowData as $value) {
                            $sheet->setCellValue($column . $row, $value);
                            $column++;
                        }
                        $row++;
                    }
                }

                // Sauvegarder le fichier
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $filename = 'transactions-' . date('Y-m-d-His') . '.xlsx';
                $tempFile = tempnam(sys_get_temp_dir(), 'excel-');
                $writer->save($tempFile);

                // Créer un fichier manipulable
                return [
                    'success' => true,
                    'excel' => [
                        'path' => $tempFile,
                        'filename' => $filename,
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ]
                ];
            } catch (\Exception $excelException) {
                LogFacade::error('Erreur export Excel: ' . $excelException->getMessage());

                // En cas d'échec, retourner un CSV comme fallback
                $csv = $this->arrayToCsv($exportData);

                return [
                    'success' => true,
                    'content' => $csv,
                    'warning' => 'Export Excel non disponible: ' . $excelException->getMessage() . '. CSV fourni à la place.'
                ];
            }
        } else if ($format === 'pdf') {
            // Pour le moment, on retourne un CSV
            $csv = $this->arrayToCsv($exportData);

            return [
                'success' => true,
                'content' => $csv,
            ];
        }

        return [
            'success' => false,
            'message' => 'Format d\'export non pris en charge'
        ];
    } catch (\Exception $e) {
        LogFacade::error('Erreur exportTransactions: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de l\'export des transactions',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }

}

    /**
     * Récupérer le rapport quotidien
     *
     * @param string $date
     * @return array
     */
    public function getDailyReport(string $date)
    {
        try {
            // Parser la date
            $reportDate = Carbon::parse($date);
            $startDate = $reportDate->copy()->startOfDay();
            $endDate = $reportDate->copy()->endOfDay();

            // Récupérer les transactions de la journée
            $transactions = Transaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->with(['transactionType', 'paymentStatus', 'user', 'provider'])
                ->get();

            // Calculer les totaux
            $paidTransactions = $transactions->filter(function ($t) {
                return $t->paymentStatus->name === 'paid';
            });

            $totalDeposits = $paidTransactions->filter(function ($t) {
                return $t->transactionType->is_credit;
            })->sum('amount');

            $totalPayments = $paidTransactions->filter(function ($t) {
                return !$t->transactionType->is_credit;
            })->sum('amount');

            // Statistiques par statut
            $byStatus = [];
            foreach ($transactions->groupBy('payment_status_id') as $statusId => $statusTransactions) {
                $statusName = $statusTransactions->first()->paymentStatus->display_name;
                $byStatus[$statusName] = [
                    'count' => $statusTransactions->count(),
                    'amount' => $statusTransactions->sum('amount')
                ];
            }

            // Statistiques par type
            $byType = [];
            foreach ($transactions->groupBy('transaction_type_id') as $typeId => $typeTransactions) {
                $typeName = $typeTransactions->first()->transactionType->display_name;
                $byType[$typeName] = [
                    'count' => $typeTransactions->count(),
                    'amount' => $typeTransactions->sum('amount')
                ];
            }

            // Top 5 des prestataires
            $byProvider = [];
            $payments = $paidTransactions->filter(function ($t) {
                return !$t->transactionType->is_credit && $t->provider_id;
            });

            foreach ($payments->groupBy('provider_id') as $providerId => $providerTransactions) {
                $providerName = $providerTransactions->first()->provider->structure_name;
                $byProvider[$providerName] = [
                    'count' => $providerTransactions->count(),
                    'amount' => $providerTransactions->sum('amount')
                ];
            }

            // Trier par montant et prendre les top 5
            uasort($byProvider, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });

            $topProviders = array_slice($byProvider, 0, 5);

            return [
                'date' => $date,
                'total_transactions' => $transactions->count(),
                'total_paid_transactions' => $paidTransactions->count(),
                'total_deposits' => $totalDeposits,
                'total_payments' => $totalPayments,
                'net_balance' => $totalDeposits - $totalPayments,
                'by_status' => $byStatus,
                'by_type' => $byType,
                'top_providers' => $topProviders
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur getDailyReport: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Récupérer le rapport mensuel
     *
     * @param string $month
     * @return array
     */
    public function getMonthlyReport(string $month)
    {
        try {
            // Parser la date
            $reportDate = Carbon::parse($month . '-01');
            $startDate = $reportDate->copy()->startOfMonth();
            $endDate = $reportDate->copy()->endOfMonth();

            // Récupérer les transactions du mois
            $transactions = Transaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->with(['transactionType', 'paymentStatus', 'user', 'provider'])
                ->get();

            // Calculer les totaux
            $paidTransactions = $transactions->filter(function ($t) {
                return $t->paymentStatus->name === 'paid';
            });

            $totalDeposits = $paidTransactions->filter(function ($t) {
                return $t->transactionType->is_credit;
            })->sum('amount');

            $totalPayments = $paidTransactions->filter(function ($t) {
                return !$t->transactionType->is_credit;
            })->sum('amount');

            // Statistiques par jour
            $byDate = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $date = $currentDate->format('Y-m-d');
                $dayTransactions = $paidTransactions->filter(function ($t) use ($date) {
                    return Carbon::parse($t->transaction_date)->format('Y-m-d') === $date;
                });

                $deposits = $dayTransactions->filter(function ($t) {
                    return $t->transactionType->is_credit;
                })->sum('amount');

                $payments = $dayTransactions->filter(function ($t) {
                    return !$t->transactionType->is_credit;
                })->sum('amount');

                $byDate[] = [
                    'date' => $date,
                    'deposits' => $deposits,
                    'payments' => $payments,
                    'total' => $deposits - $payments,
                    'count' => $dayTransactions->count()
                ];

                $currentDate->addDay();
            }

            // Top 10 des prestataires
            $byProvider = [];
            $payments = $paidTransactions->filter(function ($t) {
                return !$t->transactionType->is_credit && $t->provider_id;
            });

            foreach ($payments->groupBy('provider_id') as $providerId => $providerTransactions) {
                $providerName = $providerTransactions->first()->provider->structure_name;
                $byProvider[$providerName] = [
                    'count' => $providerTransactions->count(),
                    'amount' => $providerTransactions->sum('amount')
                ];
            }

            // Trier par montant et prendre les top 10
            uasort($byProvider, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });

            $topProviders = array_slice($byProvider, 0, 10);

            return [
                'month' => $month,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_transactions' => $transactions->count(),
                'total_paid_transactions' => $paidTransactions->count(),
                'total_deposits' => $totalDeposits,
                'total_payments' => $totalPayments,
                'net_balance' => $totalDeposits - $totalPayments,
                'by_date' => $byDate,
                'top_providers' => $topProviders
            ];
        } catch (\Exception $e) {
            LogFacade::error('Erreur getMonthlyReport: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Appliquer les filtres à la requête
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyFilters($query, array $filters)
    {
        // Filtrer par carte
        if (isset($filters['card_id'])) {
            $query->where('card_id', $filters['card_id']);
        }

        // Filtrer par type de transaction
        if (isset($filters['type'])) {
            $query->whereHas('transactionType', function ($q) use ($filters) {
                $q->where('name', $filters['type']);
            });
        }

        // Filtrer par statut
        if (isset($filters['status'])) {
            $query->whereHas('paymentStatus', function ($q) use ($filters) {
                $q->where('name', $filters['status']);
            });
        }

        // Filtrer par date
        if (isset($filters['date_from'])) {
            $query->where('transaction_date', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (isset($filters['date_to'])) {
            $query->where('transaction_date', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Filtrer par montant
        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        // Filtrer par prestataire
        if (isset($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        // Tri
        $sortBy = $filters['sort_by'] ?? 'transaction_date';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $query->orderBy($sortBy, $sortDir);

        return $query;
    }

    /**
     * Convertir un tableau en CSV
     *
     * @param array $array
     * @return string
     */
    private function arrayToCsv(array $array)
    {
        if (empty($array)) {
            return '';
        }

        // Entêtes
        $csv = implode(',', array_keys($array[0])) . "\n";

        // Données
        foreach ($array as $row) {
            $line = [];

            foreach ($row as $value) {
                // Échapper les guillemets et entourer de guillemets si nécessaire
                if (is_numeric($value)) {
                    $line[] = $value;
                } else {
                    $line[] = '"' . str_replace('"', '""', $value) . '"';
                }
            }

            $csv .= implode(',', $line) . "\n";
        }

        return $csv;
    }

    /**
 * Suppression logique d'une transaction
 *
 * @param string $transactionUid
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function softDeleteTransaction(string $transactionUid, int $userId, bool $isAdmin = false)
{
    try {
        // Récupérer la transaction
        $query = Transaction::where('transaction_uid', $transactionUid);

        // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }

        $transaction = $query->first();

        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction introuvable'
            ];
        }

        // Vérifier si la transaction peut être supprimée logiquement
        // Par exemple, on peut décider que seules les transactions avec certains statuts peuvent être supprimées
        $deletableStatuses = PaymentStatus::whereIn('name', ['paid', 'cancelled', 'failed'])->pluck('id')->toArray();

        if (!in_array($transaction->payment_status_id, $deletableStatuses)) {
            return [
                'success' => false,
                'message' => 'Cette transaction ne peut pas être supprimée car elle est en cours de traitement'
            ];
        }

        // Effectuer la suppression logique
        $transaction->delete();

        // Journaliser l'action
        Log::create([
            'user_id' => $userId,
            'action' => 'soft_delete_transaction',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'description' => "Suppression logique de la transaction #{$transaction->transaction_uid}",
        ]);

        return [
            'success' => true,
            'message' => 'Transaction supprimée avec succès'
        ];
    } catch (\Exception $e) {
        LogFacade::error('Erreur softDeleteTransaction: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la suppression de la transaction',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Récupérer les transactions supprimées logiquement
 *
 * @param array $filters Filtres à appliquer
 * @param int $userId ID de l'utilisateur (facultatif pour les admins)
 * @param bool $isAdmin Indique si la requête est faite par un admin
 * @return array
 */
public function getTrashedTransactions(array $filters = [], ?int $userId = null, bool $isAdmin = false)
{
    try {
        // Construire la requête de base
        $query = Transaction::onlyTrashed();

        // Appliquer la restriction par utilisateur si nécessaire
        if (!$isAdmin || ($isAdmin && $userId)) {
            $query->where('user_id', $userId ?: auth()->id());
        }

        // Inclure les relations
        $relations = ['transactionType', 'paymentStatus', 'card', 'provider', 'paymentMean'];
        if ($isAdmin) {
            $relations[] = 'user'; // Inclure les données utilisateur pour les admins
        }
        $query->with($relations);

        // Appliquer les filtres en utilisant la méthode existante
        $query = $this->applyFilters($query, $filters);

        // Pagination (si non gérée dans applyFilters)
        $perPage = $filters['per_page'] ?? 10;
        $page = $filters['page'] ?? 1;

        // Exécuter la requête avec pagination
        $transactions = $query->paginate($perPage, ['*'], 'page', $page);

        // Journaliser l'action
        Log::create([
            'user_id' => auth()->id(),
            'action' => $isAdmin ? 'admin_view_trashed_transactions' : 'view_trashed_transactions',
            'description' => "Consultation des transactions supprimées" . ($userId && $isAdmin ? " pour l'utilisateur #$userId" : ""),
        ]);

        return [
            'success' => true,
            'transactions' => $transactions
        ];
    } catch (\Exception $e) {
        LogFacade::error('Erreur getTrashedTransactions: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la récupération des transactions supprimées',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Suppression logique des transactions pour une période donnée
 *
 * @param array $period
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function softDeleteTransactionsByPeriod(array $period, int $userId, bool $isAdmin = false)
{
    try {
        DB::beginTransaction();

        // Construire la requête
        $query = Transaction::whereBetween('transaction_date', [$period['start_date'], $period['end_date']]);

        // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }

        // Filtrer par statut si spécifié
        if (isset($period['status_ids']) && is_array($period['status_ids'])) {
            $query->whereIn('payment_status_id', $period['status_ids']);
        } else {
            // Par défaut, ne supprimer que les transactions terminées
            $deletableStatuses = PaymentStatus::whereIn('name', ['paid', 'cancelled', 'failed'])->pluck('id')->toArray();
            $query->whereIn('payment_status_id', $deletableStatuses);
        }

        // Récupérer les IDs des transactions avant de les supprimer pour la journalisation
        $transactionIds = $query->pluck('id')->toArray();
        $transactionCount = count($transactionIds);

        if ($transactionCount === 0) {
            return [
                'success' => false,
                'message' => 'Aucune transaction trouvée pour cette période'
            ];
        }

        // Effectuer la suppression logique
        $query->delete();

        // Journaliser l'action
        Log::create([
            'user_id' => $userId,
            'action' => 'soft_delete_transactions_period',
            'description' => "Suppression logique de {$transactionCount} transactions pour la période du {$period['start_date']} au {$period['end_date']}",
            'metadata' => json_encode([
                'transaction_ids' => $transactionIds,
                'period' => $period
            ])
        ]);

        DB::commit();

        return [
            'success' => true,
            'message' => "{$transactionCount} transactions supprimées avec succès",
            'count' => $transactionCount
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        LogFacade::error('Erreur softDeleteTransactionsByPeriod: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la suppression des transactions',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Restaurer une transaction supprimée logiquement
 *
 * @param string $transactionUid
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function restoreTransaction(string $transactionUid, int $userId, bool $isAdmin = false)
{
    try {
        // Récupérer la transaction supprimée
        $query = Transaction::withTrashed()->where('transaction_uid', $transactionUid);

        // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }

        $transaction = $query->first();

        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction introuvable'
            ];
        }

        if (!$transaction->trashed()) {
            return [
                'success' => false,
                'message' => 'Cette transaction n\'est pas supprimée'
            ];
        }

        // Restaurer la transaction
        $transaction->restore();

        // Journaliser l'action
        Log::create([
            'user_id' => $userId,
            'action' => 'restore_transaction',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'description' => "Restauration de la transaction #{$transaction->transaction_uid}",
        ]);

        // Recharger les relations
        $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider', 'paymentMean']);

        return [
            'success' => true,
            'message' => 'Transaction restaurée avec succès',
            'transaction' => $transaction
        ];
    } catch (\Exception $e) {
        LogFacade::error('Erreur restoreTransaction: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la restauration de la transaction',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Restaurer les transactions supprimées logiquement pour une période donnée
 *
 * @param array $period
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function restoreTransactionsByPeriod(array $period, int $userId, bool $isAdmin = false)
{
    try {
        DB::beginTransaction();

        // Construire la requête
        $query = Transaction::withTrashed()
            ->whereBetween('transaction_date', [$period['start_date'], $period['end_date']])
            ->whereNotNull('deleted_at'); // Seulement les transactions supprimées

        // Si ce n'est pas un admin, restreindre aux transactions de l'utilisateur
        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }

        // Filtrer par statut si spécifié
        if (isset($period['status_ids']) && is_array($period['status_ids'])) {
            $query->whereIn('payment_status_id', $period['status_ids']);
        }

        // Récupérer les IDs des transactions avant de les restaurer pour la journalisation
        $transactionIds = $query->pluck('id')->toArray();
        $transactionCount = count($transactionIds);

        if ($transactionCount === 0) {
            return [
                'success' => false,
                'message' => 'Aucune transaction supprimée trouvée pour cette période'
            ];
        }

        // Effectuer la restauration
        $query->restore();

        // Journaliser l'action
        Log::create([
            'user_id' => $userId,
            'action' => 'restore_transactions_period',
            'description' => "Restauration de {$transactionCount} transactions pour la période du {$period['start_date']} au {$period['end_date']}",
            'metadata' => json_encode([
                'transaction_ids' => $transactionIds,
                'period' => $period
            ])
        ]);

        DB::commit();

        return [
            'success' => true,
            'message' => "{$transactionCount} transactions restaurées avec succès",
            'count' => $transactionCount
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        LogFacade::error('Erreur restoreTransactionsByPeriod: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la restauration des transactions',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Suppression définitive d'une transaction
 *
 * @param string $transactionUid
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function forceDeleteTransaction(string $transactionUid, int $userId, bool $isAdmin = false)
{
    try {
        // Cette opération ne devrait être autorisée que pour les administrateurs
        if (!$isAdmin) {
            return [
                'success' => false,
                'message' => 'Opération non autorisée'
            ];
        }

        // Récupérer la transaction (même supprimée logiquement)
        $transaction = Transaction::withTrashed()->where('transaction_uid', $transactionUid)->first();

        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction introuvable'
            ];
        }

        $transactionId = $transaction->id;
        $transactionUid = $transaction->transaction_uid;

        // Sauvegarder des informations sur la transaction avant de la supprimer
        $transactionInfo = [
            'id' => $transactionId,
            'uid' => $transactionUid,
            'amount' => $transaction->amount,
            'transaction_date' => $transaction->transaction_date,
            'user_id' => $transaction->user_id,
            'card_id' => $transaction->card_id
        ];

        // Effectuer la suppression définitive
        $transaction->forceDelete();

        // Journaliser l'action
        Log::create([
            'user_id' => $userId,
            'action' => 'force_delete_transaction',
            'description' => "Suppression définitive de la transaction #{$transactionUid}",
            'metadata' => json_encode($transactionInfo)
        ]);

        return [
            'success' => true,
            'message' => 'Transaction définitivement supprimée'
        ];
    } catch (\Exception $e) {
        LogFacade::error('Erreur forceDeleteTransaction: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la suppression définitive de la transaction',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Suppression définitive des transactions pour une période donnée
 *
 * @param array $period
 * @param int $userId
 * @param bool $isAdmin
 * @return array
 */
public function forceDeleteTransactionsByPeriod(array $period, int $userId, bool $isAdmin = false)
{
    try {
        // Cette opération ne devrait être autorisée que pour les administrateurs
        if (!$isAdmin) {
            return [
                'success' => false,
                'message' => 'Opération non autorisée'
            ];
        }

        DB::beginTransaction();

        // Construire la requête
        $query = Transaction::withTrashed()
            ->whereBetween('transaction_date', [$period['start_date'], $period['end_date']]);

        // Filtrer par statut si spécifié
        if (isset($period['status_ids']) && is_array($period['status_ids'])) {
            $query->whereIn('payment_status_id', $period['status_ids']);
        }

        // Récupérer les informations des transactions avant de les supprimer pour la journalisation
        $transactions = $query->get();
        $transactionCount = $transactions->count();

        if ($transactionCount === 0) {
            return [
                'success' => false,
                'message' => 'Aucune transaction trouvée pour cette période'
            ];
        }

        // Sauvegarder les informations essentielles des transactions
        $transactionsInfo = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'uid' => $transaction->transaction_uid,
                'amount' => $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'user_id' => $transaction->user_id,
                'card_id' => $transaction->card_id
            ];
        })->toArray();

        // Effectuer la suppression définitive
        foreach ($transactions as $transaction) {
            $transaction->forceDelete();
        }

        // Journaliser l'action
        Log::create([
            'user_id' => $userId,
            'action' => 'force_delete_transactions_period',
            'description' => "Suppression définitive de {$transactionCount} transactions pour la période du {$period['start_date']} au {$period['end_date']}",
            'metadata' => json_encode([
                'transactions_info' => $transactionsInfo,
                'period' => $period
            ])
        ]);

        DB::commit();

        return [
            'success' => true,
            'message' => "{$transactionCount} transactions définitivement supprimées",
            'count' => $transactionCount
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        LogFacade::error('Erreur forceDeleteTransactionsByPeriod: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la suppression définitive des transactions',
            'errors' => [
                'exception' => $e->getMessage()
            ]
        ];
    }
}



}
