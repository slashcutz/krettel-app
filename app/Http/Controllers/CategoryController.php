<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VideoCategory;
use App\Models\Video;

class CategoryController extends Controller
{
    public function show(Request $request, $slug)
    {
        $category = VideoCategory::where('slug', $slug)->firstOrFail();

        $query = Video::where('category_id', $category->id)
                       ->where('visibility', 'public');

        switch ($request->get('sort')) {
            case 'popular':
                $query->orderBy('views', 'desc');
                break;
            case 'rating':
                $query->orderBy('views', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $videos = $query->paginate(20)->withQueryString();

        return view('frontend.category.show', compact('category', 'videos'));
    }
}
