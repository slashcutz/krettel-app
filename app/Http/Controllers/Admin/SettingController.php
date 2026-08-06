<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.settings.index', [
            'settings' => Setting::all()->pluck('value', 'key')->toArray(),
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
            'platform_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'seo_description' => 'nullable|string',
            'primary_color' => 'nullable|string|max:20',
            'navbar_logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'terabox_email' => 'nullable|email|max:255',
            'terabox_password' => 'nullable|string|max:255',
            'terabox_ndus' => 'nullable|string|max:500',
            'terabox_remote_dir' => 'nullable|string|max:255',
            'terabox_web_host' => 'nullable|url|max:255',
        ]);

        foreach (['platform_name', 'support_email', 'seo_description', 'primary_color'] as $key) {
            if ($request->filled($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        if ($request->hasFile('navbar_logo')) {
            $logo = $request->file('navbar_logo');
            $filename = 'logo.' . $logo->getClientOriginalExtension();
            $logo->storeAs('settings', $filename, 'public');
            Setting::set('navbar_logo', '/storage/settings/' . $filename);
        }

        foreach (['terabox_email', 'terabox_password', 'terabox_ndus', 'terabox_remote_dir', 'terabox_web_host'] as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings saved successfully.');
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
