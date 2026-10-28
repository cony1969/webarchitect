<?php

namespace App\Services\Cms;

class ComponentPropertyCatalog
{
    public function data(): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(public_path('css/bootstrap.min.css')));
        preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $css, $matches);
        $classes = array_values(array_unique($matches[1]));
        sort($classes);
        $known = array_fill_keys($classes, true);
        $breakpoints = ['' => 'Základné (všetky šírky)', 'sm' => 'sm · od 576 px', 'md' => 'md · od 768 px', 'lg' => 'lg · od 992 px', 'xl' => 'xl · od 1200 px', 'xxl' => 'xxl · od 1400 px'];
        $fields = [];
        $add = function ($id, $label, $prefix, $values, $responsive = false, $common = false) use (&$fields, $known, $breakpoints) {
            $options = [];
            foreach (array_keys($breakpoints) as $bp) {
                $tokens = array_map(fn ($value) => $prefix.($responsive && $bp ? '-'.$bp : '').($value === '' ? '' : '-'.$value), $values);
                $options[$bp] = array_values(array_filter($tokens, fn ($token) => isset($known[$token])));
            }
            $fields[] = compact('id', 'label', 'responsive', 'common', 'options');
        };
        $colors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'white'];
        $add('container', 'Typ kontajnera', 'container', ['', 'fluid', 'sm', 'md', 'lg', 'xl', 'xxl'], false, true);
        $add('display', 'Zobrazenie', 'd', ['none', 'inline', 'inline-block', 'block', 'grid', 'table', 'table-cell', 'table-row', 'flex', 'inline-flex'], true, true);
        $add('padding', 'Vnútorné odsadenie', 'p', range(0, 5), true, true);
        $add('margin', 'Vonkajšie odsadenie', 'm', [...range(0, 5), 'auto'], true, true);
        $add('width', 'Šírka', 'w', ['25', '50', '75', '100', 'auto'], false, true);
        $add('background', 'Farba pozadia', 'bg', [...$colors, 'body', 'transparent'], false, true);
        $add('color', 'Farba textu', 'text', [...$colors, 'body', 'muted', 'black-50', 'white-50'], false, true);
        foreach (['p' => 'Vnútorné odsadenie', 'm' => 'Vonkajšie odsadenie'] as $prefix => $label) {
            foreach (['x' => 'vodorovne', 'y' => 'zvislo', 't' => 'hore', 'b' => 'dole', 's' => 'začiatok', 'e' => 'koniec'] as $side => $name) {
                $add($prefix.$side, $label.' · '.$name, $prefix.$side, $prefix === 'm' ? [...range(0, 5), 'auto'] : range(0, 5), true);
            }
        }
        $add('height', 'Výška', 'h', ['25', '50', '75', '100', 'auto']);
        $add('gap', 'Medzery medzi prvkami', 'gap', range(0, 5), true);
        $add('direction', 'Smer flex', 'flex', ['row', 'row-reverse', 'column', 'column-reverse'], true);
        $add('wrap', 'Zalamovanie flex', 'flex', ['wrap', 'nowrap', 'wrap-reverse'], true);
        $add('justify', 'Rozloženie obsahu', 'justify-content', ['start', 'end', 'center', 'between', 'around', 'evenly'], true);
        $add('align', 'Zarovnanie prvkov', 'align-items', ['start', 'end', 'center', 'baseline', 'stretch'], true);
        $add('self', 'Zarovnanie samotného prvku', 'align-self', ['auto', 'start', 'end', 'center', 'baseline', 'stretch'], true);
        $add('grow', 'Roztiahnutie flex', 'flex-grow', [0, 1], true);
        $add('shrink', 'Zmenšenie flex', 'flex-shrink', [0, 1], true);
        $add('order', 'Poradie prvku', 'order', ['first', ...range(0, 5), 'last'], true);
        $add('text-align', 'Zarovnanie textu', 'text', ['start', 'center', 'end'], true);
        $add('font-size', 'Veľkosť textu', 'fs', range(1, 6));
        $add('font-weight', 'Hrúbka textu', 'fw', ['bold', 'bolder', 'normal', 'light', 'lighter']);
        $add('font-style', 'Štýl textu', 'fst', ['italic', 'normal']);
        $add('border', 'Orámovanie', 'border', ['', 0]);
        $add('border-color', 'Farba orámovania', 'border', $colors);
        $add('border-width', 'Hrúbka orámovania', 'border', range(1, 5));
        $add('rounded', 'Zaoblenie', 'rounded', ['', 0, 1, 2, 3, 'circle', 'pill']);
        $add('shadow', 'Tieň', 'shadow', ['', 'sm', 'lg', 'none']);
        $add('overflow', 'Pretekanie obsahu', 'overflow', ['auto', 'hidden', 'visible', 'scroll']);
        $add('position', 'Pozícia', 'position', ['static', 'relative', 'absolute', 'fixed', 'sticky']);
        $add('float', 'Obtekanie', 'float', ['start', 'end', 'none'], true);
        $add('user-select', 'Výber textu', 'user-select', ['all', 'auto', 'none']);

        return compact('classes', 'breakpoints', 'fields');
    }
}
