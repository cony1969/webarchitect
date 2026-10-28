<?php

namespace App\Services\Cms;

use Illuminate\Validation\ValidationException;

class ComponentStyleRows
{
    public function apply(array $source, int $index, array $rows): array
    {
        $list = array_is_list($source['objects']);
        $roots = $list ? $source['objects'] : [$source['objects']];
        if (! isset($roots[$index]) || ! is_array($roots[$index])) {
            throw ValidationException::withMessages(['style_root' => 'Vybraný prvok komponentu už neexistuje.']);
        }
        $declarations = [];
        $rules = [];
        foreach ($this->normalizeRows($rows) as $row) {
            if ($row['pseudos']) {
                $rules[] = $row;
            } else {
                $declarations[] = $row['name'].': '.$row['value'].';';
            }
        }
        $node = &$roots[$index];
        $style = implode(' ', $declarations);
        $nested = array_key_exists('style', $node['attributes'] ?? []);
        if ($style === '') {
            unset($node['style'], $node['attributes']['style']);
        } elseif ($nested) {
            $node['attributes']['style'] = $style;
            unset($node['style']);
        } else {
            $node['style'] = $style;
        }
        $nestedRules = array_key_exists('style_rules', $node['attributes'] ?? []);
        if (! $rules) {
            unset($node['style_rules'], $node['attributes']['style_rules']);
        } elseif ($nestedRules) {
            $node['attributes']['style_rules'] = $rules;
            unset($node['style_rules']);
        } else {
            $node['style_rules'] = $rules;
        }
        $source['objects'] = $list ? $roots : $roots[0];

        return $source;
    }

    public function normalizeRows(mixed $rows, bool $pseudoOnly = false): array
    {
        $fail = fn () => throw ValidationException::withMessages(['style_rows' => 'Neplatné CSS vlastnosti alebo pseudo stavy.']);
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 200) {
            $fail();
        }
        $allowed = array_column(json_decode(file_get_contents(resource_path('data/css-pseudos.json')), true), 'value');
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? '') || ! is_string($row['value'] ?? '')) {
                $fail();
            }
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if (strlen($name) > 128 || ! preg_match('/^(?:--[a-zA-Z0-9_-]+|-?[a-zA-Z][a-zA-Z0-9-]*)$/', $name)) {
                $fail();
            }
            $value = trim($row['value'] ?? '');
            if (strlen($value) > 20000) {
                $fail();
            }
            $this->validateValue($value);
            $pseudos = $row['pseudos'] ?? [];
            if (! is_array($pseudos) || ! array_is_list($pseudos) || count($pseudos) > count($allowed)) {
                $fail();
            }
            foreach ($pseudos as $pseudo) {
                if (! is_string($pseudo) || ! in_array($pseudo, $allowed, true)) {
                    $fail();
                }
            }
            if ($pseudoOnly && ! $pseudos) {
                $fail();
            }
            $result[] = ['name' => $name, 'value' => $value, 'pseudos' => array_values(array_unique($pseudos))];
        }

        return $result;
    }

    private function validateValue(string $value): void
    {
        $quote = '';
        $escaped = false;
        $comment = false;
        $depth = 0;
        for ($i = 0; $i < strlen($value); $i++) {
            $char = $value[$i];
            $next = $value[$i + 1] ?? '';
            if ($comment) {
                if ($char === '*' && $next === '/') {
                    $i++;
                    $comment = false;
                }

                continue;
            }
            if ($escaped) {
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                $escaped = true;

                continue;
            }
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }

                continue;
            }
            if ($char === '/' && $next === '*') {
                $i++;
                $comment = true;

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            }
            if (str_contains('([{', $char)) {
                $depth++;
            }
            if (str_contains(')]}', $char)) {
                $depth--;
            }
            if (($char === ';' && $depth === 0) || $depth < 0) {
                throw ValidationException::withMessages(['style_rows' => 'Do hodnoty zadajte jednu CSS hodnotu. Ďalšiu vlastnosť pridajte tlačidlom +.']);
            }
        }
        if ($quote !== '' || $depth !== 0 || $comment) {
            throw ValidationException::withMessages(['style_rows' => 'CSS hodnota obsahuje neuzavreté úvodzovky, zátvorky alebo komentár.']);
        }
    }
}
