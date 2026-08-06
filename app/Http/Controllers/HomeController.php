<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Banner;
use App\Models\Collection;

class HomeController extends Controller
{
    public function index()
    {
        $featured = \Illuminate\Support\Facades\Cache::remember('home_featured_banner', 3600, function () {
            return Banner::where('status', true)->orderBy('sort_order')->first();
        });

        $trending = \Illuminate\Support\Facades\Cache::remember('home_trending_videos', 3600, function () {
            return Video::with('category')->where('visibility', 'public')->orderBy('views', 'desc')->take(10)->get();
        });

        $newReleases = \Illuminate\Support\Facades\Cache::remember('home_new_releases', 3600, function () {
            return Video::with('category')->where('visibility', 'public')->orderBy('created_at', 'desc')->take(10)->get();
        });

        $recommended = \Illuminate\Support\Facades\Cache::remember('home_recommended', 3600, function () {
            return Video::with('category')->where('visibility', 'public')->inRandomOrder()->take(10)->get();
        });

        $collections = \Illuminate\Support\Facades\Cache::remember('home_collections', 3600, function () {
            return Collection::with('items')->where('visibility', 'public')->latest()->take(10)->get();
        });

        return view('frontend.home.index', compact('featured', 'trending', 'newReleases', 'recommended', 'collections'));
    }
}
