<?php

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use App\Services\Cms\DocumentData;
use App\Services\Cms\DocumentStore;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    $this->store = app(DocumentStore::class);
    $this->project = $this->store->create('projects', $this->user, ['name' => 'Prvý web']);
    $this->page = $this->store->create('pages', $this->user, ['name' => 'Úvod', 'slug' => 'uvod', 'project_id' => $this->project->id]);
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $this->source = Component::whereNull('project_id')->where('user_id', $this->user->id)->sole();
    $this->payload = ['component_id' => $this->source->id, 'component_revision' => 1, 'project_id' => $this->project->id, 'page_id' => $this->page->id, 'page_revision' => 1, 'placement_id' => (string) Str::uuid()];
    $this->actingAs($this->user);
});

it('creates a project definition and renders the page from a reference instead of copied markup', function () {
    $this->postJson('/api/cms/component-placement', $this->payload)->assertOk()->assertJsonPath('data.placements_count', 1)->assertJsonPath('data.preview.objects.class', 'container');
    $local = Component::where('project_id', $this->project->id)->sole();
    expect($this->page->fresh()->document['nodes'][0])->toMatchArray(['type' => 'cms-component', 'attributes' => ['component_id' => $local->id, 'source_component_id' => $this->source->id]])
        ->and($local->settings['origin_component_id'])->toBe($this->source->id)->and($this->source->fresh()->revision)->toBe(1)->and(ComponentEntry::count())->toBe(1);
    $this->getJson('/api/cms/projects/'.$this->project->id)->assertOk()->assertJsonPath('data.overview.components_count', 1)->assertJsonPath('data.overview.components.0.id', $local->id);
    expect(app(ComponentLibrary::class)->catalog($this->user)['other'])->toBe([]);
});

it('keeps edited project versions independent and reuses them on another page', function () {
    $this->postJson('/api/cms/component-placement', $this->payload)->assertOk();
    $local = Component::where('project_id', $this->project->id)->sole();
    $source = app(ComponentLibrary::class)->legacy($local);
    $source['objects']['class'] = 'container-fluid';
    $this->postJson('/ui/components/editor', ['component_id' => $local->id, 'revision' => 1, 'as_new' => false, 'name' => 'Široký kontajner', 'slug' => $local->slug, 'tab' => 'bootstrap', 'category' => 'Základné rozloženie', 'target_path' => 'cms', 'json_content' => json_encode($source)])->assertOk();
    $second = $this->store->create('pages', $this->user, ['name' => 'Kontakt', 'slug' => 'kontakt', 'project_id' => $this->project->id]);
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['page_id' => $second->id, 'placement_id' => (string) Str::uuid()]))->assertOk()->assertJsonPath('data.preview.objects.class', 'container-fluid');
    $this->getJson('/api/cms/page-composition/'.$this->page->id)->assertOk()->assertJsonPath('data.preview.objects.class', 'container-fluid');
    expect(Component::where('project_id', $this->project->id)->count())->toBe(1)->and($this->source->fresh()->document['nodes'][0]['attributes']['class'])->toBe('container')->and(ComponentEntry::count())->toBe(1);
});

it('copies an edited version from an existing project into an independent target project', function () {
    $this->postJson('/api/cms/component-placement', $this->payload)->assertOk();
    $local = Component::where('project_id', $this->project->id)->sole();
    $doc = $local->document;
    $doc['nodes'][0]['attributes']['class'] = 'container-fluid';
    $this->store->update('components', $this->user, $local->id, ['revision' => 1, 'document' => $doc]);
    $project = $this->store->create('projects', $this->user, ['name' => 'Druhý web']);
    $page = $this->store->create('pages', $this->user, ['name' => 'Domov', 'slug' => 'domov', 'project_id' => $project->id]);
    $this->getJson('/api/cms/component-placement/options')->assertOk()->assertJsonFragment(['id' => $local->id, 'name' => $local->name, 'revision' => 2, 'project_id' => $this->project->id, 'project_name' => 'Prvý web']);
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['component_id' => $local->id, 'component_revision' => 2, 'project_id' => $project->id, 'page_id' => $page->id]))->assertOk()->assertJsonPath('data.preview.objects.class', 'container-fluid');
    $copy = Component::where('project_id', $project->id)->sole();
    expect($copy->id)->not->toBe($local->id)->and($copy->settings['origin_component_id'])->toBe($local->id)->and($copy->revision)->toBe(1);
    $doc['nodes'][0]['attributes']['class'] = 'container';
    $this->store->update('components', $this->user, $local->id, ['revision' => 2, 'document' => $doc]);
    $this->getJson('/api/cms/page-composition/'.$page->id)->assertOk()->assertJsonPath('data.preview.objects.class', 'container-fluid');
});

it('handles request retries without inserting twice and rejects stale pages and sources', function () {
    $this->postJson('/api/cms/component-placement', $this->payload)->assertOk();
    $this->postJson('/api/cms/component-placement', $this->payload)->assertOk()->assertJsonPath('data.placements_count', 1);
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['placement_id' => (string) Str::uuid()]))->assertConflict();
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['placement_id' => (string) Str::uuid(), 'page_revision' => 2, 'component_revision' => 8]))->assertConflict();
    expect($this->page->fresh()->revision)->toBe(2)->and(Component::whereNotNull('project_id')->count())->toBe(1);
});

it('allows multiple uses of a definition while preserving distinct instance ids', function () {
    $this->postJson('/api/cms/component-placement', $this->payload)->assertOk();
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['page_revision' => 2, 'placement_id' => (string) Str::uuid()]))->assertOk()->assertJsonPath('data.placements_count', 2)->assertJsonPath('data.preview.objects.1.class', 'container');
    expect(Component::whereNotNull('project_id')->count())->toBe(1);
});

it('isolates source components target projects and pages by owner and project', function () {
    $foreignProject = $this->store->create('projects', $this->other, ['name' => 'Cudzí']);
    $foreignPage = $this->store->create('pages', $this->other, ['name' => 'Cudzia', 'slug' => 'cudzia', 'project_id' => $foreignProject->id]);
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['page_id' => $foreignPage->id]))->assertNotFound();
    $this->getJson('/api/cms/page-composition/'.$foreignPage->id)->assertNotFound();
    $this->actingAs($this->other)->postJson('/api/cms/component-placement', array_replace($this->payload, ['project_id' => $foreignProject->id, 'page_id' => $foreignPage->id]))->assertNotFound();
    $this->getJson('/api/cms/component-placement/options')->assertOk()->assertJsonMissing(['id' => $this->source->id])->assertJsonMissing(['id' => $this->project->id]);
    expect(Component::whereNotNull('project_id')->count())->toBe(0);
});

it('rejects inaccessible references and cycles without committing copied definitions', function () {
    $doc = DocumentData::empty();
    $doc['nodes'][] = ['id' => (string) Str::uuid(), 'type' => 'cms-component', 'attributes' => ['component_id' => $this->source->id], 'children' => []];
    $this->source->update(['document' => $doc]);
    $this->postJson('/api/cms/component-placement', $this->payload)->assertUnprocessable();
    expect(Component::whereNotNull('project_id')->count())->toBe(0)->and($this->page->fresh()->revision)->toBe(1);
});

it('copies dependent project components and resolves the complete graph in the target project', function () {
    $document = DocumentData::empty();
    $document['nodes'][] = ['id' => (string) Str::uuid(), 'type' => 'cms-component', 'attributes' => ['component_id' => $this->source->id], 'children' => []];
    $wrapper = $this->store->create('components', $this->user, ['name' => 'Obal', 'slug' => 'obal', 'document' => $document]);
    $this->postJson('/api/cms/component-placement', array_replace($this->payload, ['component_id' => $wrapper->id]))->assertOk()->assertJsonPath('data.preview.objects.class', 'container');
    expect(Component::where('project_id', $this->project->id)->count())->toBe(2);
    $local = Component::where('project_id', $this->project->id)->where('slug', 'obal')->sole();
    expect($local->document['nodes'][0]['attributes']['component_id'])->not->toBe($this->source->id);
});

it('rolls back a definition that attempts to reference another owners component', function () {
    $foreign = $this->store->create('components', $this->other, ['name' => 'Cudzí komponent', 'slug' => 'cudzi']);
    $doc = DocumentData::empty();
    $doc['nodes'][] = ['id' => (string) Str::uuid(), 'type' => 'cms-component', 'attributes' => ['component_id' => $foreign->id], 'children' => []];
    $this->source->update(['document' => $doc]);
    $this->postJson('/api/cms/component-placement', $this->payload)->assertNotFound();
    expect(Component::whereNotNull('project_id')->count())->toBe(0)->and($this->page->fresh()->revision)->toBe(1);
});
