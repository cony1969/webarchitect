<?php

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ProjectConfigController extends Controller
{
    public function save(Request $request)
    {
        try {
            $baseDir = config_path('ui');
            $directoryPath = "{$baseDir}/content";
            $filePath = "{$directoryPath}/projects.json";

            if (!File::exists($directoryPath)) {
                File::makeDirectory($directoryPath, 0755, true, true);
            }

            // Načtení surového JSON obsahu z těla požadavku
            $jsonRaw = $request->getContent();
            $projects = json_decode($jsonRaw, true);

            if (!is_array($projects)) {
                $projects = [];
            }

            // Zápis validního naformátovaného JSON-u do souboru
            File::put($filePath, json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return response()->json([
                'success' => true,
                'message' => 'Projekty úspešne uložené do súboru.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nepodarilo sa uložiť súbor: ' . $e->getMessage()
            ], 500);
        }
    }
}
