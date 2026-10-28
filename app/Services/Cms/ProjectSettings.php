<?php

namespace App\Services\Cms;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectSettings
{
    public function normalize(array $settings): array
    {
        if (array_key_exists('setup', $settings) && ! is_array($settings['setup'])) {
            throw ValidationException::withMessages(['settings.setup' => 'Nastavenia vytvorenia projektu musia byť objekt.']);
        }
        Validator::make(['settings' => $settings], [
            'settings.project_type' => ['sometimes', 'filled', Rule::in(array_keys(config('project-editor.types')))],
            'settings.description' => ['sometimes', 'string', 'max:5000'],
            'settings.primary_domain' => ['sometimes', 'string', 'max:253', function ($attribute, $value, $fail) {
                if ($value !== '' && ! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    $fail('Zadajte doménu bez protokolu a cesty.');
                }
            }],
            'settings.git_repository' => ['sometimes', 'string', 'max:500', function ($attribute, $value, $fail) {
                if ($value !== '' && ! preg_match('#^(https://[^\s]+|git@[a-zA-Z0-9.-]+:[a-zA-Z0-9_./-]+)$#', $value)) {
                    $fail('Zadajte HTTPS alebo SSH adresu Git repozitára.');
                }
                if (preg_match('#^https://[^/]*@#', $value)) {
                    $fail('Do adresy repozitára nevkladajte prístupové údaje.');
                }
            }],
            'settings.environment' => ['sometimes', 'filled', Rule::in(array_keys(config('project-editor.environments')))],
            'settings.setup' => ['sometimes', 'array'],
            'settings.setup.from_template' => ['sometimes', 'boolean'],
            'settings.setup.template' => ['sometimes', 'filled', Rule::in(array_keys(config('project-editor.templates')))],
            'settings.setup.login_page' => ['sometimes', 'boolean'],
            'settings.setup.create_database' => ['sometimes', 'boolean'],
            'settings.setup.database_engine' => ['sometimes', 'filled', Rule::in(array_keys(config('project-editor.databases')))],
        ])->validate();

        return array_replace_recursive(config('project-editor.defaults'), $settings);
    }
}
