<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\FeedService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalizedFrontPageTest extends TestCase
{
    use RefreshDatabase;

    protected FeedService $feedService;
    protected User $user;
    protected User $authorA;
    protected User $authorB;
    protected Category $categoryTech;
    protected Category $categoryLifestyle;
    protected Tag $tagVue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedService = app(FeedService::class);

        $this->user = User::factory()->create([
            'role' => UserRole::USER,
        ]);

        $this->authorA = User::factory()->create(['name' => 'Alice Tech', 'username' => 'alicetech']);
        $this->authorB = User::factory()->create(['name' => 'Bob Life', 'username' => 'boblife']);

        $this->categoryTech = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        $this->categoryLifestyle = Category::factory()->create(['name' => 'Lifestyle', 'slug' => 'lifestyle']);
        $this->tagVue = Tag::factory()->create(['name' => 'Vue', 'slug' => 'vue']);
    }

    /**
     * Acceptance: Changing preferences visibly changes the feed order.
     */
    public function test_changing_preferences_visibly_changes_feed_order(): void
    {
        $now = Carbon::now();

        // Two posts with identical publication date and zero engagement
        $postTech = Post::factory()->create([
            'user_id' => $this->authorA->id,
            'category_id' => $this->categoryTech->id,
            'title' => 'Deep Dive into SQLite Performance',
            'status' => PostStatus::PUBLISHED,
            'published_at' => $now->copy()->subHours(2),
            'views_count' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
        ]);

        $postLife = Post::factory()->create([
            'user_id' => $this->authorB->id,
            'category_id' => $this->categoryLifestyle->id,
            'title' => 'Mindful Living in a Digital Age',
            'status' => PostStatus::PUBLISHED,
            'published_at' => $now->copy()->subHours(2),
            'views_count' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
        ]);

        // 1. User prefers Technology -> Tech post must be first
        $this->user->preferredCategories()->sync([$this->categoryTech->id]);
        $this->feedService->invalidateUserFeedCache($this->user);

        $feedTech = $this->feedService->getFeed($this->user, 'for-you', 1, 10);
        $this->assertSame($postTech->id, $feedTech->first()->id, 'Tech post should be first when Technology is preferred');

        // 2. User changes preferences to Lifestyle -> Lifestyle post must be first
        $this->user->preferredCategories()->sync([$this->categoryLifestyle->id]);
        $this->feedService->invalidateUserFeedCache($this->user);

        $feedLife = $this->feedService->getFeed($this->user, 'for-you', 1, 10);
        $this->assertSame($postLife->id, $feedLife->first()->id, 'Lifestyle post should be first when Lifestyle is preferred');
    }

    /**
     * Test Front page tabs for logged-in users.
     */
    public function test_front_page_tabs_for_logged_in_user(): void
    {
        // 1. "For you" (default)
        $response = $this->actingAs($this->user)->get('/');
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('tab', 'for-you')
                ->has('posts.data')
                ->has('sidebarData')
                ->has('categories')
            );

        // 2. "Following" tab only includes followed authors
        $this->user->following()->sync([$this->authorA->id]);

        Post::factory()->create([
            'user_id' => $this->authorA->id,
            'title' => 'Story by Alice',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        Post::factory()->create([
            'user_id' => $this->authorB->id,
            'title' => 'Story by Bob',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        $followingResponse = $this->actingAs($this->user)->get('/?tab=following');
        $followingResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'following')
                ->has('posts.data', 1)
                ->where('posts.data.0.user.id', $this->authorA->id)
            );

        // 3. "Latest" tab
        $latestResponse = $this->actingAs($this->user)->get('/?tab=latest');
        $latestResponse->assertOk()->assertInertia(fn ($page) => $page->where('tab', 'latest'));

        // 4. "Trending" tab
        $trendingResponse = $this->actingAs($this->user)->get('/?tab=trending');
        $trendingResponse->assertOk()->assertInertia(fn ($page) => $page->where('tab', 'trending'));
    }

    /**
     * Test Front page for guests and cookie preference merging at signup.
     */
    public function test_guest_front_page_and_signup_preference_merging(): void
    {
        // 1. Guest visits home -> defaults to 'trending'
        $guestResponse = $this->get('/');
        $guestResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('tab', 'trending')
            );

        // 2. Guest with lightweight cookie preferences
        $cookiePayload = json_encode([
            'categories' => [$this->categoryTech->id, $this->categoryLifestyle->id],
            'tags' => [$this->tagVue->id],
        ]);

        // 3. Registering merges cookie preferences into account
        $registerResponse = $this->withCookie('inkwell_guest_prefs', $cookiePayload)
            ->post('/register', [
                'name' => 'New Explorer',
                'username' => 'newexplorer',
                'email' => 'explorer@inkwell.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $newUser = User::where('email', 'explorer@inkwell.test')->first();
        $this->assertNotNull($newUser);

        // Verify preferences were merged
        $this->assertTrue($newUser->preferredCategories()->where('categories.id', $this->categoryTech->id)->exists());
        $this->assertTrue($newUser->preferredCategories()->where('categories.id', $this->categoryLifestyle->id)->exists());
        $this->assertTrue($newUser->preferredTags()->where('tags.id', $this->tagVue->id)->exists());
    }

    /**
     * Test onboarding flow after signup.
     */
    public function test_onboarding_requires_at_least_3_categories_with_skip_option(): void
    {
        // 1. Unauthenticated redirect
        $guestOnboarding = $this->get('/onboarding');
        $guestOnboarding->assertRedirect('/login');

        // 2. Authenticated user can view onboarding
        $cat3 = Category::factory()->create(['name' => 'Business', 'slug' => 'business']);

        $viewResponse = $this->actingAs($this->user)->get('/onboarding');
        $viewResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Onboarding/Index')
                ->has('categories')
            );

        // 3. Submitting fewer than 3 categories fails validation
        $invalidSubmit = $this->actingAs($this->user)->post('/onboarding', [
            'categories' => [$this->categoryTech->id, $this->categoryLifestyle->id],
        ]);
        $invalidSubmit->assertSessionHasErrors('categories');

        // 4. Submitting >= 3 categories succeeds and updates user preferences
        $validSubmit = $this->actingAs($this->user)->post('/onboarding', [
            'categories' => [$this->categoryTech->id, $this->categoryLifestyle->id, $cat3->id],
            'tags' => [$this->tagVue->id],
        ]);
        $validSubmit->assertRedirect(route('home'));

        $this->user->refresh();
        $this->assertCount(3, $this->user->preferredCategories);
        $this->assertCount(1, $this->user->preferredTags);

        // 5. Skip option works
        $skipSubmit = $this->actingAs($this->user)->post('/onboarding', [
            'skip' => true,
        ]);
        $skipSubmit->assertRedirect(route('home'));
    }

    /**
     * Test "Not interested" post dismissal and category down-weighting.
     */
    public function test_not_interested_dismissal_and_category_downweighting(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->authorA->id,
            'category_id' => $this->categoryTech->id,
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        // 1. Dismiss post
        $dismissResponse = $this->actingAs($this->user)->post(route('posts.dismiss', $post->id));
        $dismissResponse->assertRedirect();

        $this->assertDatabaseHas('post_dismissals', [
            'user_id' => $this->user->id,
            'post_id' => $post->id,
            'category_id' => $this->categoryTech->id,
        ]);

        // Dismissed post must NOT be in the user's feed
        $feed = $this->feedService->getFeed($this->user, 'latest');
        $this->assertFalse($feed->contains('id', $post->id), 'Dismissed post should not appear in feed');

        // 2. Undo dismissal
        $undoResponse = $this->actingAs($this->user)->post(route('posts.undismiss', $post->id));
        $undoResponse->assertRedirect();

        $this->assertDatabaseMissing('post_dismissals', [
            'user_id' => $this->user->id,
            'post_id' => $post->id,
        ]);
    }

    /**
     * Test cold-start fallback when user has no signals.
     */
    public function test_cold_start_fallback_uses_trending_posts(): void
    {
        $newUser = User::factory()->create();

        // Create 2 posts: one trending with many likes, one with zero
        $trendingPost = Post::factory()->create([
            'user_id' => $this->authorA->id,
            'title' => 'Top Trending Story',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'likes_count' => 150,
            'comments_count' => 40,
            'views_count' => 2000,
        ]);

        $quietPost = Post::factory()->create([
            'user_id' => $this->authorB->id,
            'title' => 'Quiet Story',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 5,
        ]);

        $feed = $this->feedService->getFeed($newUser, 'for-you', 1, 10);
        $this->assertSame($trendingPost->id, $feed->first()->id, 'Cold-start feed should prioritize trending posts');
    }

    /**
     * Test Editor's Pick (is_featured) toggle by admin/moderator.
     */
    public function test_editors_pick_toggle_permissions(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->authorA->id,
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'is_featured' => false,
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $regularUser = User::factory()->create(['role' => UserRole::USER]);

        // Regular user cannot toggle
        $forbidden = $this->actingAs($regularUser)->post(route('posts.toggle-featured', $post->id));
        $forbidden->assertStatus(403);

        // Admin can toggle
        $adminToggle = $this->actingAs($admin)->post(route('posts.toggle-featured', $post->id));
        $adminToggle->assertRedirect();

        $post->refresh();
        $this->assertTrue($post->is_featured);

        // Hero post returns featured post
        $hero = $this->feedService->getHeroPost();
        $this->assertSame($post->id, $hero->id);
    }

    /**
     * Test Reading Preferences settings page.
     */
    public function test_preferences_settings_page(): void
    {
        $viewResponse = $this->actingAs($this->user)->get(route('settings.preferences'));
        $viewResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Preferences')
                ->has('categories')
                ->has('tags')
            );

        $updateResponse = $this->actingAs($this->user)->put(route('settings.preferences.update'), [
            'categories' => [$this->categoryTech->id],
            'tags' => [$this->tagVue->id],
        ]);
        $updateResponse->assertRedirect();

        $this->user->refresh();
        $this->assertTrue($this->user->preferredCategories()->where('categories.id', $this->categoryTech->id)->exists());
    }

    /**
     * Test filtering front page feed by category topic.
     */
    public function test_category_filter_in_front_page_feed(): void
    {
        $techPost = Post::factory()->create([
            'user_id' => $this->authorA->id,
            'category_id' => $this->categoryTech->id,
            'title' => 'Pure Tech Story',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        $lifePost = Post::factory()->create([
            'user_id' => $this->authorB->id,
            'category_id' => $this->categoryLifestyle->id,
            'title' => 'Pure Lifestyle Story',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subHours(2),
        ]);

        // 1. Visit with category filter on default feed
        $response = $this->get('/?category=technology');
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('selectedCategory', 'technology')
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $techPost->id)
            );

        // 2. Filter on latest tab
        $latestRes = $this->get('/?tab=latest&category=lifestyle');
        $latestRes->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('selectedCategory', 'lifestyle')
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $lifePost->id)
            );
    }
}
