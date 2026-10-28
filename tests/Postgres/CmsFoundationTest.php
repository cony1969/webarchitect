<?php

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\Cms\Page;
use App\Models\Cms\Project;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use App\Services\Cms\DocumentData;
use App\Services\Cms\LegacyImport;
use App\Services\Cms\ProjectTree;
use App\Services\Cms\SectionLibrary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    expect(DB::getDriverName())->toBe('pgsql');
    expect(DB::connection()->getDatabaseName())->toBe('webarchitect_test');
    $this->alice = User::factory()->create(['id' => 1]);
    $this->bob = User::factory()->create(['id' => 2]);
    $this->fixture = storage_path('framework/testing/cms-'.bin2hex(random_bytes(6)));
    File::makeDirectory($this->fixture.'/content', 0755, true);
    File::makeDirectory($this->fixture.'/components/other', 0755, true);
    $this->trees = [];
    foreach ([1, 2] as $id) {
        $this->trees['user_'.$id] = [['id' => 'project-'.$id, 'name' => 'Project '.$id, 'domains' => [['id' => 'domain-'.$id, 'name' => 'domain'.$id.'.test', 'pages' => [['id' => 'page-'.$id, 'name' => 'Home', 'route' => '/home']]]]]];
    }
    $this->catalog = ['native' => [['category' => 'Basic', 'items' => [['id' => 'placeholder', 'name' => 'Input']]]], 'bootstrap' => [], 'other' => [['category' => 'Menu', 'items' => [['id' => 'title', 'name' => 'Title', 'path' => 'other/title.json']]], ['category' => 'Imported', 'items' => [['id' => 'title-copy', 'name' => 'Title', 'path' => 'other/title.json']]]]];
    foreach (['projects' => $this->trees, 'components' => $this->catalog, 'layouts' => [['id' => 'layout-1', 'name' => 'blank']]] as $name => $data) {
        File::put($this->fixture.'/content/'.$name.'.json', json_encode($data));
    }
    File::put($this->fixture.'/components/other/title.json', json_encode(['name' => 'Title', 'slug' => 'title', 'tab' => 'other', 'objects' => ['type' => 'h1', 'class' => 'headline', 'content' => 'Hello'], 'css' => 'h1 {color:teal}', 'js' => '/* existing */']));
    $this->manifest = app(LegacyImport::class)->run($this->fixture);
});

afterEach(function () {
    if (isset($this->fixture)) {
        File::deleteDirectory($this->fixture);
    }
});

it('imports once, preserves identifiers, catalog entries and source files', function () {
    expect($this->manifest)->toMatchArray(['projects' => 2, 'domains' => 2, 'pages' => 2, 'catalog_entries' => 3, 'components' => 2, 'component_definitions' => 1, 'layout_templates' => 1]);
    expect(app(ProjectTree::class)->read($this->alice))->toBe($this->trees['user_1']);
    expect(app(ComponentLibrary::class)->catalog($this->alice))->toEqual($this->catalog);
    expect(app(LegacyImport::class)->run($this->fixture)['already_imported'])->toBeTrue();
    expect(Component::count())->toBe(2)->and(ComponentEntry::count())->toBe(3);
    foreach ($this->manifest['source_hashes'] as $path => $hash) {
        expect(hash_file('sha256', $this->fixture.'/'.$path))->toBe($hash);
    }
    expect(Component::where('source_key', 'other/title.json')->first()->document['css'])->toBe('h1 {color:teal}');
});

it('protects every write and document read behind authentication', function () {
    foreach (['/api/ui/save-projects', '/api/ui/save-layouts', '/api/cms/pages', '/profile/save-tabs', '/ui/components/import-html', '/ui/components/load'] as $url) {
        $this->postJson($url, [])->assertUnauthorized();
    }
    $this->getJson('/api/cms/projects')->assertUnauthorized();
});

it('scopes documents and project links to their owner', function () {
    $foreign = Project::where('user_id', 2)->first();
    $this->actingAs($this->alice)->getJson('/api/cms/projects/'.$foreign->id)->assertNotFound();
    $this->putJson('/api/cms/projects/'.$foreign->id, ['name' => 'Stolen', 'slug' => 'stolen', 'revision' => 1])->assertNotFound();
    $this->postJson('/api/cms/pages', ['name' => 'Wrong owner', 'slug' => 'wrong', 'project_id' => $foreign->id])->assertNotFound();
    $this->postJson('/api/ui/save-projects', ['user_id' => 2, 'projects' => [], 'revision' => 1])->assertUnprocessable();
    $this->getJson('/api/cms/projects')->assertJsonCount(1, 'data');
});

it('saves documents with stable IDs, snapshots, copy and stale revision protection', function () {
    $this->actingAs($this->alice);
    $data = $this->postJson('/api/cms/components', ['name' => 'Hero', 'slug' => 'hero', 'document' => DocumentData::fromLegacy(['type' => 'section', 'content' => ' One '])])->assertCreated()->json('data');
    $id = $data['id'];
    expect($data['document']['nodes'][0]['children'][0])->toBe(' One ');
    $this->putJson('/api/cms/components/'.$id, ['name' => 'Hero changed', 'slug' => 'hero', 'revision' => 1])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.revision', 2);
    $this->putJson('/api/cms/components/'.$id, ['name' => 'Stale', 'slug' => 'hero', 'revision' => 1])->assertConflict();
    $copy = $this->postJson('/api/cms/components/'.$id.'/copy', ['name' => 'Hero copy', 'slug' => 'hero-copy'])->assertCreated()->json('data');
    expect($copy['id'])->not->toBe($id);
    expect($copy['document'])->toEqual(Component::findOrFail($id)->document);
    expect(DB::table('cms_document_revisions')->where('document_id', $id)->count())->toBe(2);
    expect(app(ComponentLibrary::class)->find($this->alice, 'cms/'.$id.'.json')->id)->toBe($id);
});

it('keeps shared templates immutable and makes private component overrides', function () {
    $original = Component::where('source_key', 'other/title.json')->first();
    $this->actingAs($this->alice)->putJson('/api/cms/components/'.$original->id, ['name' => 'Shared edit', 'slug' => 'shared', 'revision' => 1])->assertForbidden();
    $payload = ['name' => 'Personal title', 'slug' => 'personal-title', 'component_id' => $original->id, 'revision' => 1, 'tab' => 'other', 'category' => 'Menu', 'target_path' => 'other', 'json_content' => json_encode(['objects' => ['type' => 'h2', 'content' => 'Personal']])];
    $saved = $this->postJson('/ui/components/import-html', $payload)->assertOk()->json();
    $this->postJson('/ui/components/load', ['item_path' => 'other/title.json'])->assertOk()->assertJsonPath('component_id', $saved['component_id']);
    $this->postJson('/ui/components/import-html', $payload)->assertOk(); // Same revision 1 applies to the personal first version.
    $this->postJson('/ui/components/import-html', $payload)->assertConflict();
    expect($original->fresh()->name)->toBe('Title');
    expect(app(ComponentLibrary::class)->find($this->bob, 'other/title.json')->id)->toBe($original->id);
    expect(app(ComponentLibrary::class)->catalog($this->bob))->toEqual($this->catalog);
});

it('checks tree revisions and never changes another user tree', function () {
    $tree = $this->trees['user_1'];
    $tree[0]['name'] = 'Changed project';
    $this->actingAs($this->alice)->postJson('/api/ui/save-projects', ['projects' => $tree, 'revision' => 1])->assertOk()->assertJsonPath('revision', 2);
    $this->postJson('/api/ui/save-projects', ['projects' => [], 'revision' => 1])->assertConflict();
    expect(app(ProjectTree::class)->read($this->bob))->toBe($this->trees['user_2']);
    expect(Project::where('user_id', 1)->first()->name)->toBe('Changed project');
    $this->postJson('/api/cms/pages', ['name' => 'New page', 'slug' => 'new-page', 'domain_id' => Project::where('user_id', 1)->first()->domains->first()->id])->assertCreated();
    $this->postJson('/api/ui/save-projects', ['projects' => $tree, 'revision' => 2])->assertConflict();
});

it('isolates layout edits and permits an empty personal library', function () {
    $this->actingAs($this->alice)->postJson('/api/ui/save-layouts', ['layouts' => [['id' => 'layout-1', 'name' => 'Renamed']], 'revision' => 1])->assertOk();
    expect(app(SectionLibrary::class)->read($this->bob)[0]['name'])->toBe('blank');
    $this->postJson('/api/ui/save-layouts', ['layouts' => [], 'revision' => 1])->assertConflict();
    $this->postJson('/api/ui/save-layouts', ['layouts' => [], 'revision' => 2])->assertOk();
    expect(app(SectionLibrary::class)->read($this->alice))->toBe([]);
});

it('keeps standalone pages when the legacy tree is saved', function () {
    $this->actingAs($this->alice);
    $id = $this->postJson('/api/cms/pages', ['name' => 'Standalone', 'slug' => 'standalone'])->assertCreated()->json('data.id');
    $this->postJson('/api/ui/save-projects', ['projects' => $this->trees['user_1'], 'revision' => 2])->assertOk();
    expect(Page::find($id))->not->toBeNull();
    $attached = $this->postJson('/api/cms/pages', ['name' => 'Project standalone', 'slug' => 'project-standalone', 'project_id' => Project::where('user_id', 1)->first()->id])->assertCreated()->json('data.id');
    $this->postJson('/api/ui/save-projects', ['projects' => $this->trees['user_1'], 'revision' => 4])->assertOk();
    expect(Page::find($attached))->not->toBeNull();
});

it('rejects invalid node data and rolls back an unsuccessful legacy import', function () {
    $this->actingAs($this->alice)->postJson('/api/cms/pages', ['name' => 'Invalid', 'slug' => 'invalid', 'document' => ['schema_version' => 9]])->assertUnprocessable();
    DB::table('cms_imports')->delete();
    File::put($this->fixture.'/content/projects.json', json_encode(['user_1' => [['id' => 'new-project', 'name' => 'Rollback', 'domains' => []]], 'user_999999' => []]));
    try {
        app(LegacyImport::class)->run($this->fixture);
        $this->fail('Invalid owner must fail import');
    } catch (ModelNotFoundException $e) {
    }
    expect(Project::where('legacy_id', 'new-project')->exists())->toBeFalse();
    expect(Project::where('user_id', 1)->count())->toBe(1);
});

it('preserves HTML tag types, reserved attributes and document text', function () {
    $objects = ['type' => 'div', 'data-id' => 'kept', 'content' => [['type' => 'input', 'attributes' => ['type' => 'email']], ' text ', ['type' => 'span', 'content' => 'text']]];
    expect(DocumentData::toLegacy(DocumentData::fromLegacy($objects)))->toBe($objects);
    $this->actingAs($this->alice)->postJson('/ui/components/import-html', ['name' => 'Email', 'slug' => 'email', 'html' => '<input type="email" name="mail">'])->assertOk();
    $node = Component::where('user_id', 1)->first()->document['nodes'][0];
    expect($node['type'])->toBe('input')->and($node['attributes']['type'])->toBe('email');
});

it('renders the existing shell from the database and preserves saved tabs', function () {
    $this->actingAs($this->alice)->postJson('/profile/save-tabs', ['tabs' => [['id' => 'tab-1', 'title' => 'Existing', 'mode' => 'layout']], 'activeTab' => 'tab-1'])->assertOk();
    $this->get('/dashboard')->assertOk()->assertSee('sidebarProjectsRevision', false)->assertSee('canvas-tabs-wrapper', false)->assertSee('advanced-sidebar', false);
});

it('keeps API metadata and custom slugs when the compatible tree is saved', function () {
    $project = Project::where('user_id', 1)->first();
    $this->actingAs($this->alice)->putJson('/api/cms/projects/'.$project->id, ['name' => $project->name, 'slug' => 'custom-slug', 'settings' => ['description' => 'Preserved'], 'revision' => 1])->assertOk();
    $tree = app(ProjectTree::class)->read($this->alice);
    expect($tree[0]['description'])->toBe('Preserved');
    $this->postJson('/api/ui/save-projects', ['projects' => $tree, 'revision' => 2])->assertOk();
    expect($project->fresh()->slug)->toBe('custom-slug');
});

it('keeps the account signed in when ownership prevents deleting its CMS data', function () {
    $this->actingAs($this->alice)->deleteJson('/profile', ['password' => 'password'])->assertUnprocessable();
    $this->assertAuthenticatedAs($this->alice);
    expect(User::find(1))->not->toBeNull();
});
