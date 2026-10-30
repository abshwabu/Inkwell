<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes (No auth required)
Route::get('/', function (Request $request) {
    $search = $request->input('search');
    $categorySlug = $request->input('category');

    $posts = Post::published()
        ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
        ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($cq) => $cq->where('slug', $categorySlug)))
        ->popular()
        ->withRelations()
        ->latest('published_at')
        ->paginate(9)
        ->withQueryString();

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'posts' => $posts,
        'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');
Route::get('/posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');
Route::get('/@{username}', [AuthorController::class, 'show'])
    ->where('username', '[a-zA-Z0-9_\-]+')
    ->name('author.show');

// Authenticated & Not Banned Routes (Writing, Commenting, Liking, Bookmarking, Following)
Route::middleware(['auth', 'not.banned'])->group(function () {
    Route::get('/write', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post:slug}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post:slug}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post:slug}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post:slug}/publish', [PostController::class, 'publish'])->name('posts.publish');

    Route::post('/posts/{post:slug}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('/posts/{post:slug}/like', [LikeController::class, 'toggle'])->name('posts.like');
    Route::post('/posts/{post:slug}/bookmark', [BookmarkController::class, 'toggle'])->name('posts.bookmark');
    Route::post('/users/{user:username}/follow', [FollowController::class, 'toggle'])->name('users.follow');
});

// Authenticated Settings & Dashboard
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $posts = $user->posts()->withRelations()->latest()->paginate(10);
        return Inertia::render('Dashboard', [
            'posts' => $posts,
        ]);
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
