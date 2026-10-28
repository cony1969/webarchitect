<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\ProjectTree;
use App\Services\Cms\SectionLibrary;
use Illuminate\Http\Request;

class LegacyTreeController extends Controller
{
    public function projects(Request $request, ProjectTree $tree)
    {
        $data = $request->validate([
            'user_id' => ['prohibited'], 'revision' => ['required', 'integer', 'min:1'], 'projects' => ['present', 'array', 'max:100'],
            'projects.*.id' => ['required', 'string', 'max:160', 'distinct:strict'], 'projects.*.name' => ['required', 'string', 'max:160'],
            'projects.*.domains' => ['sometimes', 'array', 'max:100'], 'projects.*.domains.*.id' => ['required', 'string', 'max:160', 'distinct:strict'],
            'projects.*.domains.*.name' => ['required', 'string', 'max:253'], 'projects.*.domains.*.pages' => ['sometimes', 'array', 'max:500'],
            'projects.*.domains.*.pages.*.id' => ['required', 'string', 'max:160', 'distinct:strict'], 'projects.*.domains.*.pages.*.name' => ['required', 'string', 'max:160'],
        ]);
        $revision = $tree->replace($request->user(), $data['projects'], $data['revision']);

        return response()->json(['success' => true] + ProjectTreeController::snapshot($request->user()));
    }

    public function layouts(Request $request, SectionLibrary $library)
    {
        $data = $request->validate([
            'user_id' => ['prohibited'], 'revision' => ['required', 'integer', 'min:1'], 'layouts' => ['present', 'array', 'max:500'],
            'layouts.*.id' => ['required', 'string', 'max:160', 'distinct:strict'], 'layouts.*.name' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['success' => true, 'revision' => $library->replace($request->user(), $data['layouts'], $data['revision'])]);
    }
}
