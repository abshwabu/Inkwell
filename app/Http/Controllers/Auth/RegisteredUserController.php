<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                'unique:'.User::class,
            ],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'username.regex' => 'The username may only contain letters, numbers, dashes, and underscores.',
        ]);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ];

        if ($request->filled('username')) {
            $userData['username'] = Str::slug($request->username);
        }

        $user = User::create($userData);

        // Merge lightweight guest preferences from cookie into newly created account
        $guestPrefsCookie = $request->cookie('inkwell_guest_prefs');
        if ($guestPrefsCookie) {
            $guestPrefs = json_decode($guestPrefsCookie, true);
            if (is_array($guestPrefs)) {
                if (! empty($guestPrefs['categories'])) {
                    $user->preferredCategories()->syncWithoutDetaching($guestPrefs['categories']);
                }
                if (! empty($guestPrefs['tags'])) {
                    $user->preferredTags()->syncWithoutDetaching($guestPrefs['tags']);
                }
            }
        }

        if (config('auth.verify_email', false)) {
            event(new Registered($user));
        } else {
            $user->markEmailAsVerified();
        }

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false))->withoutCookie('inkwell_guest_prefs');
    }
}
