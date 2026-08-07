<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSetupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

Route::post('/upload/chunk', [VideoUploadController::class, 'chunk'])->name('upload.chunk');
Route::get('/upload/resume-check', [VideoUploadController::class, 'resumeCheck'])->name('upload.resume-check');

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/watch/{slug}', [VideoController::class, 'show'])->name('video.show');
Route::get('/stream/{video}', [VideoController::class, 'stream'])->name('video.stream');
Route::get('/stream/direct/{video}', [VideoController::class, 'streamDirect'])->name('video.stream.direct');

Route::get('/stream/pixeldrain/{video}', [VideoController::class, 'streamPixeldrain'])->name('video.stream.pixeldrain');
Route::get('/stream/pixeldrain/{video}/audio/{audioId}', [VideoController::class, 'streamPixeldrainAudio'])->name('video.stream.pixeldrain.audio');
Route::get('/stream/pixeldrain/{video}/subtitle/{subtitle}', [VideoController::class, 'streamPixeldrainSubtitle'])->name('video.stream.pixeldrain.subtitle');
Route::get('/stream/pixeldrain/{video}/quality/{variant}', [VideoController::class, 'streamPixeldrainVariant'])->name('video.stream.pixeldrain.quality');
Route::get('/stream/pixeldrain/{video}/quality/{variant}/audio/{audioId}', [VideoController::class, 'streamPixeldrainVariantAudio'])->name('video.stream.pixeldrain.quality.audio');
Route::get('/stream/720/{video}', [VideoController::class, 'stream720'])->name('video.stream.transcode');
Route::get('/terabox-test', [VideoController::class, 'teraboxTest']);
Route::get('/pixeldrain-test/{video}', [VideoController::class, 'pixeldrainTest']);
Route::get('/stream/segment/{video}/{u}', [VideoController::class, 'segment'])->name('video.segment');
Route::get('/stream/hls/{video}/{path}', [VideoController::class, 'hls'])->where('path', '.*')->name('video.hls');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/collection/{slug}', [\App\Http\Controllers\CollectionController::class, 'show'])->name('collection.show');
Route::get('/collections', [\App\Http\Controllers\CollectionController::class, 'index'])->name('collections.index');
Route::get('/my-list', [\App\Http\Controllers\ListController::class, 'index'])->name('my-list');
Route::post('/list/toggle', [\App\Http\Controllers\ListController::class, 'toggle'])->name('list.toggle');
Route::post('/analytics/watch-time', [\App\Http\Controllers\AnalyticsController::class, 'logWatchTime'])->name('analytics.watch_time');
Route::get('/terabox/image/{model}/{id}', [\App\Http\Controllers\TeraBoxImageController::class, 'show'])
    ->where('model', 'video|collection|settings')
    ->where('id', '[a-zA-Z0-9_]+')
    ->name('terabox.image');

Route::get('/dashboard', [\App\Http\Controllers\UserDashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
    Route::get('/upload', [VideoUploadController::class, 'index'])->name('upload.index');
    Route::get('/upload/popup', function () {
        return view('frontend.upload.popup');
    })->name('upload.popup');
    Route::post('/upload', [VideoUploadController::class, 'store'])->name('upload.store');
    Route::get('/upload/status/{video}', [VideoUploadController::class, 'status'])->name('upload.status');
});

// Polled by the upload popup WHILE store() is still running the sync Pixeldrain
// upload. Deliberately session-less (no auth) — store() holds the session lock
// for the whole request, so a session-based poll would just block until the
// upload finishes. The token itself is the capability: it is a random value
// generated in the popup and is meaningless to anyone else.
Route::get('/upload/progress/{token}', [VideoUploadController::class, 'progress'])
    ->name('upload.progress')
    ->withoutMiddleware('web');

Route::middleware('auth')->group(function () {
    Route::get('/profile/setup', [ProfileSetupController::class, 'create'])->name('profile.setup');
    Route::post('/profile/setup', [ProfileSetupController::class, 'store'])->name('profile.setup.store');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::middleware(['auth', 'role:Super Admin|Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'index'])->name('dashboard');
    Route::get('/pending-notifications', [\App\Http\Controllers\Admin\AdminController::class, 'pendingNotifications'])->name('pending-notifications');
    Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
    Route::resource('videos', \App\Http\Controllers\Admin\VideoController::class);
    Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class);
    
    Route::resource('banners', \App\Http\Controllers\Admin\BannerController::class);
    Route::resource('playlists', \App\Http\Controllers\Admin\PlaylistController::class);
    Route::get('collections', [\App\Http\Controllers\Admin\CollectionController::class, 'index'])->name('collections.index');
    Route::get('collections/create', [\App\Http\Controllers\Admin\CollectionController::class, 'create'])->name('collections.create');
    Route::post('collections', [\App\Http\Controllers\Admin\CollectionController::class, 'store'])->name('collections.store');
    Route::get('collections/{collection:slug}', [\App\Http\Controllers\Admin\CollectionController::class, 'show'])->name('collections.show');
    Route::get('collections/{collection:slug}/edit', [\App\Http\Controllers\Admin\CollectionController::class, 'edit'])->name('collections.edit');
    Route::match(['put', 'patch'], 'collections/{collection:slug}', [\App\Http\Controllers\Admin\CollectionController::class, 'update'])->name('collections.update');
    Route::delete('collections/{collection:slug}', [\App\Http\Controllers\Admin\CollectionController::class, 'destroy'])->name('collections.destroy');
    Route::resource('notifications', \App\Http\Controllers\Admin\NotificationController::class);
    Route::post('notifications/clear-all', [\App\Http\Controllers\Admin\NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::resource('storage', \App\Http\Controllers\Admin\StorageController::class);
    Route::resource('settings', \App\Http\Controllers\Admin\SettingController::class);
    Route::resource('reports', \App\Http\Controllers\Admin\ReportController::class);
});

require __DIR__.'/auth.php';
