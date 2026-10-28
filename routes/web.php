<?php

use App\Http\Controllers\Cms\DocumentController;
use App\Http\Controllers\Cms\LegacyTreeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UI\ComponentImportController;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/profile/save-tabs', [ProfileController::class, 'saveTabs'])->name('profile.save-tabs');
    Route::post('/api/ui/save-projects', [LegacyTreeController::class, 'projects']);
    Route::post('/api/ui/save-layouts', [LegacyTreeController::class, 'layouts']);
    Route::prefix('api/cms/{kind}')->where(['kind' => 'projects|components|sections|pages'])->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::get('/{id}', [DocumentController::class, 'show'])->whereUuid('id');
        Route::put('/{id}', [DocumentController::class, 'update'])->whereUuid('id');
        Route::post('/{id}/copy', [DocumentController::class, 'copy'])->whereUuid('id');
    });

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
            $state = DB::table('user_settings')
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

        if (! empty($sessionUpdates)) {
            session($sessionUpdates);
        }

        return response()->json(['status' => 'saved']);
    });

    Route::post('/sidebar/save-setting', function (Request $request) {
        $data = $request->validate([
            'name' => ['required', 'in:sidebar_active_tab,sidebar_open_category,sidebar_components_import_context,sidebar_projects_tree_position'],
            'value' => ['present'], 'user_id' => ['prohibited'],
        ]);
        $value = is_array($data['value']) ? json_encode($data['value'], JSON_THROW_ON_ERROR) : $data['value'];
        abort_unless(is_string($value) && strlen($value) <= 65536, 422);
        UserSetting::set($data['name'], $value);

        return response()->json(['status' => 'saved']);
    });

    Route::post('/ui/components/import-html', [ComponentImportController::class, 'import'])
        ->name('ui.components.import-html');

    Route::post('/ui/components/load', [ComponentImportController::class, 'load'])
        ->name('ui.components.load');
});

require __DIR__.'/auth.php';
