<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Cms\StarterLayouts;
use Illuminate\Console\Command;

class InstallStarterLayouts extends Command
{
    protected $signature = 'cms:install-layouts {--user= : E-mail vlastníka layoutov}';

    protected $description = 'Doplní päť prázdnych pomenovaných layoutov bez prepísania existujúcej práce';

    public function handle(StarterLayouts $layouts): int
    {
        $email = strtolower(trim((string) $this->option('user')));
        $user = $email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        if (! $user) {
            $this->error('Zadajte existujúci účet cez --user.');

            return self::FAILURE;
        }
        foreach ($layouts->install($user) as $name => $result) {
            $this->line($name.': '.$result);
        }

        return self::SUCCESS;
    }
}
