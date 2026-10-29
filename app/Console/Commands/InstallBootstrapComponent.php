<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Cms\BootstrapComponents;
use Illuminate\Console\Command;

class InstallBootstrapComponent extends Command
{
    protected $signature = 'cms:install-bootstrap {component=container : Identifikátor predlohy} {--user= : E-mail vlastníka knižnice}';

    protected $description = 'Pridá hotový Bootstrap komponent do osobnej knižnice bez prepísania existujúcich úprav';

    public function handle(BootstrapComponents $components): int
    {
        $key = $this->argument('component');
        $email = strtolower(trim((string) $this->option('user')));
        $user = $email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        if (! isset(BootstrapComponents::DEFINITIONS[$key]) || ! $user) {
            $this->error('Zadajte známu predlohu a existujúci účet cez --user.');

            return self::FAILURE;
        }
        $component = $components->install($user, $key);
        $this->info('Komponent '.$component->name.' je dostupný. Existujúce úpravy zostali zachované.');

        return self::SUCCESS;
    }
}
