<?php

namespace App\Http\Controllers;

use App\Models\Collection;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Collection::withCount('items')
            ->with('user')
            ->where('visibility', 'public')
            ->latest()
            ->paginate(24);

        return view('frontend.collection.index', compact('collections'));
    }

    public function show($slug)
    {
        $collection = Collection::with('items.video')->where('slug', $slug)->firstOrFail();

        if ($collection->visibility !== 'public') {
            abort(404);
        }

        $videos = $collection->items->map(fn ($item) => $item->video)
            ->filter(fn ($video) => $video && $video->visibility === 'public')
            ->values();

        switch (request()->get('sort')) {
            case 'popular':
                $videos = $videos->sortByDesc('views')->values();
                break;
            case 'rating':
                $videos = $videos->sortByDesc('views')->values();
                break;
            default:
                $videos = $videos->sortByDesc('created_at')->values();
        }

        $related = Collection::withCount('items')
            ->where('visibility', 'public')
            ->where('id', '!=', $collection->id)
            ->latest()
            ->take(8)
            ->get();

        return view('frontend.collection.show', compact('collection', 'videos', 'related'));
    }
}
