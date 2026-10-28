<?php

use App\Models\Cms\Domain;
use App\Models\User;
use App\Services\Cms\DocumentStore;
use App\Services\Cms\DomainConfigurationMigration;
use App\Services\Cms\DomainStore;
use App\Services\Cms\ProjectTree;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(DB::connection()->getDatabaseName())->toBe('webarchitect_test');
    $this->user = User::factory()->create();
    $this->project = app(DocumentStore::class)->create('projects', $this->user, ['name' => 'Firma']);
});

it('accepts a project name alone and generates its internal slug', function () {
    $this->actingAs($this->user)->postJson('/api/cms/projects', ['name' => 'Nový projekt'])->assertCreated()->assertJsonPath('data.settings', [])->assertJsonPath('data.slug', 'novy-projekt');
    $this->postJson('/api/cms/projects', ['name' => 'Nový projekt'])->assertCreated()->assertJsonPath('data.slug', 'novy-projekt-2');
    $this->putJson('/api/cms/projects/'.$this->project->id, ['name' => 'Nová firma', 'revision' => 1])->assertOk()->assertJsonPath('data.revision', 2);
    $this->postJson('/api/cms/projects', ['name' => 'Wrong', 'settings' => ['site_type' => 'php']])->assertUnprocessable();
});

it('reports scoped domain page component and section counts', function () {
    $domain = app(DomainStore::class)->save($this->user, ['name' => 'app.example.com', 'project_id' => $this->project->id]);
    app(DocumentStore::class)->create('pages', $this->user, ['name' => 'Home', 'slug' => 'home', 'domain_id' => $domain->id]);
    app(DocumentStore::class)->create('components', $this->user, ['name' => 'Card', 'slug' => 'card', 'project_id' => $this->project->id]);
    app(DocumentStore::class)->create('sections', $this->user, ['name' => 'Header', 'slug' => 'header', 'project_id' => $this->project->id]);
    $this->actingAs($this->user)->getJson('/api/cms/projects/'.$this->project->id)->assertOk()->assertJsonPath('data.overview.domains_count', 1)->assertJsonPath('data.overview.pages_count', 1)->assertJsonPath('data.overview.components_count', 1)->assertJsonPath('data.overview.sections_count', 1)->assertJsonPath('data.overview.domains.0.name', 'app.example.com');
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/cms/projects/'.$this->project->id)->assertNotFound();
});

it('keeps the project copy empty and original domain data intact', function () {
    app(DomainStore::class)->save($this->user, ['name' => 'www.example.com', 'project_id' => $this->project->id]);
    $copy = $this->actingAs($this->user)->postJson('/api/cms/projects/'.$this->project->id.'/copy', ['name' => 'Firma kópia'])->assertCreated();
    $copy->assertJsonPath('data.settings', [])->assertJsonPath('data.overview.domains_count', 0);
    expect($copy->json('data.id'))->not->toBe($this->project->id);
    expect($this->project->domains()->count())->toBe(1);
});

it('gives every domain an independent stack', function () {
    $this->actingAs($this->user);
    $first = $this->postJson('/api/cms/domains', ['name' => 'app.example.com', 'project_id' => $this->project->id])->assertCreated()->assertJsonPath('data.settings.site_type', 'laravel13')->assertJsonPath('data.settings.setup.database_engine', 'pgsql')->json('data');
    $second = $this->postJson('/api/cms/domains', ['name' => 'api.example.com', 'project_id' => $this->project->id, 'settings' => ['site_type' => 'python']])->assertCreated()->json('data');
    $this->putJson('/api/cms/domains/'.$first['id'], ['name' => 'app.example.com', 'revision' => 1, 'settings' => ['site_type' => 'php']])->assertOk()->assertJsonPath('data.revision', 2);
    expect(Domain::find($second['id'])->settings['site_type'])->toBe('python');
    expect($this->project->fresh()->settings)->toBe([]);
});

it('saves Laravel options without creating a physical database', function () {
    $before = DB::select('select datname from pg_database order by datname');
    $settings = ['site_type' => 'laravel13', 'description' => 'Web', 'git_repository' => 'git@github.com:team/web.git', 'environment' => 'staging', 'setup' => ['from_template' => true, 'template' => 'business', 'login_page' => true, 'create_database' => true, 'database_engine' => 'pgsql']];
    $this->actingAs($this->user)->postJson('/api/cms/domains', ['name' => 'web.example.com', 'project_id' => $this->project->id, 'settings' => $settings])->assertCreated()->assertJsonPath('data.settings.setup.login_page', true);
    expect(DB::select('select datname from pg_database order by datname'))->toEqual($before);
});

it('supports each offered stack on a domain', function (string $type) {
    $this->actingAs($this->user)->postJson('/api/cms/domains', ['name' => 'web.example.com', 'project_id' => $this->project->id, 'settings' => ['site_type' => $type]])->assertCreated()->assertJsonPath('data.settings.site_type', $type);
})->with(['laravel13', 'php', 'python', 'nodejs', 'static']);

it('rejects invalid host names and settings', function ($data, $field) {
    $this->actingAs($this->user)->postJson('/api/cms/domains', array_replace(['name' => 'web.example.com', 'project_id' => $this->project->id], $data))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Domain::count())->toBe(0);
})->with([
    [['name' => 'https://example.com/path'], 'name'],
    [['settings' => ['site_type' => 'unknown']], 'settings.site_type'],
    [['settings' => ['setup' => '']], 'settings.setup'],
    [['settings' => ['setup' => ['database_engine' => 'unknown']]], 'settings.setup.database_engine'],
    [['settings' => ['git_repository' => 'https://user:password@example.com/repo']], 'settings.git_repository'],
]);

it('protects other owners and stale revisions and records domain history', function () {
    $domain = app(DomainStore::class)->save($this->user, ['name' => 'web.example.com', 'project_id' => $this->project->id]);
    $this->actingAs($this->user)->putJson('/api/cms/domains/'.$domain->id, ['name' => 'new.example.com', 'revision' => 1])->assertOk();
    $this->putJson('/api/cms/domains/'.$domain->id, ['name' => 'stale.example.com', 'revision' => 1])->assertConflict();
    expect(DB::table('cms_document_revisions')->where('document_type', 'domains')->where('document_id', $domain->id)->count())->toBe(2);
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/cms/domains/'.$domain->id)->assertNotFound();
    $this->postJson('/api/cms/domains', ['name' => 'stolen.example.com', 'project_id' => $this->project->id])->assertNotFound();
    $this->postJson('/api/cms/domains/'.$domain->id.'/copy', ['name' => 'stolen.example.com'])->assertNotFound();
});

it('copies edited domain settings without its pages and prevents duplicate hosts', function () {
    $domain = app(DomainStore::class)->save($this->user, ['name' => 'web.example.com', 'project_id' => $this->project->id]);
    app(DocumentStore::class)->create('pages', $this->user, ['name' => 'Home', 'slug' => 'home', 'domain_id' => $domain->id]);
    $this->actingAs($this->user)->postJson('/api/cms/domains/'.$domain->id.'/copy', ['name' => 'copy.example.com', 'settings' => ['site_type' => 'python']])->assertCreated()->assertJsonPath('data.pages_count', 0)->assertJsonPath('data.settings.site_type', 'python');
    expect($domain->fresh()->settings['site_type'])->toBe('laravel13');
    $this->postJson('/api/cms/domains', ['name' => 'WEB.EXAMPLE.COM', 'project_id' => $this->project->id])->assertUnprocessable();
});

it('protects new domain configuration from a stale whole-tree save', function () {
    $before = app(ProjectTree::class)->revision($this->user);
    app(DomainStore::class)->save($this->user, ['name' => 'web.example.com', 'project_id' => $this->project->id]);
    $this->actingAs($this->user)->postJson('/api/ui/save-projects', ['projects' => [], 'revision' => $before])->assertConflict();
    expect(Domain::count())->toBe(1);
});

it('moves historical settings once while preserving domain overrides and page links', function () {
    DB::table('cms_imports')->where('source', 'domain-settings-v1')->delete();
    $this->project->settings = ['project_type' => 'laravel13', 'description' => 'Inherited', 'setup' => ['login_page' => true]];
    $this->project->save();
    $domain = Domain::create(['project_id' => $this->project->id, 'legacy_id' => 'old-domain', 'name' => 'web.example.com', 'settings' => ['site_type' => 'python']]);
    $page = app(DocumentStore::class)->create('pages', $this->user, ['name' => 'Home', 'slug' => 'home', 'domain_id' => $domain->id]);
    app(DomainConfigurationMigration::class)->run();
    expect($this->project->fresh()->settings)->toBe([]);
    expect($domain->fresh()->settings)->toMatchArray(['site_type' => 'python', 'description' => 'Inherited']);
    expect($domain->fresh()->settings['setup']['login_page'])->toBeTrue();
    expect($page->fresh()->domain_id)->toBe($domain->id);
    $revision = $domain->fresh()->revision;
    app(DomainConfigurationMigration::class)->run();
    expect($domain->fresh()->revision)->toBe($revision);
});

it('renders the project overview and the separate domain editor', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('Prehľad projektu')->assertSee('Domény / subdomény')->assertSee('EDITOR DOMÉNY')->assertSee('Základ webu')->assertSee('select2.full.min.js', false);
});
