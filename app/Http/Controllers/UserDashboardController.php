<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $watchHistory = $user->watchHistories()->with('video')->latest('updated_at')->take(10)->get();
        $favorites = $user->favorites()->with('video')->latest()->get();
        $playlists = $user->playlists()->withCount('items')->latest()->get();

        return view('frontend.dashboard.index', compact('user', 'watchHistory', 'favorites', 'playlists'));
    }
}
