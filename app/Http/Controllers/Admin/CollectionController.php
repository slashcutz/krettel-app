<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\User;
use App\Models\Video;

class CollectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $collections = Collection::with('user')->withCount('items')->latest()->paginate(15);
        return view('admin.collections.index', compact('collections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::orderBy('name')->get();
        $videos = Video::latest()->get();

        return view('admin.collections.create', compact('users', 'videos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:collections,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'terabox_image' => 'nullable|string|max:500',
            'user_id' => 'nullable|exists:users,id',
            'visibility' => 'required|in:public,private',
            'video_ids' => 'nullable|array',
            'video_ids.*' => 'exists:videos,id',
        ]);

        $collection = Collection::create([
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'image' => $this->storeImage($request),
            'terabox_image' => $request->input('terabox_image'),
            'user_id' => $request->input('user_id') ?? auth()->id(),
            'visibility' => $request->input('visibility'),
        ]);

        $this->syncItems($collection, $request->input('video_ids', []));

        return redirect()->route('admin.collections.show', $collection->id)
            ->with('success', 'Collection created successfully.');
    }

    protected function storeImage(Request $request): ?string
    {
        if ($request->hasFile('image')) {
            return $request->file('image')->store('collections', 'public');
        }

        return null;
    }

    protected function syncItems(Collection $collection, array $videoIds): void
    {
        CollectionItem::where('collection_id', $collection->id)->delete();

        foreach (array_values(array_filter($videoIds)) as $index => $videoId) {
            CollectionItem::create([
                'collection_id' => $collection->id,
                'video_id' => $videoId,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Collection $collection)
    {
        $collection->load(['user', 'items.video']);

        return view('admin.collections.show', compact('collection'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Collection $collection)
    {
        $collection->load('user');
        $users = User::orderBy('name')->get();
        $videos = Video::latest()->get();

        return view('admin.collections.edit', compact('collection', 'users', 'videos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Collection $collection)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:collections,slug,' . $collection->id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'terabox_image' => 'nullable|string|max:500',
            'user_id' => 'nullable|exists:users,id',
            'visibility' => 'required|in:public,private',
            'video_ids' => 'nullable|array',
            'video_ids.*' => 'exists:videos,id',
        ]);

        $data = $request->only([
            'name', 'slug', 'description', 'user_id', 'visibility', 'terabox_image',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request);
        }

        $collection->update($data);

        $this->syncItems($collection, $request->input('video_ids', []));

        return redirect()->route('admin.collections.index')
            ->with('success', 'Collection updated successfully.');
    }

    public function destroy(Collection $collection)
    {
        \App\Models\CollectionItem::where('collection_id', $collection->id)->delete();
        $collection->delete();

        return redirect()->route('admin.collections.index')
            ->with('success', 'Collection deleted successfully.');
    }
}
