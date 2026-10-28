<?php

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use App\Models\Cms\Component;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use App\Services\Cms\DocumentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComponentEditorController extends Controller
{
    public function catalog(Request $request, ComponentLibrary $library): JsonResponse
    {
        return response()->json(['catalog' => $library->catalog($request->user(), true)]);
    }

    public function save(Request $request, ComponentLibrary $library, DocumentStore $store): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['prohibited'], 'component_id' => ['nullable', 'uuid'],
            'revision' => ['nullable', 'integer', 'min:1'], 'as_new' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'target_path' => ['required', 'string', 'max:220', 'regex:#^[a-z0-9-]+(?:/[a-z0-9-]+)*$#'],
            'tab' => ['required', 'in:native,bootstrap,other'], 'category' => ['required', 'string', 'max:120'],
            'json_content' => ['required', 'string', 'max:2000000'],
        ]);
        $source = json_decode($data['json_content'], true);
        if (! is_array($source) || ! is_array($source['objects'] ?? null) || ! count($source['objects'])) {
            throw ValidationException::withMessages(['json_content' => 'JSON musí obsahovať platné objekty komponentu v položke objects.']);
        }
        foreach (['css', 'js'] as $field) {
            if (isset($source[$field]) && ! is_string($source[$field])) {
                throw ValidationException::withMessages(['json_content' => 'Položky css a js musia byť text.']);
            }
        }
        $component = DB::transaction(function () use ($request, $library, $store, $data, $source) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $original = isset($data['component_id']) ? $store->query('components', $request->user())->findOrFail($data['component_id']) : null;
            if (! $data['as_new']) {
                abort_unless($original, 422, 'Najprv vyberte existujúci komponent alebo použite Uložiť ako nový.');
                abort_unless((int) $original->user_id === (int) $request->user()->id, 403, 'Spoločnú predlohu uložte ako nový vlastný komponent.');
                abort_unless(isset($data['revision']) && $original->revision === $data['revision'], 409, 'Komponent medzitým zmenil iný editor. Načítajte ho znovu.');
                abort_unless($data['slug'] === $original->slug, 422, 'Pre nový slug použite Uložiť ako nový.');
            }
            $path = $data['target_path'].'/'.$data['slug'].'.json';
            $duplicate = Component::withTrashed()->where('user_id', $request->user()->id)
                ->where(fn ($q) => $q->where('source_key', $path)->orWhere(fn ($q) => $q->where('scope_key', '')->where('slug', $data['slug'])))
                ->when(! $data['as_new'] && $original, fn ($q) => $q->whereKeyNot($original->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['slug' => 'Komponent s týmto slugom už existuje. Pre nový komponent zvoľte iný slug.']);
            }
            // Metadata in Properties is authoritative; the JSON keeps additional component fields.
            $source = array_replace($source, ['name' => trim($data['name']), 'slug' => $data['slug'], 'tab' => $data['tab']]);
            $payload = array_replace($data, [
                'name' => trim($data['name']), 'source_payload' => $source,
                'document_css' => $source['css'] ?? '', 'document_js' => $source['js'] ?? '',
                'component_id' => $data['as_new'] ? null : $original->id,
            ]);

            return $library->saveImport($request->user(), $payload, $source['objects'], $path);
        });

        return response()->json(['success' => true, 'data' => $library->editorData($component), 'catalog' => $library->catalog($request->user(), true)]);
    }
}
