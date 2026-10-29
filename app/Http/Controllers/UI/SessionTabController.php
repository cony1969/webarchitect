<?php

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionTabController extends Controller
{
    private const PANELS = [
        'component_properties' => ['basic', 'bootstrap', 'css'],
        'domain_properties' => ['general', 'bootstrap'],
        'appearance' => ['templates', 'other'],
        'legacy_properties' => ['basic', 'appearance', 'bootstrap', 'advanced'],
        'component_view' => ['preview', 'json'],
    ];

    public function __invoke(Request $request)
    {
        $panel = $request->input('panel');
        $data = $request->validate([
            'panel' => ['required', Rule::in(array_keys(self::PANELS))],
            'tab' => ['required', 'string', Rule::in(is_string($panel) ? (self::PANELS[$panel] ?? []) : [])],
            'user_id' => ['prohibited'],
        ]);
        $request->session()->put('wa.tabs.'.$request->user()->id.'.'.$data['panel'], $data['tab']);

        return response()->json(['status' => 'saved']);
    }
}
