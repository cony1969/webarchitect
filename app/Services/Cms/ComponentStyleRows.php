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
        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            // A partially completed unnamed row is intentionally not part of the saved component.
            if ($name === '') {
                continue;
            }
            if (! preg_match('/^(?:--[a-zA-Z0-9_-]+|-?[a-zA-Z][a-zA-Z0-9-]*)$/', $name)) {
                throw ValidationException::withMessages(['style_rows' => 'Názov CSS vlastnosti nie je platný: '.$name]);
            }
            $value = trim($row['value'] ?? '');
            $this->validateValue($value);
            $declarations[] = $name.': '.$value.';';
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
        $source['objects'] = $list ? $roots : $roots[0];

        return $source;
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
