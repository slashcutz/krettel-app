<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Video;
use Illuminate\Support\Facades\DB;

echo "VIDEOS: ".Video::count().PHP_EOL;
foreach (Video::latest()->limit(5)->get() as $v) {
    echo $v->id.' | '.$v->title.' | '.$v->visibility.' | '.$v->video_url.' | '.$v->created_at.PHP_EOL;
}
echo "JOBS: ".DB::table('jobs')->count().PHP_EOL;
foreach (DB::table('jobs')->get() as $j) {
    echo 'job id='.$j->id.' attempt='.$j->attempts.' queue='.$j->queue.PHP_EOL;
    echo 'payload: '.substr($j->payload, 0, 300).PHP_EOL;
}
echo "FAILED JOBS: ".DB::table('failed_jobs')->count().PHP_EOL;
foreach (DB::table('failed_jobs')->get() as $f) {
    echo 'failed id='.$f->id.' ex='.substr($f->exception,0,400).PHP_EOL;
}
echo "UPLOAD LOGS: ".DB::table('upload_logs')->count().PHP_EOL;
echo "PENDING-uploads dir: ".PHP_EOL;
foreach (Illuminate\Support\Facades\Storage::disk('local')->allFiles('pending-uploads') as $f) {
    echo '  '.$f.' ('.Illuminate\Support\Facades\Storage::disk('local')->size($f).' bytes)'.PHP_EOL;
}
echo "QUEUE CONNECTION: ".env('QUEUE_CONNECTION').PHP_EOL;
echo "Storage app/private list:".PHP_EOL;
foreach (Illuminate\Support\Facades\Storage::disk('local')->allFiles() as $f) {
    echo '  '.$f.' ('.Illuminate\Support\Facades\Storage::disk('local')->size($f).' bytes)'.PHP_EOL;
}