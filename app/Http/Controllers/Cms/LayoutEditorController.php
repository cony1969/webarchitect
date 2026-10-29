<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\LayoutEditor;
use App\Services\Cms\PageComposition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LayoutEditorController extends Controller
{
    public function index(Request $request, LayoutEditor $editor): JsonResponse
    {
        return response()->json(['data' => $editor->catalog($request->user())]);
    }

    public function show(Request $request, LayoutEditor $editor, string $id): JsonResponse
    {
        return response()->json(['data' => $editor->data($request->user(), $editor->find($request->user(), $id))]);
    }

    public function preview(Request $request, PageComposition $composition): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'array'], 'user_id' => ['prohibited']]);

        return response()->json(['data' => $composition->resolve($request->user(), $data['document'], null)]);
    }

    public function save(Request $request, LayoutEditor $editor, ?string $id = null): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'document' => ['required', 'array'],
            'revision' => [$id ? 'required' : 'nullable', 'integer', 'min:1'], 'user_id' => ['prohibited'], 'project_id' => ['prohibited']]);
        $layout = $editor->save($request->user(), $data, $id);

        return response()->json(['data' => $editor->data($request->user(), $layout), 'catalog' => $editor->catalog($request->user())]);
    }
}
