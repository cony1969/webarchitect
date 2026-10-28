<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Component;
use App\Models\Cms\Page;
use App\Models\Cms\Project;
use App\Models\User;
use App\Services\Cms\DocumentStore;
use App\Services\Cms\PageComposition;
use App\Services\Cms\ProjectComponents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComponentPlacementController extends Controller
{
    public function options(Request $request)
    {
        $projects = Project::where('user_id', $request->user()->id)->where('status', '!=', 'archived')->with(['pages.domain'])->orderBy('name')->get();
        $components = Component::where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereNull('user_id'))->with('project')->orderBy('name')->get()->filter(fn ($c) => ! ($c->settings['placeholder'] ?? false));

        return response()->json(['projects' => $projects->map(fn ($p) => ['id' => $p->id, 'legacy_id' => $p->legacy_id, 'name' => $p->name, 'pages' => $p->pages->map(fn ($page) => ['id' => $page->id, 'legacy_id' => $page->legacy_id, 'name' => $page->name, 'domain_name' => $page->domain?->name, 'revision' => $page->revision])->values()]), 'sources' => $components->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'revision' => $c->revision, 'project_id' => $c->project_id, 'project_name' => $c->project?->name ?? 'Knižnica komponentov'])->values()]);
    }

    public function show(Request $request, string $id, PageComposition $composition)
    {
        return response()->json(['data' => $composition->page($request->user(), Page::where('user_id', $request->user()->id)->findOrFail($id))]);
    }

    public function insert(Request $request, DocumentStore $store, ProjectComponents $components, PageComposition $composition)
    {
        $data = $request->validate([
            'component_id' => ['required', 'uuid'], 'component_revision' => ['required', 'integer', 'min:1'],
            'project_id' => ['required', 'uuid'], 'page_id' => ['required', 'uuid'], 'page_revision' => ['required', 'integer', 'min:1'],
            'placement_id' => ['required', 'uuid'], 'user_id' => ['prohibited'],
        ]);

        return DB::transaction(function () use ($request, $data, $store, $components, $composition) {
            $user = $request->user();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $project = Project::where('user_id', $user->id)->findOrFail($data['project_id']);
            $page = Page::where('user_id', $user->id)->where('project_id', $project->id)->lockForUpdate()->findOrFail($data['page_id']);
            $source = $store->query('components', $user)->findOrFail($data['component_id']);
            abort_if($source->settings['placeholder'] ?? false, 422, 'Komponent zatiaľ nemá definíciu.');
            $document = $page->document;
            foreach ($document['nodes'] as $node) {
                if (is_array($node) && $node['id'] === $data['placement_id']) {
                    abort_unless($node['type'] === 'cms-component' && ($node['attributes']['source_component_id'] ?? null) === $source->id, 409, 'Identifikátor vloženia už patrí inému komponentu.');

                    return response()->json(['data' => $composition->page($user, $page), 'project_tree' => ProjectTreeController::snapshot($user)]);
                }
            }
            abort_unless($page->revision === $data['page_revision'], 409, 'Stránka sa medzitým zmenila. Obnovte zoznam cieľových stránok.');
            abort_unless($source->revision === $data['component_revision'], 409, 'Zdrojový komponent sa medzitým zmenil. Obnovte výber zdroja.');
            $component = $components->useIn($user, $source, $project);
            $document['nodes'][] = ['id' => $data['placement_id'], 'type' => 'cms-component', 'attributes' => ['component_id' => $component->id, 'source_component_id' => $source->id], 'children' => []];
            // Resolve before writing, so broken/cyclic references roll back the entire insertion.
            $composition->resolve($user, $document, $project->id);
            $saved = $store->update('pages', $user, $page->id, ['revision' => $page->revision, 'document' => $document]);

            return response()->json(['data' => $composition->page($user, $saved), 'project_tree' => ProjectTreeController::snapshot($user)]);
        });
    }
}
