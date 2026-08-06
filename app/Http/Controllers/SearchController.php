<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\Genre;
use App\Models\Language;
use App\Models\Collection;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q'));

        $filters = [
            'category' => $request->input('category'),
            'genre' => $request->input('genre'),
            'language' => $request->input('language'),
            'type' => $request->input('type'),
            'resolution' => $request->input('resolution'),
            'rating' => $request->input('rating'),
            'sort' => $request->input('sort', 'latest'),
        ];

        $videos = Video::query()->with('category')->where('visibility', 'public');

        if ($query !== '') {
            $videos->where(function ($sub) use ($query) {
                $sub->where('title', 'like', "%{$query}%")
                    ->orWhere('tags', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%")
                    ->orWhere('full_description', 'like', "%{$query}%");
            });
        }

        if ($filters['category']) {
            $videos->where('category_id', $filters['category']);
        }

        if ($filters['language']) {
            $videos->where('language_id', $filters['language']);
        }

        if ($filters['type']) {
            $videos->where('video_type', $filters['type']);
        }

        if ($filters['resolution']) {
            $videos->where('resolution', $filters['resolution']);
        }

        if ($filters['rating']) {
            $videos->where('age_rating', $filters['rating']);
        }

        if ($filters['genre']) {
            $videos->whereHas('genres', fn ($g) => $g->where('genres.id', $filters['genre']));
        }

        switch ($filters['sort']) {
            case 'popular':
                $videos->orderByDesc('views');
                break;
            case 'rating':
                $videos->orderByDesc('views');
                break;
            default:
                $videos->latest();
        }

        $videos = $videos->paginate(20)->withQueryString();

        $categories = VideoCategory::where('status', true)->orderBy('sort_order')->orderBy('name')->get();
        $genres = Genre::where('status', true)->orderBy('name')->get();
        $languages = Language::where('status', true)->orderBy('name')->get();
        $resolutions = Video::where('visibility', 'public')->whereNotNull('resolution')->where('resolution', '!=', '')->distinct()->orderBy('resolution')->pluck('resolution')->values();
        $ratings = Video::where('visibility', 'public')->whereNotNull('age_rating')->where('age_rating', '!=', '')->distinct()->orderBy('age_rating')->pluck('age_rating')->values();

        return view('frontend.search.index', compact(
            'videos', 'query', 'filters', 'categories', 'genres', 'languages', 'resolutions', 'ratings'
        ));
    }

    public function suggest(Request $request)
    {
        $q = trim((string) $request->input('q'));

        if ($q === '') {
            return response()->json(['videos' => [], 'categories' => [], 'collections' => []]);
        }

        $videos = Video::where('visibility', 'public')
            ->where('title', 'like', "%{$q}%")
            ->orderByDesc('views')
            ->take(6)
            ->get(['id', 'title', 'slug', 'thumbnail', 'views', 'resolution'])
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'slug' => $video->slug,
                    'views' => $video->views,
                    'resolution' => $video->resolution,
                    'thumbnail' => $video->thumbnail
                        ? (str_starts_with($video->thumbnail, 'http') ? $video->thumbnail : asset('storage/' . $video->thumbnail))
                        : 'https://via.placeholder.com/160x90?text=Video',
                ];
            })
            ->values();

        $categories = VideoCategory::where('status', true)
            ->where('name', 'like', "%{$q}%")
            ->take(5)
            ->get(['id', 'name', 'slug', 'icon']);

        $collections = Collection::where('visibility', 'public')
            ->where('name', 'like', "%{$q}%")
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'slug', 'image'])
            ->map(function ($collection) {
                $image = $collection->image;
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'slug' => $collection->slug,
                    'image' => $image
                        ? (str_starts_with($image, 'http') ? $image : asset('storage/' . $image))
                        : 'https://via.placeholder.com/200x200?text=' . urlencode($collection->name),
                ];
            })
            ->values();

        return response()->json([
            'videos' => $videos,
            'categories' => $categories,
            'collections' => $collections,
        ]);
    }
}
