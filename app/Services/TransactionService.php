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
use App\Services\OrangeMoneyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Crypt;
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

    public function processDeposit(array $data)
{
    try {
        DB::beginTransaction();

        // 1) Vérifs carte & moyen de paiement
        $card = Card::where('id', $data['card_id'])
            ->where('user_id', $data['user_id'])
            ->first();

        if (!$card) {
            return ['success' => false, 'message' => 'Carte introuvable'];
        }
        if ($card->status !== 'activated') {
            return ['success' => false, 'message' => 'Cette carte n\'est pas active et ne peut pas recevoir de dépôt'];
        }

        $paymentMean = PaymentMean::where('id', $data['payment_mean_id'])
            ->where('user_id', $data['user_id'])
            ->first();

        if (!$paymentMean) {
            return ['success' => false, 'message' => 'Moyen de paiement introuvable'];
        }
        if ($paymentMean->status !== 'active') {
            return ['success' => false, 'message' => 'Ce moyen de paiement n\'est pas actif'];
        }

        $transactionType = TransactionType::where('name', 'deposit')->first();
        if (!$transactionType) {
            return ['success' => false, 'message' => 'Type de transaction introuvable'];
        }

        $pendingStatus = PaymentStatus::where('name', 'pending')->first();
        if (!$pendingStatus) {
            return ['success' => false, 'message' => 'Statut de paiement introuvable'];
        }

        // 2) Création transaction PENDING
        $transaction = new Transaction([
            'card_id'             => $card->id,
            'user_id'             => $data['user_id'],
            'amount'              => $data['amount'],
            'transaction_date'    => now(),
            'transaction_type_id' => $transactionType->id,
            'payment_mean_id'     => $paymentMean->id,
            'provider_id'         => null,
            'payment_status_id'   => $pendingStatus->id,
            'previous_balance'    => $card->balance,
            'current_balance'     => $card->balance,
            'transaction_uid'     => Str::uuid(),
            'description'         => $data['description'] ?? 'Dépôt sur la carte',
            'metadata'            => $data['metadata'] ?? null,
        ]);
        $transaction->save();

        // 3) Brancher selon le provider choisi
        // Adapte ce test selon ton schéma (ex: $paymentMean->provider ou $paymentMean->type)
        $provider = strtolower($paymentMean->paymentType->name ?? '');


        if ($provider === 'wave') {
            // === WAVE CHECKOUT ===
            /** @var \App\Services\WaveService $wave */
            $wave = app(\App\Services\WaveService::class);

            // On passe une référence client = transaction_uid pour faire le matching plus tard
            $checkout = $wave->createCheckoutSession(
                $data['amount'],
                $transaction->transaction_uid // client_reference
            );

            // Stocker quelques infos Wave dans metadata
            $meta = (array)($transaction->metadata ?? []);
            $meta['wave'] = [
                'checkout_id'     => $checkout['id'] ?? null,
                'transaction_id'  => $checkout['transaction_id'] ?? null,
                'wave_launch_url' => $checkout['wave_launch_url'] ?? null,
                'when_expires'    => $checkout['when_expires'] ?? null,
            ];
            $transaction->metadata = $meta;
            $transaction->save();

            DB::commit();

            // IMPORTANT : On NE TOUCHE PAS au solde ici.
            // Le solde sera augmenté dans le webhook "checkout.payment_succeeded".
            return [
                'success'       => true,
                'provider'      => 'wave',
                'status'        => 'pending',
                'redirect_url'  => $checkout['wave_launch_url'] ?? null,
                'transaction'   => $transaction->load(['transactionType', 'paymentStatus', 'card']),
                'message'       => 'Session de paiement Wave créée. Redirigez l’utilisateur vers redirect_url.'
            ];
        }

        if ($provider === 'orange_money' || $provider === 'om' || $provider === 'orangemoney') {
            // === ORANGE MONEY (ton code existant) ===
            $orangeMoneyService = new OrangeMoneyService();
            $omCashInData = [
                "amount" => $data["amount"],
                "customer" => [
                    "id" => $data["user_id"],
                    "idType" => "MSISDN",
                    "walletType" => "MSI"
                ],
                "metadata" => [
                    "transaction_uid" => $transaction->transaction_uid,
                    "description" => $data["description"] ?? "Dépôt sur la carte",
                ],
                "partner" => [
                    "id" => $orangeMoneyService->config["merchant_id"],
                    "idType" => "MSISDN",
                    "walletType" => "MSI"
                ],
                "receiveNotification" => true,
                "reference" => $transaction->id,
                "requestDate" => now()->toIso8601String(),
            ];

            try {
                $apiResponse = $orangeMoneyService->processCashIn($omCashInData);
                if (isset($apiResponse["status"]) && $apiResponse["status"] === "SUCCESS") {
                    // Paiement OM confirmé synchronement → on crédite tout de suite
                    $card->balance += $data['amount'];
                    $card->save();
                    $paidStatus = PaymentStatus::where('name', 'paid')->first();
                    $transaction->payment_status_id = $paidStatus->id;
                    $transaction->current_balance = $card->balance;

                    $meta = (array)($transaction->metadata ?? []);
                    $meta['om_response'] = $apiResponse;
                    $transaction->metadata = $meta;
                    $transaction->save();

                    Log::create([
                        'user_id'     => $data['user_id'],
                        'action'      => 'deposit',
                        'entity_type' => 'transaction',
                        'entity_id'   => $transaction->id,
                        'description' => "Dépôt de {$data['amount']} FCFA sur la carte #{$card->card_number}",
                    ]);

                    DB::commit();

                    $transaction->load(['transactionType', 'paymentStatus', 'card']);
                    return [
                        'success'     => true,
                        'provider'    => 'orange_money',
                        'status'      => 'paid',
                        'transaction' => $transaction
                    ];
                } else {
                    LogFacade::error("Orange Money Cash In API Error: " . json_encode($apiResponse));
                    $failedStatus = PaymentStatus::where('name', 'failed')->first();
                    $transaction->payment_status_id = $failedStatus->id;
                    $transaction->save();
                    DB::commit();
                    return ['success' => false, 'message' => 'Le paiement a échoué (OM)'];
                }
            } catch (\Exception $e) {
                LogFacade::error("Erreur lors de l'appel à OrangeMoneyService (Cash In): " . $e->getMessage());
                $failedStatus = PaymentStatus::where('name', 'failed')->first();
                $transaction->payment_status_id = $failedStatus->id;
                $transaction->save();
                DB::commit();
                return ['success' => false, 'message' => 'Erreur de paiement (OM)'];
            }
        }

        // Provider inconnu
        DB::rollBack();
        return ['success' => false, 'message' => 'Provider de paiement non supporté'];

    } catch (\Exception $e) {
        DB::rollBack();
        LogFacade::error('Erreur processDeposit: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors du traitement du dépôt',
            'errors'  => ['exception' => $e->getMessage()]
        ];
    }
}


//   public function processPayment(array $data)
// {
//     try {
//         DB::beginTransaction();

//         // 1) Vérifs de base
//         $card = Card::where('id', $data['card_id'])
//             ->where('user_id', $data['user_id'])
//             ->first();
//         if (!$card) return ['success' => false, 'message' => 'Carte introuvable'];
//         if ($card->status !== 'activated') return ['success' => false, 'message' => 'Carte inactive'];

//         $user = User::find($data['user_id']);
//         if (!$user) return ['success' => false, 'message' => 'Utilisateur introuvable'];

//         if ($user->verification_code !== ($data['verification_code'] ?? null)) {
//             return ['success' => false, 'message' => 'Code de vérification incorrect'];
//         }

//         $provider = Provider::where('id', $data['provider_id'])
//             ->where('status', 'active')
//             ->first();
//         if (!$provider) return ['success' => false, 'message' => 'Prestataire introuvable ou inactif'];

//         if ($card->balance < $data['amount']) {
//             return ['success' => false, 'message' => 'Solde insuffisant'];
//         }

//         $transactionType = TransactionType::where('name', 'payment')->first();
//         $pendingStatus   = PaymentStatus::where('name', 'pending')->first();
//         if (!$transactionType || !$pendingStatus) {
//             return ['success' => false, 'message' => 'Type ou statut de transaction introuvable'];
//         }

//         $paymentMean = PaymentMean::where('id', $data['payment_mean_id'])
//             ->where('user_id', $data['user_id'])
//             ->first();
//         if (!$paymentMean) return ['success' => false, 'message' => 'Moyen de paiement introuvable'];

//         $provider = strtolower($paymentMean->paymentType->name ?? '');
//         // 2) Transaction en pending
//         $transaction = new Transaction([
//             'card_id'             => $card->id,
//             'user_id'             => $data['user_id'],
//             'amount'              => (int) $data['amount'],
//             'transaction_date'    => now(),
//             'transaction_type_id' => $transactionType->id,
//             'payment_mean_id'     => $paymentMean->id,
//             'provider_id'         => $provider->id,
//             'payment_status_id'   => $pendingStatus->id,
//             'previous_balance'    => $card->balance,
//             'current_balance'     => $card->balance,
//             'transaction_uid'     => Str::uuid(),
//             'description'         => $data['description'] ?? 'Paiement santé',
//             'metadata'            => $data['metadata'] ?? null,
//         ]);
//         $transaction->save();

//         // 3) Traitement par provider
//         if ($providerCode === 'wave') {
//             /** @var \App\Services\WaveService $wave */
//             $wave = app(\App\Services\WaveService::class);
//             $checkout = $wave->createCheckoutSession(
//                 $data['amount'],
//                 $transaction->transaction_uid
//             );

//             $meta = (array)($transaction->metadata ?? []);
//             $meta['wave'] = [
//                 'checkout_id'     => $checkout['id'] ?? null,
//                 'transaction_id'  => $checkout['transaction_id'] ?? null,
//                 'wave_launch_url' => $checkout['wave_launch_url'] ?? null,
//                 'when_expires'    => $checkout['when_expires'] ?? null,
//             ];
//             $transaction->metadata = $meta;
//             $transaction->save();

//             DB::commit();

//             return [
//                 'success'      => true,
//                 'provider'     => 'wave',
//                 'status'       => 'pending',
//                 'redirect_url' => $checkout['wave_launch_url'] ?? null,
//                 'transaction'  => $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider']),
//                 'message'      => 'Paiement Wave initié. Redirigez l’utilisateur vers redirect_url.',
//             ];
//         }

//         if (in_array($providerCode, ['orange_money', 'om', 'orangemoney'])) {
//             // Normalisation MSISDN
//             $rawMsisdn = $paymentMean->msisdn ?? $paymentMean->phone ?? $user->phone ?? $card->phone;
//             $msisdn    = $rawMsisdn ? preg_replace('/\D/', '', $rawMsisdn) : null;
//             if ($msisdn && strlen($msisdn) === 9 && preg_match('/^(70|75|76|77|78)\d{7}$/', $msisdn)) {
//                 $msisdn = '221' . $msisdn;
//             }
//             if (!$msisdn || !(strlen($msisdn) === 12 && str_starts_with($msisdn, '221'))) {
//                 return ['success' => false, 'message' => 'MSISDN invalide: format attendu 221XXXXXXXXX.'];
//             }

//             $orangeMoneyService = new OrangeMoneyService();
//             $omPaymentData = [
//                 'amount'      => (int) $data['amount'],
//                 'customer_id' => $msisdn,
//                 'metadata'    => [
//                     'order_id'    => (string) $transaction->transaction_uid,
//                     'reference'   => (string) $transaction->id,
//                     'description' => $data['description'] ?? 'Paiement santé',
//                 ],
//                 'code'     => $orangeMoneyService->config['merchant_id'],
//                 'name'     => $orangeMoneyService->config['merchant_name'] ?? 'Default Merchant Name',
//                 'validity' => 86400,
//             ];

//             $apiResponse = $orangeMoneyService->initiatePayment($omPaymentData);

//             if (isset($apiResponse['qrCode']) || isset($apiResponse['deeplink'])) {
//                 $transaction->metadata = array_merge((array) $transaction->metadata, ['om_response' => $apiResponse]);
//                 $transaction->save();
//                 DB::commit();

//                 return [
//                     'success'      => true,
//                     'provider'     => 'orange_money',
//                     'status'       => 'pending',
//                     'transaction'  => $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider']),
//                     'qr_code_data' => $apiResponse,
//                 ];
//             }

//             // Sinon échec
//             if ($failed = PaymentStatus::where('name', 'failed')->first()) {
//                 $transaction->payment_status_id = $failed->id;
//                 $transaction->save();
//             }
//             DB::commit();
//             return ['success' => false, 'message' => 'Échec de la génération du QR Code Orange Money.', 'errors' => $apiResponse];
//         }

//         // Provider non supporté
//         DB::rollBack();
//         return ['success' => false, 'message' => 'Provider de paiement non supporté'];

//     } catch (\Throwable $e) {
//         DB::rollBack();
//         LogFacade::error('Erreur processPayment: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
//         return [
//             'success' => false,
//             'message' => 'Une erreur est survenue lors du traitement du paiement',
//             'errors'  => ['exception' => $e->getMessage()],
//         ];
//     }
// }

public function processPayment(array $data): array
    {
        DB::beginTransaction();
       try{
       // Utilisateur
            $user = User::find($data['user_id'] ?? null);
            if (!$user) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Utilisateur introuvable'];
            }

            // Carte: id + user_id
            $card = Card::where('id', $data['card_id'] ?? null)
                ->where('user_id', $user->id)
                ->first();

            if (!$card) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Carte introuvable'];
            }

            // Statut carte: on garde ta convention "status === 'activated'"
            $isActive = (isset($card->status) && strtolower((string)$card->status) === 'activated');
            if (!$isActive) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Carte inactive'];
            }

            // Vérification du code utilisateur (comme ton ancienne méthode)
            if ($user->verification_code !== ($data['verification_code'] ?? null)) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Code de vérification incorrect'];
            }

            // Prestataire: ne filtre QUE sur status='active' (la colonne active n'existe pas en DB)
            $provider = Provider::where('id', $data['provider_id'] ?? null)
                ->where('status', 'active')
                ->first();

            if (!$provider) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Prestataire introuvable ou inactif'];
            }

            // Montant & solde
            $amount = (int)($data['amount'] ?? 0);
            if ($amount <= 0) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Montant invalide'];
            }
            if ($card->balance < $amount) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Solde insuffisant'];
            }

            $transactionType = TransactionType::where('name', 'payment')->first();
            $pendingStatus   = PaymentStatus::where('name', 'pending')->first();
            if (!$transactionType || !$pendingStatus) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Type ou statut de transaction introuvable'];
            }

            $paymentMean = PaymentMean::where('id', $data['payment_mean_id'] ?? null)
                ->where('user_id', $user->id)
                ->first();

            if (!$paymentMean) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Moyen de paiement introuvable'];
            }

            // 2) Déterminer le "code" du provider à partir du type de moyen de paiement
            //    (ex: paymentType->name = 'Wave' => 'wave', 'Orange_Money' => 'orange_money')
            $paymentTypeName = optional($paymentMean->paymentType)->name;
            $providerCode    = strtolower($paymentTypeName ?? '');
            // petit mapping si besoin
            $map = [
                'om'           => 'orange_money',
                'orangemoney'  => 'orange_money',
                'orange money' => 'orange_money',
                'wave sn'      => 'wave',
            ];
            if (isset($map[$providerCode])) {
                $providerCode = $map[$providerCode];
            }

            if (!in_array($providerCode, ['wave', 'orange_money'])) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Provider de paiement non supporté'];
            }

            // 3) Créer la transaction en "pending"
            $transaction = new Transaction([
                'card_id'             => $card->id,
                'user_id'             => $user->id,
                'amount'              => $amount,
                'transaction_date'    => now(),
                'transaction_type_id' => $transactionType->id,
                'payment_mean_id'     => $paymentMean->id,
                'provider_id'         => $provider->id, // ATTENTION: provider objet Eloquent
                'payment_status_id'   => $pendingStatus->id,
                'previous_balance'    => $card->balance,
                'current_balance'     => $card->balance, // on débitera au succès
                'transaction_uid'     => (string) Str::uuid(),
                'description'         => $data['description'] ?? 'Paiement santé',
                'metadata'            => $data['metadata'] ?? null,
            ]);
            $transaction->save();

            // 4) Dispatch par provider
            if ($providerCode === 'wave') {
                /** @var \App\Services\WaveService $wave */
                $wave = app(\App\Services\WaveService::class);

                // createCheckoutSession(montant, référence)
                $checkout = $wave->createCheckoutSession(
                    $amount,
                    (string) $transaction->transaction_uid
                );

                // Sauvegarder métadonnées Wave
                $meta = (array)($transaction->metadata ?? []);
                $meta['wave'] = [
                    'checkout_id'     => $checkout['id'] ?? null,
                    'transaction_id'  => $checkout['transaction_id'] ?? null,
                    'wave_launch_url' => $checkout['wave_launch_url'] ?? null,
                    'when_expires'    => $checkout['when_expires'] ?? null,
                ];
                $transaction->metadata = $meta;
                $transaction->save();

                DB::commit();

                return [
                    'success'      => true,
                    'provider'     => 'wave',
                    'status'       => 'pending',
                    'redirect_url' => $checkout['wave_launch_url'] ?? null,
                    'transaction'  => $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider']),
                    'message'      => 'Paiement Wave initié. Redirigez l’utilisateur vers redirect_url.',
                ];
            }

            if ($providerCode === 'orange_money') {
                // Normalisation du MSISDN
                $rawMsisdn = $paymentMean->msisdn
                    ?? $paymentMean->phone
                    ?? $user->phone
                    ?? $card->phone;

                $msisdn = $rawMsisdn ? preg_replace('/\D/', '', $rawMsisdn) : null;

                // 9 chiffres commençant par 70/75/76/77/78 => on préfixe 221
                if ($msisdn && strlen($msisdn) === 9 && preg_match('/^(70|75|76|77|78)\d{7}$/', $msisdn)) {
                    $msisdn = '221' . $msisdn;
                }

                if (!$msisdn || !(strlen($msisdn) === 12 && str_starts_with($msisdn, '221'))) {
                    DB::rollBack();
                    return ['success' => false, 'message' => 'MSISDN invalide: format attendu 221XXXXXXXXX.'];
                }

                $orangeMoneyService = app(\App\Services\OrangeMoneyService::class);

                $omPaymentData = [
                    'amount'      => $amount,
                    'customer_id' => $msisdn,
                    'metadata'    => [
                        'order_id'    => (string) $transaction->transaction_uid,
                        'reference'   => (string) $transaction->id,
                        'description' => $data['description'] ?? 'Paiement santé',
                    ],
                    'code'     => $orangeMoneyService->config['merchant_id'] ?? null,
                    'name'     => $orangeMoneyService->config['merchant_name'] ?? 'Default Merchant Name',
                    'validity' => 86400,
                ];

                $apiResponse = $orangeMoneyService->initiatePayment($omPaymentData);

                if (isset($apiResponse['qrCode']) || isset($apiResponse['deeplink'])) {
                    $transaction->metadata = array_merge(
                        (array) $transaction->metadata,
                        ['om_response' => $apiResponse]
                    );
                    $transaction->save();

                    DB::commit();

                    return [
                        'success'      => true,
                        'provider'     => 'orange_money',
                        'status'       => 'pending',
                        'transaction'  => $transaction->load(['transactionType', 'paymentStatus', 'card', 'provider']),
                        'qr_code_data' => $apiResponse,
                    ];
                }

                // Échec génération QR/deeplink
                if ($failed = PaymentStatus::where('name', 'failed')->first()) {
                    $transaction->payment_status_id = $failed->id;
                    $transaction->save();
                }

                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Échec de la génération du QR Code Orange Money.',
                    'errors'  => $apiResponse,
                ];
            }

            // Si on arrive ici: pas supporté (sécurité)
            DB::rollBack();
            return ['success' => false, 'message' => 'Provider de paiement non supporté'];

        } catch (\Throwable $e) {
            DB::rollBack();
            LogFacade::error('Erreur processPayment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors du traitement du paiement',
                'errors'  => ['exception' => $e->getMessage()],
            ];
        }
    }





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
            $query = Transaction::where('transaction_uid', $transactionUid);
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
            $cancelableStatuses = PaymentStatus::whereIn('name', ['pending', 'processing'])->pluck('id')->toArray();
            if (!in_array($transaction->payment_status_id, $cancelableStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Cette transaction ne peut pas être annulée'
                ];
            }
            $maxCancellationTime = Carbon::now()->subMinutes(30);
            if ($transaction->created_at < $maxCancellationTime && !$isAdmin) {
                return [
                    'success' => false,
                    'message' => 'Le délai d\'annulation est dépassé'
                ];
            }
            $canceledStatus = PaymentStatus::where('name', 'cancelled')->first();
            if (!$canceledStatus) {
                return [
                    'success' => false,
                    'message' => 'Statut d\'annulation introuvable'
                ];
            }
            if ($transaction->current_balance != $transaction->previous_balance) {
                $card = $transaction->card;
                if ($transaction->transactionType->is_credit) {
                    $card->balance -= $transaction->amount;
                } else {
                    $card->balance += $transaction->amount;
                }
                $card->save();
            }
            $transaction->payment_status_id = $canceledStatus->id;
            $transaction->save();
            Log::create([
                'user_id' => $userId,
                'action' => 'cancel_transaction',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => "Annulation de la transaction {$transactionUid}",
            ]);
            DB::commit();
            return [
                'success' => true,
                'message' => 'Transaction annulée avec succès',
                'transaction' => $transaction
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            LogFacade::error('Erreur cancelTransaction: ' . $e->getMessage());
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
     * Mettre à jour le statut d'une transaction
     *
     * @param string $transactionUid
     * @param int $newStatusId
     * @param int|null $userId
     * @param bool $isAdmin
     * @return array
     */
    public function updateTransactionStatus(string $transactionUid, int $newStatusId, ?int $userId = null, bool $isAdmin = false)
    {
        try {
            DB::beginTransaction();
            $query = Transaction::where('transaction_uid', $transactionUid);
            if (!$isAdmin && $userId !== null) {
                $query->where('user_id', $userId);
            }
            $transaction = $query->first();
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction introuvable'
                ];
            }
            $newStatus = PaymentStatus::find($newStatusId);
            if (!$newStatus) {
                return [
                    'success' => false,
                    'message' => 'Nouveau statut de paiement introuvable'
                ];
            }
            $transaction->payment_status_id = $newStatusId;
            $transaction->save();
            Log::create([
                'user_id' => $userId ?? ($isAdmin ? null : $transaction->user_id),
                'action' => 'update_transaction_status',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => "Statut de la transaction {$transactionUid} mis à jour vers {$newStatus->name}",
            ]);
            DB::commit();
            return [
                'success' => true,
                'message' => 'Statut de la transaction mis à jour avec succès',
                'transaction' => $transaction->load('paymentStatus')
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
     * Appliquer les filtres à la requête
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyFilters($query, array $filters)
    {
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                    ->orWhere('transaction_uid', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('provider', function ($q) use ($search) {
                        $q->where('structure_name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('paymentMean', function ($q) use ($search) {
                        $q->where('account_identifier', 'like', '%' . $search . '%');
                    });
            });
        }
        if (isset($filters['user_id']) && !empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['card_id']) && !empty($filters['card_id'])) {
            $query->where('card_id', $filters['card_id']);
        }
        if (isset($filters['transaction_type_id']) && !empty($filters['transaction_type_id'])) {
            $query->where('transaction_type_id', $filters['transaction_type_id']);
        }
        if (isset($filters['provider_id']) && !empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }
        if (isset($filters['payment_mean_id']) && !empty($filters['payment_mean_id'])) {
            $query->where('payment_mean_id', $filters['payment_mean_id']);
        }
        if (isset($filters['payment_status_id']) && !empty($filters['payment_status_id'])) {
            $query->where('payment_status_id', $filters['payment_status_id']);
        }
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date']);
        }
        if (isset($filters['min_amount']) && !empty($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount']) && !empty($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }
        if (isset($filters['sort_by']) && !empty($filters['sort_by'])) {
            $sortDirection = isset($filters['sort_direction']) && in_array(strtolower($filters['sort_direction']), ['asc', 'desc']) ? $filters['sort_direction'] : 'desc';
            $query->orderBy($filters['sort_by'], $sortDirection);
        } else {
            $query->orderBy('transaction_date', 'desc');
        }
        return $query;
    }

    /**
     * Exporter les transactions en PDF
     *
     * @param array $filters
     * @return string Chemin du fichier PDF généré
     */
    public function exportTransactionsPdf(array $filters = [])
    {
        $transactions = $this->applyFilters(Transaction::with(['user', 'card', 'transactionType', 'provider', 'paymentMean', 'paymentStatus']), $filters)->get();
        $pdf = Pdf::loadView('exports.transactions_pdf', compact('transactions'));
        $filename = 'transactions_' . Carbon::now()->format('Ymd_His') . '.pdf';
        $path = storage_path('app/public/' . $filename);
        $pdf->save($path);
        return $path;
    }

    /**
     * Exporter les transactions en Excel (XLSX)
     *
     * @param array $filters
     * @return string Chemin du fichier Excel généré
     */
    public function exportTransactionsExcel(array $filters = [])
    {
        $transactions = $this->applyFilters(Transaction::with(['user', 'card', 'transactionType', 'provider', 'paymentMean', 'paymentStatus']), $filters)->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'UID Transaction');
        $sheet->setCellValue('C1', 'Utilisateur');
        $sheet->setCellValue('D1', 'Carte');
        $sheet->setCellValue('E1', 'Montant');
        $sheet->setCellValue('F1', 'Date Transaction');
        $sheet->setCellValue('G1', 'Type Transaction');
        $sheet->setCellValue('H1', 'Prestataire');
        $sheet->setCellValue('I1', 'Moyen Paiement');
        $sheet->setCellValue('J1', 'Statut Paiement');
        $sheet->setCellValue('K1', 'Solde Avant');
        $sheet->setCellValue('L1', 'Solde Après');
        $sheet->setCellValue('M1', 'Description');
        $row = 2;
        foreach ($transactions as $transaction) {
            $sheet->setCellValue('A' . $row, $transaction->id);
            $sheet->setCellValue('B' . $row, $transaction->transaction_uid);
            $sheet->setCellValue('C' . $row, $transaction->user->first_name . ' ' . $transaction->user->last_name);
            $sheet->setCellValue('D' . $row, $transaction->card->card_number);
            $sheet->setCellValue('E' . $row, $transaction->amount);
            $sheet->setCellValue('F' . $row, $transaction->transaction_date->format('Y-m-d H:i:s'));
            $sheet->setCellValue('G' . $row, $transaction->transactionType->display_name);
            $sheet->setCellValue('H' . $row, $transaction->provider ? $transaction->provider->structure_name : 'N/A');
            $sheet->setCellValue('I' . $row, $transaction->paymentMean ? $transaction->paymentMean->paymentType->display_name . ' (' . $this->maskAccountIdentifier($transaction->paymentMean->account_identifier) . ')' : 'N/A');
            $sheet->setCellValue('J' . $row, $transaction->paymentStatus->display_name);
            $sheet->setCellValue('K' . $row, $transaction->previous_balance);
            $sheet->setCellValue('L' . $row, $transaction->current_balance);
            $sheet->setCellValue('M' . $row, $transaction->description);
            $row++;
        }
        $filename = 'transactions_' . Carbon::now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/public/' . $filename);
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        return $path;
    }

    /**
     * Masquer l'identifiant de compte pour l'affichage
     *
     * @param string $encryptedIdentifier
     * @return string
     */
    private function maskAccountIdentifier($encryptedIdentifier)
    {
        try {
            $identifier = Crypt::decryptString($encryptedIdentifier);
            $length = strlen($identifier);
            if ($length <= 4) {
                return str_repeat('*', $length);
            }
            return str_repeat('*', $length - 4) . substr($identifier, -4);
        } catch (\Exception $e) {
            return '*** Identifiant chiffré ***';
        }
    }

    /**
     * Fonction utilitaire pour appliquer les filtres aux requêtes de log
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyLogFilters($query, array $filters)
    {
        if (isset($filters['user_id']) && !empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['action']) && !empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (isset($filters['entity_type']) && !empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (isset($filters['entity_id']) && !empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%');
            });
        }
        if (isset($filters['sort_by']) && !empty($filters['sort_by'])) {
            $sortDirection = isset($filters['sort_direction']) && in_array(strtolower($filters['sort_direction']), ['asc', 'desc']) ? $filters['sort_direction'] : 'desc';
            $query->orderBy($filters['sort_by'], $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        return $query;
    }

    /**
     * Récupérer les logs
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getLogs(array $filters = [])
    {
        $query = Log::query();
        return $this->applyLogFilters($query, $filters)->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Récupérer un log par son ID
     *
     * @param int $id
     * @return Log|null
     */
    public function getLogById(int $id)
    {
        return Log::find($id);
    }

    /**
     * Supprimer un log
     *
     * @param int $id
     * @return bool
     */
    public function deleteLog(int $id)
    {
        $log = Log::find($id);
        if ($log) {
            return $log->delete();
        }
        return false;
    }

    /**
     * Vider tous les logs
     *
     * @return bool
     */
    public function clearAllLogs()
    {
        return Log::truncate();
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
            $query = Transaction::where('transaction_uid', $transactionUid);
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
            $pdf = PDF::loadView('receipts.transaction', $data);
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
            $query = Transaction::where('transaction_date', '>=', $startDate)
                ->whereHas('paymentStatus', function ($q) {
                    $q->where('name', 'paid');
                });
            if ($userId) {
                $query->where('user_id', $userId);
            }
            $transactions = $query->with(['transactionType', 'provider'])->get();
            $totalDeposits = $transactions->filter(function ($t) {
                return $t->transactionType->is_credit;
            })->sum('amount');
            $totalPayments = $transactions->filter(function ($t) {
                return !$t->transactionType->is_credit;
            })->sum('amount');
            $byType = [];
            $transactionTypes = TransactionType::all()->keyBy('id');
            foreach ($transactions->groupBy('transaction_type_id') as $typeId => $typeTransactions) {
                $typeName = $transactionTypes[$typeId]->display_name ?? 'Inconnu';
                $byType[$typeName] = $typeTransactions->sum('amount');
            }
            $byProvider = [];
            $payments = $transactions->filter(function ($t) {
                return !$t->transactionType->is_credit && $t->provider_id;
            });
            foreach ($payments->groupBy('provider_id') as $providerId => $providerTransactions) {
                $providerName = $providerTransactions->first()->provider->structure_name ?? 'Inconnu';
                $byProvider[$providerName] = $providerTransactions->sum('amount');
            }
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
     * Récupérer le rapport quotidien
     *
     * @param string $date
     * @return array
     */
    public function getDailyReport(string $date)
    {
        try {
            $reportDate = Carbon::parse($date);
            $startDate = $reportDate->copy()->startOfDay();
            $endDate = $reportDate->copy()->endOfDay();
            $transactions = Transaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->with(['transactionType', 'paymentStatus', 'user', 'provider'])
                ->get();
            $paidTransactions = $transactions->filter(function ($t) {
                return $t->paymentStatus->name === 'paid';
            });
            $totalDeposits = $paidTransactions->filter(function ($t) {
                return $t->transactionType->is_credit;
            })->sum('amount');
            $totalPayments = $paidTransactions->filter(function ($t) {
                return !$t->transactionType->is_credit;
            })->sum('amount');
            $byStatus = [];
            foreach ($transactions->groupBy('payment_status_id') as $statusId => $statusTransactions) {
                $statusName = $statusTransactions->first()->paymentStatus->display_name;
                $byStatus[$statusName] = [
                    'count' => $statusTransactions->count(),
                    'amount' => $statusTransactions->sum('amount')
                ];
            }
            $byType = [];
            foreach ($transactions->groupBy('transaction_type_id') as $typeId => $typeTransactions) {
                $typeName = $typeTransactions->first()->transactionType->display_name;
                $byType[$typeName] = [
                    'count' => $typeTransactions->count(),
                    'amount' => $typeTransactions->sum('amount')
                ];
            }
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
            $reportDate = Carbon::parse($month . '-01');
            $startDate = $reportDate->copy()->startOfMonth();
            $endDate = $reportDate->copy()->endOfMonth();
            $transactions = Transaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->with(['transactionType', 'paymentStatus', 'user', 'provider'])
                ->get();
            $paidTransactions = $transactions->filter(function ($t) {
                return $t->paymentStatus->name === 'paid';
            });
            $totalDeposits = $paidTransactions->filter(function ($t) {
                return $t->transactionType->is_credit;
            })->sum('amount');
            $totalPayments = $paidTransactions->filter(function ($t) {
                return !$t->transactionType->is_credit;
            })->sum('amount');
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
            $query = Transaction::where('transaction_uid', $transactionUid);
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
            $deletableStatuses = PaymentStatus::whereIn('name', ['paid', 'cancelled', 'failed'])->pluck('id')->toArray();
            if (!in_array($transaction->payment_status_id, $deletableStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Cette transaction ne peut pas être supprimée car elle est en cours de traitement'
                ];
            }
            $transaction->delete();
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
            $query = Transaction::onlyTrashed();
            if (!$isAdmin || ($isAdmin && $userId)) {
                $query->where('user_id', $userId ?: auth()->id());
            }
            $relations = ['transactionType', 'paymentStatus', 'card', 'provider', 'paymentMean'];
            if ($isAdmin) {
                $relations[] = 'user';
            }
            $query->with($relations);
            $query = $this->applyFilters($query, $filters);
            $perPage = $filters['per_page'] ?? 10;
            $page = $filters['page'] ?? 1;
            $transactions = $query->paginate($perPage, ['*'], 'page', $page);
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
            $query = Transaction::whereBetween('transaction_date', [$period['start_date'], $period['end_date']]);
            if (!$isAdmin) {
                $query->where('user_id', $userId);
            }
            if (isset($period['status_ids']) && is_array($period['status_ids'])) {
                $query->whereIn('payment_status_id', $period['status_ids']);
            } else {
                $deletableStatuses = PaymentStatus::whereIn('name', ['paid', 'cancelled', 'failed'])->pluck('id')->toArray();
                $query->whereIn('payment_status_id', $deletableStatuses);
            }
            $transactionIds = $query->pluck('id')->toArray();
            $transactionCount = count($transactionIds);
            if ($transactionCount === 0) {
                return [
                    'success' => false,
                    'message' => 'Aucune transaction trouvée pour cette période'
                ];
            }
            $query->delete();
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
            $query = Transaction::withTrashed()->where('transaction_uid', $transactionUid);
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
            $transaction->restore();
            Log::create([
                'user_id' => $userId,
                'action' => 'restore_transaction',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'description' => "Restauration de la transaction #{$transaction->transaction_uid}",
            ]);
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
            $query = Transaction::withTrashed()
                ->whereBetween('transaction_date', [$period['start_date'], $period['end_date']])
                ->whereNotNull('deleted_at');
            if (!$isAdmin) {
                $query->where('user_id', $userId);
            }
            if (isset($period['status_ids']) && is_array($period['status_ids'])) {
                $query->whereIn('payment_status_id', $period['status_ids']);
            }
            $transactionIds = $query->pluck('id')->toArray();
            $transactionCount = count($transactionIds);
            if ($transactionCount === 0) {
                return [
                    'success' => false,
                    'message' => 'Aucune transaction supprimée trouvée pour cette période'
                ];
            }
            $query->restore();
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
            if (!$isAdmin) {
                return [
                    'success' => false,
                    'message' => 'Opération non autorisée'
                ];
            }
            $transaction = Transaction::withTrashed()->where('transaction_uid', $transactionUid)->first();
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction introuvable'
                ];
            }
            $transactionId = $transaction->id;
            $transactionUid = $transaction->transaction_uid;
            $transactionInfo = [
                'id' => $transactionId,
                'uid' => $transactionUid,
                'amount' => $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'user_id' => $transaction->user_id,
                'card_id' => $transaction->card_id
            ];
            $transaction->forceDelete();
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
            if (!$isAdmin) {
                return [
                    'success' => false,
                    'message' => 'Opération non autorisée'
                ];
            }
            DB::beginTransaction();
            $query = Transaction::withTrashed()
                ->whereBetween('transaction_date', [$period['start_date'], $period['end_date']]);
            if (isset($period['status_ids']) && is_array($period['status_ids'])) {
                $query->whereIn('payment_status_id', $period['status_ids']);
            }
            $transactions = $query->get();
            $transactionCount = $transactions->count();
            if ($transactionCount === 0) {
                return [
                    'success' => false,
                    'message' => 'Aucune transaction trouvée pour cette période'
                ];
            }
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
            foreach ($transactions as $transaction) {
                $transaction->forceDelete();
            }
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