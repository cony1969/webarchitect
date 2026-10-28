<?php

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Cms\LegacyTreeController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Compatibility entry point; all writes use the authenticated CMS store. */
class ProjectConfigController extends Controller
{
    public function save(Request $request)
    {
        abort_unless($request->user(), 401);

        return app()->call([app(LegacyTreeController::class), 'projects'], ['request' => $request]);
    }
}
