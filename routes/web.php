<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use App\Models\UserSetting;
use App\Http\Controllers\UI\ProjectConfigController;
use App\Http\Controllers\UI\ComponentImportController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/reset-layout', [ProfileController::class, 'resetLayout'])->name('profile.reset-layout');

    Route::get('/sidebar/resizer-db-state', function () {
        $keys = [
            'sidebar_width',
            'block_top_height',
            'block_middle_height',
            'sidebar_right_width',
            'block_right_top_height',
        ];

        $state = [];
        if (auth()->check()) {
            $state = \DB::table('user_settings')
                ->where('user_id', auth()->id())
                ->whereIn('name', $keys)
                ->pluck('value', 'name')
                ->toArray();
        }

        return response()->json([
            'status' => 'ok',
            'db_state' => $state,
        ]);
    });

    Route::post('/sidebar/resize', function (Request $request) {
        $map = [
            'sidebar_width' => $request->json('width'),
            'block_top_height' => $request->json('top_height'),
            'block_middle_height' => $request->json('middle_height'),
            'sidebar_right_width' => $request->json('right_width'),
            'block_right_top_height' => $request->json('right_top_height'),
        ];

        $sessionUpdates = [];
        foreach ($map as $key => $value) {
            if ($value !== null) {
                $sessionUpdates[$key] = $value;
                UserSetting::set($key, $value);
            }
        }

        if (!empty($sessionUpdates)) {
            session($sessionUpdates);
        }

        return response()->json(['status' => 'saved']);
    });

    Route::post('/sidebar/save-setting', function (Request $request) {
        $name = $request->json('name');
        $value = $request->json('value');
        
        if ($name) {
            $finalValue = is_array($value) ? json_encode($value) : $value;
            UserSetting::set($name, $finalValue);
        }
    });

    Route::post('/ui/components/import-html', [ComponentImportController::class, 'import'])
        ->name('ui.components.import-html');

    Route::post('/ui/components/load', [ComponentImportController::class, 'load'])
        ->name('ui.components.load');
});

Route::post('/api/ui/save-projects', function (\Illuminate\Http\Request $request) {
    try {
        $baseDir = '/data/d/4/d4f1af97-73d7-4842-9f8a-d4679c7e0428/pokerclub.sk/sub/war/config/ui';
        $directoryPath = $baseDir . '/content';
        $filePath = $directoryPath . '/projects.json';

        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $allData = [];
        if (file_exists($filePath)) {
            $allData = json_decode(file_get_contents($filePath), true) ?: [];
            if (array_is_list($allData)) { 
                $allData = []; 
            }
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $userId = $payload['user_id'] ?? auth()->id(); 
        
        if (!$userId) {
            return response()->json(['success' => false, 'error' => 'User not found'], 401);
        }

        $userKey = 'user_' . $userId;
        $allData[$userKey] = isset($payload['projects']) ? $payload['projects'] : [];

        file_put_contents($filePath, json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/ui/save-layouts', function (\Illuminate\Http\Request $request) {
    try {
        $baseDir = '/data/d/4/d4f1af97-73d7-4842-9f8a-d4679c7e0428/pokerclub.sk/sub/war/config/ui';
        $directoryPath = $baseDir . '/content';
        $filePath = $directoryPath . '/layouts.json';

        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $layouts = json_decode($request->getContent(), true) ?: [];

        file_put_contents($filePath, json_encode($layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/profile/save-tabs', [App\Http\Controllers\ProfileController::class, 'saveTabs'])->name('profile.save-tabs');

require __DIR__.'/auth.php';
