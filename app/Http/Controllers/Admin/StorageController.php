<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class StorageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.storage.index', [
            'storage' => Setting::all()->pluck('value', 'key')->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'default_driver' => 'required|in:local,public,s3,terabox',
            'max_upload_size_mb' => 'nullable|integer|min:1',
        ]);

        Setting::set('default_driver', $request->input('default_driver'));
        Setting::set('max_upload_size_mb', (string) $request->input('max_upload_size_mb', '2048'));

        return redirect()->route('admin.storage.index')
            ->with('success', 'Storage configuration saved.');
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
