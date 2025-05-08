<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateUserContactEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-contact-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour le champ contact_email pour tous les utilisateurs qui ont ce champ null';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = User::whereNull('contact_email')->get();
        $count = $users->count();

        $this->info("Mise à jour de {$count} utilisateurs...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($users as $user) {
            $user->contact_email = $user->email;
            $user->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Tous les utilisateurs ont été mis à jour avec succès!');

        return Command::SUCCESS;
    }
}
