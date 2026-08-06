<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banners = Banner::with('video')->latest()->paginate(10);
        return view('admin.banners.index', compact('banners'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $videos = \App\Models\Video::orderBy('title')->get();

        return view('admin.banners.create', compact('videos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'video_id' => 'nullable|exists:videos,id',
            'sort_order' => 'integer',
            'status' => 'in:active,inactive,draft',
        ]);

        Banner::create($request->only([
            'title', 'subtitle', 'image_url', 'link_url', 'video_id', 'sort_order', 'status',
        ]));

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $banner = Banner::findOrFail($id);
        $videos = \App\Models\Video::orderBy('title')->get();

        return view('admin.banners.edit', compact('banner', 'videos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'video_id' => 'nullable|exists:videos,id',
            'sort_order' => 'integer',
            'status' => 'in:active,inactive,draft',
        ]);

        $banner->update($request->only([
            'title', 'subtitle', 'image_url', 'link_url', 'video_id', 'sort_order', 'status',
        ]));

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully.');
    }

    public function destroy(string $id)
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully.');
    }
}
