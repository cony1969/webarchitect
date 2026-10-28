<?php

namespace App\Services\Cms;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomainSettings
{
    public function normalize(array $settings): array
    {
        if (array_key_exists('setup', $settings) && ! is_array($settings['setup'])) {
            throw ValidationException::withMessages(['settings.setup' => 'Nastavenia vytvorenia webu musia byť objekt.']);
        }
        Validator::make(['settings' => $settings], [
            'settings.bootstrap' => ['sometimes', 'array:colors'],
            'settings.bootstrap.colors' => ['sometimes', 'array:'.implode(',', array_keys(config('domain-editor.bootstrap_colors')))],
            'settings.bootstrap.colors.*' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'settings.site_type' => ['sometimes', 'filled', Rule::in(array_keys(config('domain-editor.types')))],
            'settings.description' => ['sometimes', 'string', 'max:5000'],
            'settings.git_repository' => ['sometimes', 'string', 'max:500', function ($attribute, $value, $fail) {
                if ($value !== '' && ! preg_match('#^(https://[^\s]+|git@[a-zA-Z0-9.-]+:[a-zA-Z0-9_./-]+)$#', $value)) {
                    $fail('Zadajte HTTPS alebo SSH adresu Git repozitára.');
                }
                if (preg_match('#^https://[^/]*@#', $value)) {
                    $fail('Do adresy repozitára nevkladajte prístupové údaje.');
                }
            }],
            'settings.environment' => ['sometimes', 'filled', Rule::in(array_keys(config('domain-editor.environments')))],
            'settings.setup' => ['sometimes', 'array'],
            'settings.setup.from_template' => ['sometimes', 'boolean'],
            'settings.setup.template' => ['sometimes', 'filled', Rule::in(array_keys(config('domain-editor.templates')))],
            'settings.setup.login_page' => ['sometimes', 'boolean'],
            'settings.setup.create_database' => ['sometimes', 'boolean'],
            'settings.setup.database_engine' => ['sometimes', 'filled', Rule::in(array_keys(config('domain-editor.databases')))],
        ])->validate();

        $result = array_replace_recursive(config('domain-editor.defaults'), $settings);
        foreach ($result['bootstrap']['colors'] as &$color) {
            $color = strtolower($color);
            if (strlen($color) === 4) {
                $color = '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
            }
        }
        unset($color);

        return $result;
    }
}
