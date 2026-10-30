<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    /**
     * Display the archive index grouped by year and month.
     */
    public function index(): Response
    {
        $archiveGroups = Post::published()
            ->selectRaw("strftime('%Y', published_at) as year, strftime('%m', published_at) as month, count(*) as count")
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(function ($row) {
                $monthInt = (int) $row->month;
                $yearInt = (int) $row->year;
                $date = Carbon::createFromDate($yearInt, $monthInt, 1);

                return [
                    'year' => $yearInt,
                    'month' => $monthInt,
                    'month_formatted' => str_pad((string) $monthInt, 2, '0', STR_PAD_LEFT),
                    'month_name' => $date ? $date->format('F') : (string) $monthInt,
                    'count' => (int) $row->count,
                ];
            });

        // Group by year for hierarchical presentation
        $byYear = $archiveGroups->groupBy('year');

        // Recent stories in the archives
        $recentStories = Post::published()
            ->withRelations()
            ->latest('published_at')
            ->take(6)
            ->get(['id', 'title', 'slug', 'excerpt', 'cover_image_path', 'category_id', 'user_id', 'reading_time_minutes', 'published_at']);

        return Inertia::render('Archive/Index', [
            'archiveYears' => $byYear,
            'recentStories' => $recentStories,
            'totalPublished' => Post::published()->count(),
        ]);
    }

    /**
     * Display posts published in a specific year and optional month.
     */
    public function show(Request $request, int $year, ?string $month = null): Response
    {
        if ($year < 1970 || $year > 2100) {
            abort(404, 'Invalid archive year.');
        }

        $monthInt = $month !== null ? (int) $month : null;
        if ($monthInt !== null && ($monthInt < 1 || $monthInt > 12)) {
            abort(404, 'Invalid archive month.');
        }

        $monthName = null;
        if ($monthInt !== null) {
            $date = Carbon::createFromDate($year, $monthInt, 1);
            $monthName = $date ? $date->format('F') : (string) $monthInt;
        }

        $postsQuery = Post::published()->withRelations();
        $postsQuery->whereYear('published_at', $year);

        if ($monthInt !== null) {
            $postsQuery->whereMonth('published_at', $monthInt);
        }

        $posts = $postsQuery->latest('published_at')->paginate(12)->withQueryString();

        return Inertia::render('Archive/Show', [
            'year' => $year,
            'month' => $monthInt,
            'month_name' => $monthName,
            'posts' => $posts,
        ]);
    }
}
