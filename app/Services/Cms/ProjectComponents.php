<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectComponents
{
    private int $nodes = 0;

    public function __construct(private DocumentStore $store, private ComponentLibrary $library, private PageComposition $composition) {}

    // Caller holds the owner's transaction lock. Reusing a project version never replaces its edits.
    public function useIn(User $user, Component $source, Project $project, array $stack = []): Component
    {
        if (! $stack) {
            $this->nodes = 0;
        }
        if (in_array($source->id, $stack, true) || count($stack) > 48) {
            throw ValidationException::withMessages(['component_id' => 'Komponenty obsahujú kruhový odkaz alebo príliš hlboké vnorenie.']);
        }
        if ($source->project_id === $project->id) {
            return $source;
        }
        $existing = Component::where('user_id', $user->id)->where('project_id', $project->id)->where('settings->origin_component_id', $source->id)->first();
        if ($existing) {
            return $existing;
        }
        $document = $source->document;
        $map = function ($node) use (&$map, $source, $project, $user, $stack) {
            if (++$this->nodes > 5000) {
                throw ValidationException::withMessages(['component_id' => 'Komponent obsahuje príliš veľa vnorených objektov.']);
            }
            if (is_string($node)) {
                return $node;
            }
            $node['id'] = (string) Str::uuid();
            if ($node['type'] === 'cms-component') {
                $child = $this->composition->component($user, $node['attributes']['component_id'] ?? '', $source->project_id);
                $node['attributes']['component_id'] = $this->useIn($user, $child, $project, [...$stack, $source->id])->id;
            }
            $node['children'] = array_map($map, $node['children']);

            return $node;
        };
        $document['nodes'] = array_map($map, $document['nodes']);
        $slug = $source->slug;
        $number = 2;
        while (Component::where('user_id', $user->id)->where('project_id', $project->id)->where('slug', $slug)->exists()) {
            $slug = Str::limit($source->slug, 140, '').'-'.$number++;
        }
        $metadata = $this->library->editorData($source);
        $component = new Component([
            'user_id' => $user->id, 'owner_key' => 'user:'.$user->id, 'project_id' => $project->id, 'scope_key' => $project->id,
            'kind' => 'component', 'name' => $source->name, 'slug' => $slug,
            'document' => DocumentData::validate($document), 'source_payload' => $source->source_payload,
            'settings' => array_replace($source->settings ?? [], ['placeholder' => false, 'category' => $metadata['category'], 'origin_component_id' => $source->id, 'origin_revision' => $source->revision, 'origin_project_name' => $source->project?->name]),
        ]);
        $this->store->save($component, $user->id);

        return $component;
    }
}
