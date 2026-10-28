<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\DomainStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainController extends Controller
{
    public function __construct(private DomainStore $store) {}

    public function index(Request $request)
    {
        return response()->json(['data' => $this->store->query($request->user())->orderBy('position')->get()->map(fn ($domain) => $this->store->data($domain))]);
    }

    public function show(Request $request, string $id)
    {
        return response()->json(['data' => $this->store->data($this->store->query($request->user())->findOrFail($id))]);
    }

    public function store(Request $request)
    {
        $request->validate(['project_id' => ['required', 'uuid']]);

        return $this->saved($request, $this->store->save($request->user(), $this->validated($request)), 201);
    }

    public function update(Request $request, string $id)
    {
        return $this->saved($request, $this->store->save($request->user(), $this->validated($request, true), $id));
    }

    public function copy(Request $request, string $id)
    {
        return $this->saved($request, $this->store->save($request->user(), $this->validated($request), $id, true), 201);
    }

    private function saved(Request $request, $domain, int $status = 200)
    {
        return response()->json(['data' => $this->store->data($domain), 'project_tree' => ProjectTreeController::snapshot($request->user())], $status);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:253'], 'project_id' => ['sometimes', 'uuid'],
            'revision' => [$updating ? 'required' : 'sometimes', 'integer', 'min:1'],
            'settings' => ['sometimes', 'array', 'max:100'], 'status' => ['sometimes', Rule::in(['draft', 'archived'])],
            'user_id' => ['prohibited'], 'owner_key' => ['prohibited'], 'id' => ['prohibited'],
        ]);
    }
}
