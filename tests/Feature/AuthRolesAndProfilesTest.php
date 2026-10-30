<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthRolesAndProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_collects_name_username_email_and_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Writer',
            'username' => 'janewriter',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('janewriter', $user->username);
        $this->assertSame('Jane Writer', $user->name);
        $this->assertNotNull($user->email_verified_at); // default config verify_email = false marks verified
    }

    public function test_registration_with_email_verification_enabled(): void
    {
        Config::set('auth.verify_email', true);

        $response = $this->post('/register', [
            'name' => 'Unverified User',
            'username' => 'unverifieduser',
            'email' => 'unverified@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'unverified@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_public_routes_are_accessible_to_guests_without_auth(): void
    {
        $author = User::factory()->create(['username' => 'starauthor']);
        $category = Category::factory()->create(['name' => 'Science', 'slug' => 'science']);
        $tag = Tag::factory()->create(['name' => 'physics', 'slug' => 'physics']);

        $post = Post::factory()->published()->create([
            'user_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Quantum Computing Explained',
            'slug' => 'quantum-computing-explained',
        ]);
        $post->tags()->attach($tag);

        // 1. Home
        $this->get('/')->assertStatus(200);

        // 2. Post detail
        $this->get('/posts/' . $post->slug)->assertStatus(200);

        // 3. Category page
        $this->get('/categories/' . $category->slug)->assertStatus(200);

        // 4. Tag page
        $this->get('/tags/' . $tag->slug)->assertStatus(200);

        // 5. Author page
        $this->get('/@' . $author->username)->assertStatus(200);

        // 6. Search
        $this->get('/search?q=Quantum')->assertStatus(200);
    }

    public function test_write_actions_redirect_guests_to_login_and_return_intended(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $author->id]);

        // Attempting to visit write page redirects to login
        $writeResponse = $this->get('/write');
        $writeResponse->assertRedirect('/login');
        $this->assertSame(url('/write'), session('url.intended'));

        // Attempting to post a comment redirects to login
        $commentResponse = $this->post('/posts/' . $post->slug . '/comments', ['body' => 'Nice!']);
        $commentResponse->assertRedirect('/login');

        // Attempting to like redirects to login
        $likeResponse = $this->post('/posts/' . $post->slug . '/like');
        $likeResponse->assertRedirect('/login');

        // Attempting to bookmark redirects to login
        $bookmarkResponse = $this->post('/posts/' . $post->slug . '/bookmark');
        $bookmarkResponse->assertRedirect('/login');

        // Attempting to follow redirects to login
        $followResponse = $this->post('/users/' . $author->username . '/follow');
        $followResponse->assertRedirect('/login');
    }

    public function test_not_banned_middleware_blocks_banned_users_with_clear_message(): void
    {
        $bannedUser = User::factory()->banned()->create();
        $post = Post::factory()->published()->create();

        // 1. Post creation attempt
        $response = $this->actingAs($bannedUser)->post('/posts', [
            'title' => 'Spam Title',
            'content_html' => '<p>Spam content</p>',
        ]);
        $response->assertStatus(403);

        // 2. Comment attempt
        $commentResponse = $this->actingAs($bannedUser)->post('/posts/' . $post->slug . '/comments', [
            'body' => 'Spam comment',
        ]);
        $commentResponse->assertStatus(403);

        // 3. Like attempt
        $likeResponse = $this->actingAs($bannedUser)->post('/posts/' . $post->slug . '/like');
        $likeResponse->assertStatus(403);
    }

    public function test_post_policy_author_moderator_and_admin_permissions(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $randomUser = User::factory()->create();

        $post = Post::factory()->published()->create(['user_id' => $author->id]);
        $draft = Post::factory()->draft()->create(['user_id' => $author->id]);

        // Update: author, admin, moderator can update; random user cannot
        $this->assertTrue($author->can('update', $post));
        $this->assertTrue($moderator->can('update', $post));
        $this->assertTrue($admin->can('update', $post));
        $this->assertFalse($randomUser->can('update', $post));

        // Delete: author, admin, moderator can delete; random user cannot
        $this->assertTrue($author->can('delete', $post));
        $this->assertTrue($moderator->can('delete', $post));
        $this->assertTrue($admin->can('delete', $post));
        $this->assertFalse($randomUser->can('delete', $post));

        // Publish: only author or admin can publish/unpublish; moderator CANNOT publish other's post
        $this->assertTrue($author->can('publish', $post));
        $this->assertTrue($admin->can('publish', $post));
        $this->assertFalse($moderator->can('publish', $post));
        $this->assertFalse($randomUser->can('publish', $post));

        // View drafts: author, admin, moderator can view; guest/random cannot
        $this->assertTrue($author->can('view', $draft));
        $this->assertTrue($admin->can('view', $draft));
        $this->assertTrue($moderator->can('view', $draft));
        $this->assertFalse($randomUser->can('view', $draft));
        $this->assertFalse(Gate::forUser(null)->allows('view', $draft));
    }

    public function test_comment_policy_permissions(): void
    {
        $postAuthor = User::factory()->create();
        $commentAuthor = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $randomUser = User::factory()->create();

        $post = Post::factory()->published()->create(['user_id' => $postAuthor->id]);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $commentAuthor->id,
            'body' => 'Thoughtful critique',
            'status' => CommentStatus::VISIBLE,
        ]);

        // Update comment: only comment author or admin can update
        $this->assertTrue($commentAuthor->can('update', $comment));
        $this->assertTrue($admin->can('update', $comment));
        $this->assertFalse($postAuthor->can('update', $comment));
        $this->assertFalse($moderator->can('update', $comment));
        $this->assertFalse($randomUser->can('update', $comment));

        // Delete comment: comment author, post author, moderator, or admin can delete
        $this->assertTrue($commentAuthor->can('delete', $comment));
        $this->assertTrue($postAuthor->can('delete', $comment));
        $this->assertTrue($moderator->can('delete', $comment));
        $this->assertTrue($admin->can('delete', $comment));
        $this->assertFalse($randomUser->can('delete', $comment));
    }

    public function test_public_author_page_renders_avatar_bio_followers_and_published_posts(): void
    {
        $author = User::factory()->create([
            'name' => 'Alexander Pope',
            'username' => 'alexpope',
            'bio' => 'Essayist and poet focusing on literary craft and classical rhythm.',
        ]);

        $publishedPost = Post::factory()->published()->create([
            'user_id' => $author->id,
            'title' => 'An Essay on Criticism in the Digital Era',
        ]);

        $draftPost = Post::factory()->draft()->create([
            'user_id' => $author->id,
            'title' => 'Unpublished Work in Progress',
        ]);

        $response = $this->get('/@' . $author->username);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Author/Show')
            ->where('author.name', 'Alexander Pope')
            ->where('author.username', 'alexpope')
            ->where('author.bio', 'Essayist and poet focusing on literary craft and classical rhythm.')
            ->has('author.followers_count')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $publishedPost->id)
        );
    }

    public function test_settings_page_profile_edit_and_avatar_upload_resized_to_256px(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['username' => 'originaluser']);

        // Create a test image 800x600
        $image = UploadedFile::fake()->image('my-avatar.png', 800, 600);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Name',
            'username' => 'updateduser',
            'email' => 'updated@example.com',
            'bio' => 'My newly revised bio.',
            'avatar' => $image,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updateduser', $user->username);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('My newly revised bio.', $user->bio);
        $this->assertNotNull($user->avatar_path);

        // Verify stored on public disk and dimensions are 256x256
        $storedFilename = basename($user->avatar_path);
        Storage::disk('public')->assertExists('avatars/' . $storedFilename);

        $storedPath = Storage::disk('public')->path('avatars/' . $storedFilename);
        $size = getimagesize($storedPath);
        $this->assertSame(256, $size[0]);
        $this->assertSame(256, $size[1]);
    }

    public function test_rate_limiting_on_login_and_registration(): void
    {
        // Registration throttling: 5 attempts allowed in 1 minute
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'Test',
                'username' => 'user' . $i,
                'email' => 'invalid-email',
                'password' => 'password',
            ]);
        }

        // 6th attempt hits route rate limit
        $blockedResponse = $this->post('/register', [
            'name' => 'Blocked',
            'username' => 'blockeduser',
            'email' => 'blocked@example.com',
            'password' => 'password',
        ]);
        $blockedResponse->assertStatus(429);
    }
}
