<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin overview dashboard with 30-day stats and charts.
     */
    public function index(): Response
    {
        $startDate = now()->subDays(29)->startOfDay();

        $totalUsers = User::count();
        $totalPosts = Post::count();
        $publishedPosts = Post::where('status', PostStatus::PUBLISHED->value)->count();
        $totalViews = (int) Post::sum('views_count');
        $signups30d = User::where('created_at', '>=', $startDate)->count();
        $pendingReports = Report::where('status', 'pending')->count();

        // 30-day daily breakdown for charts
        $dailySignups = User::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyPosts = Post::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyViews = PostView::where('viewed_at', '>=', $startDate)
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $chartLabels = [];
        $chartSignups = [];
        $chartPosts = [];
        $chartViews = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $dateStr = $day->format('Y-m-d');
            $label = $day->format('M j');

            $chartLabels[] = $label;
            $chartSignups[] = $dailySignups[$dateStr] ?? 0;
            $chartPosts[] = $dailyPosts[$dateStr] ?? 0;
            $chartViews[] = $dailyViews[$dateStr] ?? 0;
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'total_posts' => $totalPosts,
                'published_posts' => $publishedPosts,
                'total_views' => $totalViews,
                'signups_30d' => $signups30d,
                'pending_reports' => $pendingReports,
            ],
            'chartData' => [
                'labels' => $chartLabels,
                'signups' => $chartSignups,
                'posts' => $chartPosts,
                'views' => $chartViews,
            ],
        ]);
    }
}
