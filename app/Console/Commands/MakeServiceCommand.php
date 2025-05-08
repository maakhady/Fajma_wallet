<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    /**
     * La signature de la commande
     *
     * @var string
     */
    protected $signature = 'make:service {name : Le nom du service}';

    /**
     * La description de la commande
     *
     * @var string
     */
    protected $description = 'Crée un nouveau service';

    /**
     * Exécuter la commande
     *
     * @return int
     */
    public function handle()
    {
        // Récupérer le nom du service
        $name = $this->argument('name');
        
        // Créer le répertoire Services s'il n'existe pas
        if (!File::exists(app_path('Services'))) {
            File::makeDirectory(app_path('Services'));
        }
        
        // Vérifier si le service existe déjà
        $path = app_path("Services/{$name}.php");
        if (File::exists($path)) {
            $this->error("Le service {$name} existe déjà !");
            return 1;
        }
        
        // Générer le contenu du service
        $content = $this->getServiceTemplate($name);
        
        // Créer le fichier
        File::put($path, $content);
        
        $this->info("Service {$name} créé avec succès !");
        
        return 0;
    }
    
    /**
     * Obtenir le template du service
     *
     * @param string $name
     * @return string
     */
    protected function getServiceTemplate($name)
    {
        return <<<EOT
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class {$name}
{
    /**
     * Constructeur du service
     */
    public function __construct()
    {
        //
    }
    
    /**
     * Méthode d'exemple
     */
    public function example()
    {
        try {
            // Logique du service
            return true;
        } catch (\Exception \$e) {
            Log::error('{$name} error: ' . \$e->getMessage());
            throw \$e;
        }
    }
}
EOT;
    }
}