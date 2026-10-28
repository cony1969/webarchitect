<?php

namespace App\Services\Cms;

use App\Models\Cms\Domain;
use App\Models\Cms\Page;
use App\Models\Cms\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectTree
{
    public function __construct(private DocumentStore $store) {}

    public function revision(User $user): int
    {
        return (int) (DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'projects')->value('revision') ?? 1);
    }

    public function read(User $user): array
    {
        return Project::where('user_id', $user->id)->with('domains.pages')->orderBy('position')->get()->map(function ($project) {
            return array_merge($project->source_payload ?? [], $project->settings ?? [], ['id' => $project->legacy_id, 'name' => $project->name, 'domains' => $project->domains->map(function ($domain) {
                return array_merge($domain->source_payload ?? [], $domain->settings ?? [], ['id' => $domain->legacy_id, 'name' => $domain->name, 'pages' => $domain->pages->map(fn ($page) => array_merge($page->source_payload ?? [], $page->settings ?? [], ['id' => $page->legacy_id, 'name' => $page->name]))->all()]);
            })->all()]);
        })->all();
    }

    public function replace(User $user, array $projects, ?int $revision, bool $importing = false): int
    {
        return DB::transaction(function () use ($user, $projects, $revision, $importing) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $collection = DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'projects')->lockForUpdate()->first();
            $current = (int) ($collection->revision ?? 1);
            abort_unless($importing || $revision === $current, 409, 'Projekty medzitým zmenilo iné okno. Obnovte stránku pred uložením.');
            $projectIds = [];
            $domainIds = [];
            $pageIds = [];
            foreach ($projects as $position => $data) {
                $project = Project::withTrashed()->firstOrNew(['owner_key' => 'user:'.$user->id, 'legacy_id' => $data['id']]);
                $project->fill(['user_id' => $user->id, 'name' => $data['name'], 'slug' => $project->exists && $project->name === $data['name'] ? $project->slug : (Str::slug($data['name']) ?: 'project'), 'kind' => 'project', 'position' => $position, 'scope_key' => '', 'deleted_at' => null]);
                $project->document ??= DocumentData::empty();
                $project->settings = array_replace($project->settings ?? [], Arr::except($data, ['id', 'name', 'domains']));
                $project->source_payload = Arr::except($data, ['domains']);
                $this->store->save($project, $user->id);
                $projectIds[] = $project->id;
                foreach ($data['domains'] ?? [] as $domainPosition => $domainData) {
                    $domain = Domain::withTrashed()->firstOrNew(['project_id' => $project->id, 'legacy_id' => $domainData['id']]);
                    $domain->fill(['name' => $domainData['name'], 'position' => $domainPosition, 'settings' => Arr::except($domainData, ['id', 'name', 'pages']), 'source_payload' => Arr::except($domainData, ['pages']), 'deleted_at' => null]);
                    $domain->save();
                    $domainIds[] = $domain->id;
                    foreach ($domainData['pages'] ?? [] as $pagePosition => $pageData) {
                        $page = Page::withTrashed()->firstOrNew(['owner_key' => 'user:'.$user->id, 'legacy_id' => $pageData['id']]);
                        abort_if($page->exists && $page->project_id !== $project->id, 422, 'Identifikátor stránky už patrí do iného projektu.');
                        $page->fill(['user_id' => $user->id, 'project_id' => $project->id, 'domain_id' => $domain->id, 'name' => $pageData['name'], 'slug' => $page->exists && $page->name === $pageData['name'] ? $page->slug : (Str::slug($pageData['name']) ?: 'page'), 'kind' => 'page', 'position' => $pagePosition, 'scope_key' => $domain->id, 'source_payload' => $pageData, 'deleted_at' => null]);
                        $page->settings = array_replace($page->settings ?? [], Arr::except($pageData, ['id', 'name']));
                        $page->document ??= DocumentData::empty();
                        $this->store->save($page, $user->id);
                        $pageIds[] = $page->id;
                    }
                }
            }
            Page::where('user_id', $user->id)->whereNotNull('domain_id')->whereNotIn('id', $pageIds)->delete();
            Domain::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->whereNotIn('id', $domainIds)->delete();
            Project::where('user_id', $user->id)->whereNotIn('id', $projectIds)->delete();
            $next = $importing ? $current : $current + 1;
            DB::table('cms_collections')->updateOrInsert(['user_id' => $user->id, 'name' => 'projects'], ['revision' => $next, 'created_at' => $collection->created_at ?? now(), 'updated_at' => now()]);

            return $next;
        });
    }
}
