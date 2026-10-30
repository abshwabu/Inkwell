<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Front Page Feed & Search
Route::get('/', [FeedController::class, 'index'])->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');

// "Not interested" dismissal & undismissal (works for guests with cookie, and logged-in users)
Route::post('/posts/{post}/dismiss', [FeedController::class, 'dismiss'])->name('posts.dismiss');
Route::post('/posts/{post}/undismiss', [FeedController::class, 'undoDismiss'])->name('posts.undismiss');

// Category routes (/category/{slug} and alias /categories/{slug})
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show']);

// Tag routes (/tag/{slug} and alias /tags/{slug})
Route::get('/tag/{tag:slug}', [TagController::class, 'show'])->name('tags.show');
Route::get('/tags/{tag:slug}', [TagController::class, 'show']);

// Archives (/archive and /archive/{year}/{month?})
Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
Route::get('/archive/{year}/{month?}', [ArchiveController::class, 'show'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}'])
    ->name('archive.show');

// Short post route /p/{slug} and legacy /posts/{post:slug} - 301 redirect to canonical
Route::get('/p/{post_slug}', [PostController::class, 'showShort'])
    ->where('post_slug', '[a-zA-Z0-9_\-]+')
    ->name('posts.show.short');

Route::get('/posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');

Route::get('/@{username}', [AuthorController::class, 'show'])
    ->where('username', '[a-zA-Z0-9_\-]+')
    ->name('author.show');

// Authenticated & Not Banned Routes (Writing, Commenting, Liking, Bookmarking, Following, Admin Feature)
Route::middleware(['auth', 'not.banned'])->group(function () {
    Route::get('/write', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('/posts/autosave', [PostController::class, 'autosave'])->name('posts.autosave');
    Route::post('/uploads/images', [ImageUploadController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('uploads.images');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
    Route::post('/posts/{post}/unpublish', [PostController::class, 'unpublish'])->name('posts.unpublish');
    Route::post('/posts/{post}/archive', [PostController::class, 'archive'])->name('posts.archive');
    Route::post('/posts/{post}/schedule', [PostController::class, 'schedule'])->name('posts.schedule');
    Route::post('/posts/{post}/toggle-featured', [FeedController::class, 'toggleFeatured'])->name('posts.toggle-featured');

    Route::post('/posts/{post:slug}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('/posts/{post:slug}/like', [LikeController::class, 'toggle'])->name('posts.like');
    Route::post('/posts/{post:slug}/bookmark', [BookmarkController::class, 'toggle'])->name('posts.bookmark');
    Route::post('/users/{user:username}/follow', [FollowController::class, 'toggle'])->name('users.follow');

    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('reports.store');
});

// Authenticated Settings, Dashboard, Bookmarks, Following, Notifications & Onboarding
Route::middleware('auth')->group(function () {
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::get('/following', [FollowController::class, 'index'])->name('following.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('/onboarding', [PreferenceController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [PreferenceController::class, 'storeOnboarding'])->name('onboarding.store');

    Route::get('/settings/preferences', [PreferenceController::class, 'edit'])->name('settings.preferences');
    Route::put('/settings/preferences', [PreferenceController::class, 'update'])->name('settings.preferences.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/bulk-action', [DashboardController::class, 'bulkAction'])->name('dashboard.bulk-action');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Canonical post route /{category_slug}/{post_slug} (Must be at the end to prevent collisions)
Route::get('/{category_slug}/{post_slug}', [PostController::class, 'showCanonical'])
    ->where(['category_slug' => '[a-zA-Z0-9_\-]+', 'post_slug' => '[a-zA-Z0-9_\-]+'])
    ->name('posts.show.canonical');
