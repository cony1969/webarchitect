<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Cms\DocumentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
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

        // Keep restrictive CMS ownership foreign keys explicit in the existing account flow.
        foreach (DocumentStore::MODELS as $model) {
            if ($model::withTrashed()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'password' => 'Účet obsahuje dokumenty CMS. Pred zmazaním účtu treba vyriešiť ich odstránenie alebo odovzdanie.',
                ])->errorBag('userDeletion');
            }
        }

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
        $request->validate([
            'tabs' => ['present', 'array', 'max:50'],
            'tabs.*' => ['array'], 'tabs.*.id' => ['required', 'string', 'max:180'],
            'activeTab' => ['required', 'string', 'max:180'], 'user_id' => ['prohibited'],
        ]);
        abort_if(strlen($request->getContent()) > 524288, 422);

        DB::table('user_settings')->updateOrInsert(
            [
                'user_id' => auth()->id(),
                'name' => 'open_canvas_tabs',
            ],
            [
                'value' => json_encode([
                    'tabs' => $request->input('tabs', []),
                    'activeTab' => $request->input('activeTab', 'tab-1'),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['status' => 'success']);
    }
}
