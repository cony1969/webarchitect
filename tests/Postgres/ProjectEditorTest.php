<?php

use App\Models\Cms\Domain;
use App\Models\Cms\Project;
use App\Models\User;
use App\Services\Cms\DocumentStore;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(DB::getDriverName())->toBe('pgsql');
    expect(DB::connection()->getDatabaseName())->toBe('webarchitect_test');
    $this->user = User::factory()->create();
});

it('creates a project with Laravel 13 and PostgreSQL defaults and refreshes the tree', function () {
    $result = $this->actingAs($this->user)->postJson('/api/cms/projects', ['name' => 'Nový projekt', 'slug' => 'novy-projekt'])->assertCreated();
    $result->assertJsonPath('data.settings.project_type', 'laravel13')->assertJsonPath('data.settings.setup.database_engine', 'pgsql')->assertJsonPath('data.settings.setup.create_database', false)->assertJsonPath('project_tree.revision', 2);
    $id = $result->json('data.id');
    $legacy = $result->json('data.legacy_id');
    expect($result->json('project_tree.ids')[$legacy])->toBe($id);
    $this->getJson('/api/ui/project-tree')->assertOk()->assertJsonPath('projects.0.name', 'Nový projekt');
});

it('saves Laravel setup choices as configuration without creating a database', function () {
    $before = DB::select('select datname from pg_database order by datname');
    $settings = ['project_type' => 'laravel13', 'description' => 'Web pre klienta', 'primary_domain' => 'client.example', 'git_repository' => 'git@github.com:team/client.git', 'environment' => 'staging', 'setup' => ['from_template' => true, 'template' => 'business', 'login_page' => true, 'create_database' => true, 'database_engine' => 'pgsql']];
    $id = $this->actingAs($this->user)->postJson('/api/cms/projects', ['name' => 'Klient', 'slug' => 'klient', 'settings' => $settings])->assertCreated()->json('data.id');
    expect(Project::findOrFail($id)->settings)->toEqual($settings);
    expect(DB::select('select datname from pg_database order by datname'))->toEqual($before);
});

it('supports every offered project type', function (string $type) {
    $this->actingAs($this->user)->postJson('/api/cms/projects', ['name' => 'Web', 'slug' => 'web', 'settings' => ['project_type' => $type]])->assertCreated()->assertJsonPath('data.settings.project_type', $type);
})->with(['laravel13', 'php', 'python', 'nodejs', 'static']);

it('validates project types, setup choices, domains and repository URLs', function ($settings, $field) {
    $this->actingAs($this->user)->postJson('/api/cms/projects', ['name' => 'Web', 'slug' => 'web', 'settings' => $settings])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Project::count())->toBe(0);
})->with([
    [['project_type' => 'unknown'], 'settings.project_type'],
    [['project_type' => ''], 'settings.project_type'],
    [['setup' => ''], 'settings.setup'],
    [['setup' => ['template' => 'unknown']], 'settings.setup.template'],
    [['setup' => ['login_page' => 'perhaps']], 'settings.setup.login_page'],
    [['setup' => ['database_engine' => 'unknown']], 'settings.setup.database_engine'],
    [['primary_domain' => 'https://example.com/path'], 'settings.primary_domain'],
    [['git_repository' => 'javascript:alert(1)'], 'settings.git_repository'],
    [['git_repository' => 'https://user:password@example.com/repo'], 'settings.git_repository'],
]);

it('updates a project in place and preserves settings that existed before this editor', function () {
    $project = app(DocumentStore::class)->create('projects', $this->user, ['name' => 'Original', 'slug' => 'original', 'settings' => ['legacy_appearance' => ['color' => 'teal']]]);
    $settings = $project->settings;
    $settings['project_type'] = 'python';
    $settings['description'] = 'Updated';
    $result = $this->actingAs($this->user)->putJson('/api/cms/projects/'.$project->id, ['name' => 'Updated', 'slug' => 'updated', 'revision' => 1, 'settings' => $settings])->assertOk();
    $result->assertJsonPath('data.id', $project->id)->assertJsonPath('data.revision', 2)->assertJsonPath('data.settings.legacy_appearance.color', 'teal')->assertJsonPath('project_tree.projects.0.name', 'Updated');
    expect(Project::count())->toBe(1);
    $this->putJson('/api/cms/projects/'.$project->id, ['name' => 'Stale', 'slug' => 'stale', 'revision' => 1])->assertConflict();
    expect($project->fresh()->name)->toBe('Updated');
});

it('saves current edited settings as a new project without altering or duplicating its domains', function () {
    $project = app(DocumentStore::class)->create('projects', $this->user, ['name' => 'Original', 'slug' => 'original']);
    Domain::create(['project_id' => $project->id, 'legacy_id' => 'domain-original', 'name' => 'original.example', 'position' => 0, 'settings' => []]);
    $settings = $project->settings;
    $settings['description'] = 'Unsaved edited description';
    $settings['setup']['login_page'] = true;
    $response = $this->actingAs($this->user)->postJson('/api/cms/projects/'.$project->id.'/copy', ['name' => 'Copy', 'slug' => 'copy', 'settings' => $settings])->assertCreated();
    expect($response->json('data.id'))->not->toBe($project->id);
    $response->assertJsonPath('data.settings.description', 'Unsaved edited description')->assertJsonPath('data.settings.setup.login_page', true)->assertJsonPath('data.revision', 1);
    expect($project->fresh()->settings['description'])->toBe('')->and(Project::count())->toBe(2)->and(Domain::count())->toBe(1);
    expect(Project::findOrFail($response->json('data.id'))->domains()->count())->toBe(0);
});

it('rejects duplicate slugs and access to another user project', function () {
    $project = app(DocumentStore::class)->create('projects', $this->user, ['name' => 'Original', 'slug' => 'original']);
    $this->actingAs($this->user)->postJson('/api/cms/projects/'.$project->id.'/copy', ['name' => 'Copy', 'slug' => 'original'])->assertUnprocessable()->assertJsonValidationErrors('slug');
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/cms/projects/'.$project->id)->assertNotFound();
    $this->getJson('/api/ui/project-tree')->assertOk()->assertJsonCount(0, 'projects');
    $this->postJson('/api/cms/projects/'.$project->id.'/copy', ['name' => 'Stolen', 'slug' => 'stolen'])->assertNotFound();
});

it('renders the project editor, right properties and local Select2 assets', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('waCanvasTabs', false)->assertSee('wa-project-editor', false)->assertSee('wa-project-type', false)->assertSee('assets/libs/select2/js/select2.full.min.js', false)->assertSee('Uložiť ako nový')->assertSee('Vytvoriť zo vzoru')->assertSee('Vytvoriť prihlasovaciu stránku')->assertSee('Vytvoriť databázu');
});

it('keeps project order stable when a project is updated', function () {
    $store = app(DocumentStore::class);
    $first = $store->create('projects', $this->user, ['name' => 'First', 'slug' => 'first']);
    $second = $store->create('projects', $this->user, ['name' => 'Second', 'slug' => 'second']);
    $third = $store->create('projects', $this->user, ['name' => 'Third', 'slug' => 'third']);
    expect([$first->position, $second->position, $third->position])->toBe([0, 1, 2]);
    $this->actingAs($this->user)->putJson('/api/cms/projects/'.$first->id, ['name' => 'Updated first', 'slug' => 'first', 'revision' => 1])->assertOk()->assertJsonPath('project_tree.projects.0.name', 'Updated first');
    expect($this->getJson('/api/ui/project-tree')->assertOk()->json('projects'))->toHaveCount(3);
});
