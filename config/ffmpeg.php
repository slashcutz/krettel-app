<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FFmpeg Binaries
    |--------------------------------------------------------------------------
    |
    | Absolute paths to the ffmpeg / ffprobe executables. Point these at your
    | installed binaries (e.g. Laragon's C:\laragon\bin\ffmpeg\bin\ffmpeg.exe
    | or a winget-installed build). The multi-audio HLS transcoder relies on
    | them; `ffmpeg` is used to produce the master playlist with audio groups
    | and `ffprobe` to inspect the source container for its audio streams.
    |
    */

    'ffmpeg' => env('FFMPEG_BINARY', PHP_OS_FAMILY === 'Windows' ? 'ffmpeg' : '/usr/bin/ffmpeg'),

    'ffprobe' => env('FFPROBE_BINARY', PHP_OS_FAMILY === 'Windows' ? 'ffprobe' : '/usr/bin/ffprobe'),

];
