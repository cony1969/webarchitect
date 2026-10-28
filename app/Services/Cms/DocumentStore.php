<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\Document;
use App\Models\Cms\Domain;
use App\Models\Cms\Page;
use App\Models\Cms\Project;
use App\Models\Cms\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentStore
{
    public const MODELS = ['projects' => Project::class, 'components' => Component::class, 'sections' => Section::class, 'pages' => Page::class];

    public function query(string $kind, User $user)
    {
        $class = self::MODELS[$kind] ?? abort(404);

        return $class::query()->where(fn ($q) => $q->where('user_id', $user->id)->when(in_array($kind, ['components', 'sections']), fn ($q) => $q->orWhereNull('user_id')));
    }

    public function record(Document $document, ?int $actor): void
    {
        $kind = array_search($document::class, self::MODELS, true);
        DB::table('cms_document_revisions')->insert([
            'document_type' => $kind, 'document_id' => $document->id, 'revision' => $document->revision,
            'created_by' => $actor, 'payload' => json_encode($document->toArray(), JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    public function save(Document $document, ?int $actor): Document
    {
        if (! $document->exists) {
            $document->revision = 1;
            $document->status ??= 'draft';
        } elseif (! $document->isDirty()) {
            return $document;
        } else {
            $document->revision++;
        }
        $document->save();
        $this->record($document, $actor);

        return $document;
    }

    public function create(string $kind, User $user, array $data): Document
    {
        return DB::transaction(function () use ($kind, $user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $class = self::MODELS[$kind];
            $id = (string) Str::uuid();
            $attributes = $this->attributes($kind, $user, $data);
            $this->uniqueSlug($class, $user, $attributes);
            $document = new $class(array_merge($attributes, [
                'id' => $id, 'user_id' => $user->id, 'owner_key' => 'user:'.$user->id,
                'legacy_id' => rtrim($kind, 's').'-'.$id, 'position' => $kind === 'projects' ? (int) ($class::where('user_id', $user->id)->max('position') ?? -1) + 1 : 0,
                'kind' => $kind === 'pages' ? 'page' : ($kind === 'sections' ? 'section' : rtrim($kind, 's')),
            ]));
            $saved = $this->save($document, $user->id);
            $this->bumpCollection($kind, $user, $saved);

            return $saved;
        });
    }

    public function update(string $kind, User $user, string $id, array $data): Document
    {
        return DB::transaction(function () use ($kind, $user, $id, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $document = $this->query($kind, $user)->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $document->user_id === (int) $user->id, 403, 'Spoločnú predlohu najprv uložte ako vlastnú kópiu.');
            abort_unless($document->revision === (int) $data['revision'], 409, 'Dokument medzitým zmenil iný editor. Načítajte aktuálnu verziu.');
            $attributes = $this->attributes($kind, $user, array_merge($document->toArray(), $data));
            $this->uniqueSlug($document::class, $user, $attributes, $document->id);
            $saved = $this->save($document->fill($attributes), $user->id);
            $this->bumpCollection($kind, $user, $saved);

            return $saved;
        });
    }

    public function copy(string $kind, User $user, string $id, array $data): Document
    {
        $original = $this->query($kind, $user)->findOrFail($id);

        return $this->create($kind, $user, array_merge($original->only(['name', 'slug', 'document', 'settings', 'project_id', 'domain_id']), $data, ['status' => 'draft']));
    }

    private function bumpCollection(string $kind, User $user, Document $document): void
    {
        $collection = in_array($kind, ['projects', 'pages']) ? 'projects' : ($kind === 'sections' && $document->kind === 'layout_template' ? 'layouts' : null);
        if (! $collection) {
            return;
        }
        $query = DB::table('cms_collections')->where('user_id', $user->id)->where('name', $collection);
        $current = (int) ($query->value('revision') ?? 1);
        DB::table('cms_collections')->updateOrInsert(['user_id' => $user->id, 'name' => $collection], ['revision' => $current + 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function attributes(string $kind, User $user, array $data): array
    {
        $attributes = [
            'name' => $data['name'], 'slug' => $data['slug'], 'status' => $data['status'] ?? 'draft',
            'document' => DocumentData::validate($data['document'] ?? DocumentData::empty()),
            'settings' => $kind === 'projects' ? app(ProjectSettings::class)->normalize($data['settings'] ?? []) : ($data['settings'] ?? []), 'scope_key' => '',
        ];
        if ($kind !== 'projects') {
            $attributes['project_id'] = $data['project_id'] ?? null;
            if ($attributes['project_id']) {
                Project::where('user_id', $user->id)->findOrFail($attributes['project_id']);
            }
            $attributes['scope_key'] = $attributes['project_id'] ?? '';
        }
        if ($kind === 'pages') {
            $attributes['domain_id'] = $data['domain_id'] ?? null;
            if ($attributes['domain_id']) {
                $domain = Domain::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->findOrFail($attributes['domain_id']);
                if ($attributes['project_id'] && $attributes['project_id'] !== $domain->project_id) {
                    throw ValidationException::withMessages(['domain_id' => 'Doména nepatrí do zvoleného projektu.']);
                }
                $attributes['project_id'] = $domain->project_id;
                $attributes['scope_key'] = $domain->id;
            }
        }

        return $attributes;
    }

    private function uniqueSlug(string $class, User $user, array $attributes, ?string $except = null): void
    {
        $duplicate = $class::where('user_id', $user->id)->where('scope_key', $attributes['scope_key'])->where('slug', $attributes['slug'])->when($except, fn ($q) => $q->where('id', '!=', $except))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['slug' => 'Tento slug už v danom umiestnení existuje.']);
        }
    }
}
