<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Project;
use App\Models\User;
use App\Services\Cms\ProjectTree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectTreeController extends Controller
{
    public function __invoke(Request $request)
    {
        return response()->json(self::snapshot($request->user()));
    }

    public static function snapshot(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $tree = app(ProjectTree::class);

            return [
                'projects' => $tree->read($user),
                'revision' => $tree->revision($user),
                'ids' => Project::where('user_id', $user->id)->pluck('id', 'legacy_id'),
            ];
        });
    }
}
