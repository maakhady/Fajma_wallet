<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentStatusService;

class InitializePaymentStatuses extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'payment:init-statuses';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Initialise les statuts de paiement par défaut';

    /**
     * Le service de statut de paiement.
     *
     * @var PaymentStatusService
     */
    protected $paymentStatusService;

    /**
     * Créer une nouvelle instance de commande.
     *
     * @param PaymentStatusService $paymentStatusService
     * @return void
     */
    public function __construct(PaymentStatusService $paymentStatusService)
    {
        parent::__construct();
        $this->paymentStatusService = $paymentStatusService;
    }

    /**
     * Exécuter la commande console.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Initialisation des statuts de paiement par défaut...');

        try {
            $statuses = $this->paymentStatusService->initializeDefaultStatuses();
            
            if (count($statuses) > 0) {
                $this->info(count($statuses) . ' statuts de paiement ont été créés:');
                
                foreach ($statuses as $status) {
                    $this->line("- {$status->display_name} ({$status->name})");
                }
            } else {
                $this->info('Tous les statuts de paiement par défaut existent déjà.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Erreur lors de l\'initialisation des statuts de paiement: ' . $e->getMessage());
            return 1;
        }
    }
}