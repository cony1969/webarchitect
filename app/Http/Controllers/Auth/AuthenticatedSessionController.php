<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Ak sú v DB user_settings uložené hodnoty, inicializujeme nimi session.
        // Ak v DB nie sú, fallback správanie ostáva bez zmeny.
        $settingKeys = [
            'sidebar_width',
            'block_top_height',
            'block_middle_height',
            'sidebar_right_width',
            'block_right_top_height',
            'sidebar_active_tab',
            'sidebar_open_category',
        ];

        $settings = DB::table('user_settings')
            ->where('user_id', Auth::id())
            ->whereIn('name', $settingKeys)
            ->pluck('value', 'name')
            ->toArray();

        $numericKeys = [
            'sidebar_width',
            'block_top_height',
            'block_middle_height',
            'sidebar_right_width',
            'block_right_top_height',
        ];

        $sessionPayload = [];
        foreach ($settingKeys as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = $settings[$key];
            if ($value === null || $value === '') {
                continue;
            }

            if (in_array($key, $numericKeys, true) && is_numeric($value)) {
                $value = (int) $value;
            }

            $sessionPayload[$key] = $value;
        }

        if (!empty($sessionPayload)) {
            $request->session()->put($sessionPayload);
        }

        // Ak požiadavka prišla cez HTMX, okamžite presmeruj celé okno prehliadača
        if ($request->header('HX-Request')) {
            return response('', 204)->header('HX-Redirect', route('dashboard'));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
