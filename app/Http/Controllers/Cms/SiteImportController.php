<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\AsusSiteAnalyzer;
use Illuminate\Http\Request;

class SiteImportController extends Controller
{
    public function __construct(private AsusSiteAnalyzer $analyzer) {}

    public function sources(Request $request)
    {
        return response()->json(['data' => $this->analyzer->sources($request->user())])->header('Cache-Control', 'private, no-store');
    }

    public function analyze(Request $request)
    {
        $this->analyzer->authorize($request->user());
        $data = $request->validate(['source_id' => ['required', 'string', 'max:253'], 'path' => ['prohibited'], 'root' => ['prohibited'], 'url' => ['prohibited']]);

        return response()->json(['data' => $this->analyzer->analyze($request->user(), $data['source_id'])])->header('Cache-Control', 'private, no-store');
    }
}
