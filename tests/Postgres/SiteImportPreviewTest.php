<?php

use App\Models\User;
use App\Services\Cms\AsusSiteAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->root = sys_get_temp_dir().'/wa-import-test-'.Str::uuid();
    File::makeDirectory($this->root);
    config(['site-import.user_ids' => [$this->user->id], 'site-import.sources' => ['web.example' => ['name' => 'Test web', 'domain' => 'web.example', 'aliases' => ['www.web.example'], 'root' => $this->root]]]);
    $this->putSource = function ($relative, $content) {
        File::ensureDirectoryExists(dirname($this->root.'/'.$relative));
        file_put_contents($this->root.'/'.$relative, $content);
    };
    ($this->putSource)('index.php', '<?php file_put_contents(__DIR__."/executed.txt", "bad"); include "sections/$section.php"; /* include "sections/unused.php"; */ ?><html><head><title>Test web</title></head><body><a href="https://moja.web.example">Zone</a><img src="missing.png"></body></html>');
    ($this->putSource)('pages/index.php', '<?php $Sections["index"] = array("about");');
    ($this->putSource)('sections/about.php', '<section id="about"><form action="controllers/send.php?token=do-not-leak" method="post"></form></section>');
    ($this->putSource)('sections/unused.php', '<section>unused</section>');
    ($this->putSource)('controllers/send.php', '<?php $password="private-do-not-leak";');
    ($this->putSource)('assets/vendor/bootstrap/bootstrap.min.css', 'body{}');
    ($this->putSource)('assets/img/image.svg', '<svg/>');
    ($this->putSource)('includes/head.php', <<<'HTML'
<head><title>Shared head</title></head><?php echo '<a href="'.$privateVariable.'">'; ?>
HTML
    );
    ($this->putSource)('.env', 'PASSWORD=private-env-do-not-leak');
    ($this->putSource)('backup/database.sql', 'private-backup-do-not-leak');
    ($this->putSource)('sample.html', '<title>Sample</title>');
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('analyzes the source without executing PHP or changing CMS records', function () {
    $before = DB::table('cms_projects')->count();
    $report = app(AsusSiteAnalyzer::class)->analyze($this->user, 'web.example');
    expect($report['mode'])->toBe('analysis_only')->and($report['site_type'])->toBe('php')
        ->and($report['pages'])->toHaveCount(2)->and($report['sections'])->toHaveCount(2)
        ->and($report['used_sections'])->toBe(['sections/about.php'])->and($report['files_count'])->toBe(9)
        ->and($report['assets']['styles'])->toBe(1)->and($report['assets']['images'])->toBe(1)
        ->and($report['aliases'])->toBe(['www.web.example'])
        ->and($report['linked_subdomains_unavailable'])->toBe(['moja.web.example'])
        ->and($report['missing_files'])->toBe(['missing.png'])
        ->and($report['forms'][0]['action'])->toBe('controllers/send.php')
        ->and(file_exists($this->root.'/executed.txt'))->toBeFalse()
        ->and(DB::table('cms_projects')->count())->toBe($before);
    $encoded = json_encode($report);
    expect($encoded)->not->toContain('private-do-not-leak', 'do-not-leak', 'PASSWORD=', $this->root, '<?php');
});

it('authorizes source listing and analysis on the server', function () {
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/cms/site-import/sources')->assertForbidden();
    $this->postJson('/api/cms/site-import/analyze', ['source_id' => 'web.example'])->assertForbidden();
    $this->actingAs($this->user)->getJson('/api/cms/site-import/sources')->assertOk()->assertJsonPath('data.0.id', 'web.example');
    $this->postJson('/api/cms/site-import/analyze', ['source_id' => 'web.example'])->assertOk()->assertJsonPath('data.mode', 'analysis_only')->assertHeader('Cache-Control', 'no-store, private');
});

it('rejects arbitrary paths and unlisted sources', function () {
    $this->actingAs($this->user)->postJson('/api/cms/site-import/analyze', ['source_id' => '../../etc'])->assertNotFound();
    $this->postJson('/api/cms/site-import/analyze', ['source_id' => 'web.example', 'root' => '/etc'])->assertUnprocessable();
    $this->postJson('/api/cms/site-import/analyze', ['source_id' => 'web.example', 'url' => 'http://localhost'])->assertUnprocessable();
});

it('skips symlinks and private files and detects changed content', function () {
    symlink('/etc/passwd', $this->root.'/linked.txt');
    symlink(dirname($this->root), $this->root.'/escape');
    $first = app(AsusSiteAnalyzer::class)->analyze($this->user, 'web.example');
    expect($first['files_count'])->toBe(9);
    ($this->putSource)('assets/img/image.svg', '<svg><path/></svg>');
    $second = app(AsusSiteAnalyzer::class)->analyze($this->user, 'web.example');
    expect($first['fingerprint'])->not->toBe($second['fingerprint']);
});

it('reports an independent subdomain source separately from a shared alias', function () {
    config(['site-import.sources.moja' => ['name' => 'Zone', 'domain' => 'moja.web.example', 'aliases' => [], 'root' => $this->root]]);
    $report = app(AsusSiteAnalyzer::class)->analyze($this->user, 'web.example');
    expect($report['subdomains'])->toBe([['id' => 'moja', 'domain' => 'moja.web.example']])->and($report['linked_subdomains_unavailable'])->toBe([]);
});

it('limits the analyzed data and handles missing sources', function () {
    config(['site-import.max_bytes' => 1]);
    $this->actingAs($this->user)->postJson('/api/cms/site-import/analyze', ['source_id' => 'web.example'])->assertUnprocessable();
    config(['site-import.sources.web.example.root' => '/nonexistent']);
    config(['site-import.sources' => ['gone' => ['name' => 'Gone', 'domain' => 'gone.example', 'root' => $this->root.'/gone']]]);
    $this->postJson('/api/cms/site-import/analyze', ['source_id' => 'gone'])->assertUnprocessable();
});
