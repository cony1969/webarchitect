<?php

namespace App\Services\Cms;

use App\Models\Cms\Domain;
use App\Models\Cms\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DomainConfigurationMigration
{
    public function run(): void
    {
        if (DB::table('cms_imports')->where('source', 'domain-settings-v1')->exists()) {
            return;
        }
        DB::transaction(function () {
            $backup = ['projects' => [], 'domains' => []];
            foreach (Project::withTrashed()->orderBy('user_id')->get() as $project) {
                User::whereKey($project->user_id)->lockForUpdate()->firstOrFail();
                $backup['projects'][$project->id] = ['settings' => $project->settings, 'source_payload' => $project->getAttribute('source_payload')];
                $inherited = $project->settings ?? [];
                if (isset($inherited['project_type'])) {
                    $inherited['site_type'] = $inherited['project_type'];
                }
                unset($inherited['project_type'], $inherited['primary_domain']);
                foreach (Domain::withTrashed()->where('project_id', $project->id)->get() as $domain) {
                    $backup['domains'][$domain->id] = ['settings' => $domain->settings, 'source_payload' => $domain->source_payload];
                    DB::table('cms_document_revisions')->insertOrIgnore(['document_type' => 'domains', 'document_id' => $domain->id, 'revision' => $domain->revision, 'created_by' => $project->user_id, 'payload' => json_encode($domain->toArray(), JSON_THROW_ON_ERROR), 'created_at' => now()]);
                    $own = $domain->settings ?? [];
                    if (isset($own['project_type']) && ! isset($own['site_type'])) {
                        $own['site_type'] = $own['project_type'];
                    }
                    unset($own['project_type'], $own['primary_domain']);
                    $domain->settings = app(DomainSettings::class)->normalize(array_replace_recursive($inherited, $own));
                    app(DomainStore::class)->persist($domain, $project->user_id);
                }
                $project->settings = [];
                $project->source_payload = Arr::only($project->getAttribute('source_payload') ?? [], ['id', 'name']);
                app(DocumentStore::class)->save($project, $project->user_id);
            }
            foreach (User::whereIn('id', Project::withTrashed()->select('user_id'))->get() as $user) {
                app(DomainStore::class)->bumpTree($user);
            }
            DB::table('cms_imports')->insert(['source' => 'domain-settings-v1', 'manifest' => json_encode($backup, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }
}
