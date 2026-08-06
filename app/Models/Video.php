<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = [];

    protected $casts = [
        'previews' => 'array',
    ];

    public function resolveImageUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'terabox://')) {
            return route('terabox.image', ['model' => 'video', 'id' => $this->id]);
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return asset('storage/' . $value);
    }

    public function category()
    {
        return $this->belongsTo(VideoCategory::class, 'category_id');
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'genre_video');
    }
}
