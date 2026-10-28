<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\Project;
use App\Models\Cms\Section;

class ProjectOverview
{
    public function data(Project $project): array
    {
        $project->loadCount(['domains', 'pages']);
        $domains = $project->domains()->withCount('pages')->get(['id', 'project_id', 'legacy_id', 'name', 'settings', 'status', 'revision']);

        return array_merge($project->toArray(), [
            'editor_kind' => 'project',
            'overview' => [
                'domains' => $domains->map(fn ($domain) => ['id' => $domain->id, 'legacy_id' => $domain->legacy_id, 'name' => $domain->name, 'site_type' => $domain->settings['site_type'] ?? 'laravel13', 'pages_count' => $domain->pages_count])->all(),
                'domains_count' => $project->domains_count, 'pages_count' => $project->pages_count,
                'components_count' => Component::where('project_id', $project->id)->where('user_id', $project->user_id)->count(),
                'sections_count' => Section::where('project_id', $project->id)->where('user_id', $project->user_id)->count(),
                'updated_at' => $project->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
