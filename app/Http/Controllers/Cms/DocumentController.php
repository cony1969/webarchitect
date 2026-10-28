<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\DocumentStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private DocumentStore $store) {}

    public function index(Request $request, string $kind)
    {
        return response()->json($this->store->query($kind, $request->user())->orderBy('position')->orderBy('id')->paginate(50));
    }

    public function show(Request $request, string $kind, string $id)
    {
        return response()->json(['data' => $this->store->query($kind, $request->user())->findOrFail($id)]);
    }

    public function store(Request $request, string $kind)
    {
        return $this->saved($request, $kind, $this->store->create($kind, $request->user(), $this->validated($request)), 201);
    }

    public function update(Request $request, string $kind, string $id)
    {
        return $this->saved($request, $kind, $this->store->update($kind, $request->user(), $id, $this->validated($request, true)));
    }

    public function copy(Request $request, string $kind, string $id)
    {
        return $this->saved($request, $kind, $this->store->copy($kind, $request->user(), $id, $this->validated($request)), 201);
    }

    private function saved(Request $request, string $kind, $document, int $status = 200)
    {
        $payload = ['data' => $document];
        if ($kind === 'projects') {
            $payload['project_tree'] = ProjectTreeController::snapshot($request->user());
        }

        return response()->json($payload, $status);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'], 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'document' => ['sometimes', 'array'], 'settings' => ['sometimes', 'array', 'max:100'],
            'revision' => [$updating ? 'required' : 'sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['draft', 'archived'])],
            'project_id' => ['sometimes', 'nullable', 'uuid'], 'domain_id' => ['sometimes', 'nullable', 'uuid'],
            'user_id' => ['prohibited'], 'owner_key' => ['prohibited'], 'id' => ['prohibited'],
        ]);
    }
}
