<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PostSeeder;
use Database\Seeders\TagSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSchemaAndModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_attributes_casts_and_slug_generation(): void
    {
        $user = User::factory()->create([
            'name' => 'John Writing Expert',
            'username' => null, // trigger auto slug generation
            'role' => UserRole::ADMIN,
            'is_banned' => false,
        ]);

        $this->assertSame('john-writing-expert', $user->username);
        $this->assertSame(UserRole::ADMIN, $user->role);
        $this->assertFalse($user->is_banned);

        // Test collision
        $user2 = User::factory()->create([
            'name' => 'John Writing Expert',
            'username' => null,
        ]);
        $this->assertSame('john-writing-expert-1', $user2->username);
    }

    public function test_post_creation_slug_collision_and_reading_time(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Architecture']);

        $post1 = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Scaling SQLite Databases',
            'content_html' => '<p>' . str_repeat('word ', 450) . '</p>',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $post2 = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Scaling SQLite Databases',
            'content_html' => '<p>Short read</p>',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        $this->assertSame('scaling-sqlite-databases', $post1->slug);
        $this->assertSame('scaling-sqlite-databases-1', $post2->slug);
        $this->assertSame(2, $post1->reading_time_minutes); // 450 words / 225 = ceil(2.0) = 2
        $this->assertSame(1, $post2->reading_time_minutes); // min 1
    }

    public function test_post_scopes_published_and_popular_without_n_plus_one(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $published = Post::factory()->published()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'views_count' => 1000,
            'likes_count' => 50,
            'comments_count' => 10,
        ]);
        $published->tags()->attach($tag);

        $draft = Post::factory()->draft()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $archived = Post::factory()->archived()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $publishedPosts = Post::published()->get();
        $this->assertTrue($publishedPosts->contains($published));
        $this->assertFalse($publishedPosts->contains($draft));
        $this->assertFalse($publishedPosts->contains($archived));

        // Test N+1 prevention withRelations scope
        DB::flushQueryLog();
        DB::enableQueryLog();

        $feed = Post::published()->popular()->withRelations()->get();
        foreach ($feed as $item) {
            $authorName = $item->user->name;
            $catName = $item->category?->name;
            $tagsList = $item->tags->pluck('name')->join(',');
        }

        $queries = DB::getQueryLog();
        // Exactly 3 queries: 1 for posts, 1 for user, 1 for category, 1 for tags pivot = max 4 queries
        $this->assertLessThanOrEqual(4, count($queries));
    }

    public function test_comments_nesting_and_soft_deletes(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user->id]);

        $parentComment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'body' => 'Top level comment',
            'status' => CommentStatus::VISIBLE,
        ]);

        $reply = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $parentComment->id,
            'body' => 'Nested reply comment',
            'status' => CommentStatus::VISIBLE,
        ]);

        $this->assertSame($parentComment->id, $reply->parent->id);
        $this->assertTrue($parentComment->replies->contains($reply));

        // Test SoftDeletes
        $reply->delete();
        $this->assertSoftDeleted('comments', ['id' => $reply->id]);
        $this->assertNull(Comment::find($reply->id));
        $this->assertNotNull(Comment::withTrashed()->find($reply->id));
    }

    public function test_social_relationships_likes_bookmarks_follows_and_views(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user1->id]);

        // Follows
        Follow::create(['follower_id' => $user1->id, 'followed_id' => $user2->id]);
        $this->assertTrue($user1->following->contains($user2));
        $this->assertTrue($user2->followers->contains($user1));

        // Likes
        Like::create(['user_id' => $user2->id, 'post_id' => $post->id]);
        $this->assertTrue($user2->likedPosts->contains($post));
        $this->assertTrue($post->likers->contains($user2));

        // Bookmarks
        Bookmark::create(['user_id' => $user2->id, 'post_id' => $post->id]);
        $this->assertTrue($user2->bookmarkedPosts->contains($post));

        // Post Views
        $view = PostView::create([
            'post_id' => $post->id,
            'user_id' => $user2->id,
            'session_hash' => 'hash_test_123',
            'viewed_at' => now(),
        ]);
        $this->assertSame($post->id, $view->post->id);
        $this->assertSame($user2->id, $view->user->id);
    }

    public function test_database_seeders_populate_required_counts(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(TagSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(PostSeeder::class);

        $this->assertSame(8, Category::count());
        $this->assertSame(30, Tag::count());
        $this->assertSame(10, User::count());
        $this->assertSame(60, Post::count());

        $this->assertGreaterThan(0, Comment::count());
        $this->assertGreaterThan(0, Like::count());
        $this->assertGreaterThan(0, Bookmark::count());
        $this->assertGreaterThan(0, Follow::count());
        $this->assertGreaterThan(0, PostView::count());
    }
}
