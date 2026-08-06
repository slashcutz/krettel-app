<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileSetupController extends Controller
{
    public function create()
    {
        return view('frontend.profile.setup');
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:profiles'],
            'language' => ['required', 'string', 'max:10'],
            'theme' => ['required', 'string', 'max:10'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = Auth::user();
        
        $profile = $user->profile()->create([
            'username' => $request->username,
            'language' => $request->language,
            'theme' => $request->theme,
        ]);

        if ($request->hasFile('avatar')) {
            // Placeholder for Spatie Media Library upload
            $profile->addMediaFromRequest('avatar')->toMediaCollection('avatars');
        }

        return redirect()->route('dashboard')->with('status', 'Profile setup completed!');
    }
}
