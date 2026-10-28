<?php

namespace App\Console\Commands;

use App\Services\Cms\LegacyImport;
use Illuminate\Console\Command;

class ImportLegacyCms extends Command
{
    protected $signature = 'cms:import-legacy {--path= : Cesta ku koreňu pôvodného config/ui}';

    protected $description = 'Jednorazový transakčný import pôvodných JSON dát CMS so zachovaním identifikátorov';

    public function handle(LegacyImport $import): int
    {
        $result = $import->run($this->option('path') ?: config_path('ui'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
