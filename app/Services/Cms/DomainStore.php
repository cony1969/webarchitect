<?php

namespace App\Services\Cms;

use App\Models\Cms\Domain;
use App\Models\Cms\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DomainStore
{
    public function query(User $user)
    {
        return Domain::whereHas('project', fn ($query) => $query->where('user_id', $user->id));
    }

    public function data(Domain $domain): array
    {
        $domain->loadCount('pages')->load('project:id,name,legacy_id');

        return array_merge($domain->makeHidden('source_payload')->toArray(), ['editor_kind' => 'domain']);
    }

    public function persist(Domain $domain, ?int $actor): Domain
    {
        if ($domain->exists && ! $domain->isDirty()) {
            return $domain;
        }
        $domain->revision = $domain->exists ? (int) $domain->revision + 1 : 1;
        $domain->status ??= 'draft';
        $domain->save();
        DB::table('cms_document_revisions')->insert([
            'document_type' => 'domains', 'document_id' => $domain->id, 'revision' => $domain->revision,
            'created_by' => $actor, 'payload' => json_encode($domain->makeHidden('source_payload')->toArray(), JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);

        return $domain;
    }

    public function save(User $user, array $data, ?string $id = null, bool $copy = false): Domain
    {
        return DB::transaction(function () use ($user, $data, $id, $copy) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $original = $id ? $this->query($user)->whereKey($id)->lockForUpdate()->firstOrFail() : null;
            $projectId = $data['project_id'] ?? $original?->project_id;
            $project = Project::where('user_id', $user->id)->findOrFail($projectId);
            if ($original && ! $copy && $projectId !== $original->project_id) {
                throw ValidationException::withMessages(['project_id' => 'Doménu nemožno touto úpravou presunúť do iného projektu.']);
            }
            if ($original && ! $copy) {
                abort_unless($original->revision === (int) $data['revision'], 409, 'Doménu medzitým zmenilo iné okno. Načítajte aktuálnu verziu alebo uložte svoju prácu ako novú doménu.');
            }
            $name = strtolower(rtrim(trim($data['name']), '.'));
            if (function_exists('idn_to_ascii')) {
                $name = idn_to_ascii($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $name;
            }
            if (! filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($name, '.')) {
                throw ValidationException::withMessages(['name' => 'Zadajte platnú doménu alebo subdoménu bez protokolu a cesty.']);
            }
            $duplicate = Domain::where('project_id', $projectId)->whereRaw('LOWER(name) = ?', [$name])->when($original && ! $copy, fn ($q) => $q->where('id', '!=', $original->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'Táto doména už v projekte existuje.']);
            }
            $domain = $original && ! $copy ? $original : new Domain(['id' => (string) Str::uuid(), 'project_id' => $projectId, 'legacy_id' => 'domain-'.Str::uuid(), 'position' => (int) (Domain::where('project_id', $projectId)->max('position') ?? -1) + 1]);
            $settings = $data['settings'] ?? $original?->settings ?? [];
            $domain->fill(['name' => $name, 'settings' => app(DomainSettings::class)->normalize($settings), 'status' => $copy ? 'draft' : ($data['status'] ?? $original?->status ?? 'draft')]);
            $this->persist($domain, $user->id);
            $this->bumpTree($user);

            return $domain;
        });
    }

    public function bumpTree(User $user): void
    {
        $revision = (int) (DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'projects')->value('revision') ?? 1);
        DB::table('cms_collections')->updateOrInsert(['user_id' => $user->id, 'name' => 'projects'], ['revision' => $revision + 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
