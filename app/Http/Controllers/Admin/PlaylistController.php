<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use App\Models\Video;

class PlaylistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $playlists = Playlist::with('user')->latest()->paginate(15);
        return view('admin.playlists.index', compact('playlists'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::orderBy('name')->get();
        $videos = Video::latest()->get();

        return view('admin.playlists.create', compact('users', 'videos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:playlists,slug',
            'description' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'visibility' => 'required|in:public,private',
            'video_ids' => 'nullable|array',
            'video_ids.*' => 'exists:videos,id',
        ]);

        $playlist = Playlist::create([
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'user_id' => $request->input('user_id') ?? auth()->id(),
            'visibility' => $request->input('visibility'),
        ]);

        $this->syncItems($playlist, $request->input('video_ids', []));

        return redirect()->route('admin.playlists.show', $playlist->id)
            ->with('success', 'Playlist created successfully.');
    }

    protected function syncItems(Playlist $playlist, array $videoIds): void
    {
        PlaylistItem::where('playlist_id', $playlist->id)->delete();

        foreach (array_values(array_filter($videoIds)) as $index => $videoId) {
            PlaylistItem::create([
                'playlist_id' => $playlist->id,
                'video_id' => $videoId,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $playlist = Playlist::with(['user', 'items.video'])->findOrFail($id);

        return view('admin.playlists.show', compact('playlist'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $playlist = Playlist::with('user')->findOrFail($id);
        $users = User::orderBy('name')->get();
        $videos = Video::latest()->get();

        return view('admin.playlists.edit', compact('playlist', 'users', 'videos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $playlist = Playlist::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:playlists,slug,' . $id,
            'description' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'visibility' => 'required|in:public,private',
            'video_ids' => 'nullable|array',
            'video_ids.*' => 'exists:videos,id',
        ]);

        $playlist->update($request->only([
            'name', 'slug', 'description', 'user_id', 'visibility',
        ]));

        $this->syncItems($playlist, $request->input('video_ids', []));

        return redirect()->route('admin.playlists.index')
            ->with('success', 'Playlist updated successfully.');
    }

    public function destroy(string $id)
    {
        $playlist = Playlist::findOrFail($id);

        \App\Models\PlaylistItem::where('playlist_id', $playlist->id)->delete();
        $playlist->delete();

        return redirect()->route('admin.playlists.index')
            ->with('success', 'Playlist deleted successfully.');
    }
}
