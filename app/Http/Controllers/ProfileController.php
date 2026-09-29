<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;


class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function resetLayout(Request $request): RedirectResponse
    {
        $keys = [
            'sidebar_width',
            'block_top_height',
            'block_middle_height',
            'sidebar_right_width',
            'block_right_top_height',
        ];

        $request->session()->forget($keys);

        DB::table('user_settings')
            ->where('user_id', $request->user()->id)
            ->whereIn('name', $keys)
            ->delete();

        $response = Redirect::route('profile.edit')->with('status', 'layout-reset');

        if ($request->header('HX-Request')) {
            $response->headers->set('HX-Refresh', 'true');
        }

        return $response;
    }

    public function saveTabs(Request $request)
    {
        DB::table('user_settings')->updateOrInsert(
            [
                'user_id' => auth()->id(), 
                'name' => 'open_canvas_tabs'
            ],
            [
                'value' => json_encode([
                    'tabs' => $request->input('tabs', []),
                    'activeTab' => $request->input('activeTab', 'tab-1')
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        return response()->json(['status' => 'success']);
    }

}
