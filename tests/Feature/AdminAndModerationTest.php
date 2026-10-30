<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Tag;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAndModerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $moderator;
    protected User $regularUser;
    protected Category $category;
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'username' => 'alice_admin',
            'role' => UserRole::ADMIN,
        ]);

        $this->moderator = User::factory()->create([
            'name' => 'Molly Moderator',
            'username' => 'molly_mod',
            'role' => UserRole::MODERATOR,
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'Bob Regular',
            'username' => 'bob_regular',
            'role' => UserRole::USER,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $this->post = Post::factory()->create([
            'user_id' => $this->regularUser->id,
            'category_id' => $this->category->id,
            'title' => 'Sample Article for Moderation',
            'slug' => 'sample-article-for-moderation',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function test_guests_and_regular_users_cannot_access_admin_area(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->actingAs($this->regularUser)
            ->get('/admin')
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->get('/admin/posts')
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->get('/admin/reports')
            ->assertForbidden();
    }

    public function test_moderators_can_access_moderation_queue_and_posts(): void
    {
        $this->actingAs($this->moderator)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));

        $this->actingAs($this->moderator)
            ->get('/admin/posts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Posts/Index'));

        $this->actingAs($this->moderator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Index'));

        $this->actingAs($this->moderator)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index'));
    }

    public function test_moderators_cannot_access_admin_only_pages_and_actions(): void
    {
        // 1. Settings page is admin-only
        $this->actingAs($this->moderator)
            ->get('/admin/settings')
            ->assertForbidden();

        $this->actingAs($this->moderator)
            ->post('/admin/settings', ['site_name' => 'Hacked Name'])
            ->assertForbidden();

        // 2. Audit logs page is admin-only
        $this->actingAs($this->moderator)
            ->get('/admin/audit-logs')
            ->assertForbidden();

        // 3. Changing user roles is admin-only
        $this->actingAs($this->moderator)
            ->post("/admin/users/{$this->regularUser->id}/role", ['role' => 'admin'])
            ->assertForbidden();

        // 4. Category deletion is admin-only
        $emptyCategory = Category::factory()->create(['name' => 'Empty']);
        $this->actingAs($this->moderator)
            ->delete("/admin/categories/{$emptyCategory->id}")
            ->assertForbidden();

        // 5. Tag merging is admin-only
        $tagA = Tag::factory()->create(['name' => 'TagA']);
        $tagB = Tag::factory()->create(['name' => 'TagB']);
        $this->actingAs($this->moderator)
            ->post("/admin/tags/{$tagA->id}/merge", ['target_tag_id' => $tagB->id])
            ->assertForbidden();
    }

    public function test_every_moderation_action_produces_an_audit_entry(): void
    {
        // 1. Feature / unfeature post
        $this->actingAs($this->moderator)
            ->post("/admin/posts/{$this->post->id}/feature");
        $this->assertTrue($this->post->fresh()->is_featured);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'post.feature',
            'auditable_id' => $this->post->id,
        ]);

        // 2. Unpublish post
        $this->actingAs($this->moderator)
            ->post("/admin/posts/{$this->post->id}/unpublish");
        $this->assertEquals(PostStatus::DRAFT, $this->post->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'post.unpublish',
        ]);

        // 3. Soft delete & restore post
        $this->actingAs($this->moderator)
            ->delete("/admin/posts/{$this->post->id}");
        $this->assertSoftDeleted('posts', ['id' => $this->post->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'post.delete',
        ]);

        $this->actingAs($this->moderator)
            ->post("/admin/posts/{$this->post->id}/restore");
        $this->assertNotSoftDeleted('posts', ['id' => $this->post->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'post.restore',
        ]);

        // 4. Report resolution: dismiss, hide, warn, ban
        $comment = Comment::create([
            'post_id' => $this->post->id,
            'user_id' => $this->regularUser->id,
            'body' => 'Violating comment',
            'status' => CommentStatus::VISIBLE,
        ]);

        $report = Report::create([
            'user_id' => $this->moderator->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'reason' => 'harassment',
            'status' => 'pending',
        ]);

        $this->actingAs($this->moderator)
            ->post("/admin/reports/{$report->id}/hide");
        $this->assertEquals(CommentStatus::HIDDEN, $comment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'content.hide',
        ]);

        $this->actingAs($this->moderator)
            ->post("/admin/reports/{$report->id}/warn", ['warning_reason' => 'First warning.']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'user.warn',
        ]);

        $this->actingAs($this->moderator)
            ->post("/admin/reports/{$report->id}/ban", ['ban_reason' => 'Repeated violations.']);
        $this->assertTrue($this->regularUser->fresh()->is_banned);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'user.ban',
        ]);

        // 5. Unban user
        $this->actingAs($this->moderator)
            ->post("/admin/users/{$this->regularUser->id}/unban");
        $this->assertFalse($this->regularUser->fresh()->is_banned);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->moderator->id,
            'action' => 'user.unban',
        ]);

        // 6. Admin user role change
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regularUser->id}/role", ['role' => 'moderator']);
        $this->assertEquals(UserRole::MODERATOR, $this->regularUser->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'user.role_change',
        ]);

        // 7. Site settings update
        $this->actingAs($this->admin)
            ->post('/admin/settings', [
                'site_name' => 'Inkwell Pro',
                'tagline' => 'Crafted Thoughts',
                'signups_enabled' => true,
                'require_email_verification' => false,
                'comment_moderation_mode' => 'open',
                'daily_post_limit_new_users' => 5,
                'link_limit_new_users_comments' => 2,
            ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'settings.update',
        ]);
        $this->assertEquals('Inkwell Pro', app(SettingService::class)->get('site_name'));
    }

    public function test_category_deletion_prevented_without_reassignment(): void
    {
        // Category has $this->post
        $this->assertEquals(1, $this->category->posts()->count());

        // Deleting without reassignment fails validation
        $this->actingAs($this->admin)
            ->delete("/admin/categories/{$this->category->id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $this->category->id]);

        // Create replacement category and reassign
        $replacement = Category::factory()->create(['name' => 'Science']);

        $this->actingAs($this->admin)
            ->delete("/admin/categories/{$this->category->id}", [
                'reassign_to_id' => $replacement->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $this->category->id]);
        $this->assertEquals($replacement->id, $this->post->fresh()->category_id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'category.reassign',
        ]);
    }

    public function test_tag_merge_reassigns_posts_and_deletes_source(): void
    {
        $sourceTag = Tag::factory()->create(['name' => 'VueJS']);
        $targetTag = Tag::factory()->create(['name' => 'Vue']);

        $this->post->tags()->sync([$sourceTag->id]);
        $this->assertTrue($this->post->tags->contains($sourceTag));

        $this->actingAs($this->admin)
            ->post("/admin/tags/{$sourceTag->id}/merge", [
                'target_tag_id' => $targetTag->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('tags', ['id' => $sourceTag->id]);
        $this->assertTrue($this->post->fresh()->tags->contains($targetTag));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'tag.merge',
        ]);
    }

    public function test_spam_protection_honeypot_field(): void
    {
        $this->actingAs($this->regularUser)
            ->post('/posts', [
                'title' => 'Valid Title for Post',
                'content_html' => '<p>' . str_repeat('word ', 120) . '</p>',
                'hp_field' => 'bot-value-filled',
            ])
            ->assertSessionHasErrors('hp_field');
    }

    public function test_spam_protection_blocked_words(): void
    {
        app(SettingService::class)->set('blocked_words', 'viagra,casino,free-crypto');

        $this->actingAs($this->regularUser)
            ->post('/posts', [
                'title' => 'Win big in online casino today',
                'content_html' => '<p>' . str_repeat('word ', 120) . '</p>',
            ])
            ->assertSessionHasErrors('content');
    }

    public function test_spam_protection_new_user_comment_link_limit(): void
    {
        $newUser = User::factory()->create([
            'created_at' => now()->subDay(),
        ]);

        app(SettingService::class)->set('link_limit_new_users_comments', 1);

        // 2 links in comment
        $this->actingAs($newUser)
            ->post("/posts/{$this->post->slug}/comments", [
                'body' => 'Check this out: https://link1.com and also https://link2.com for deals!',
            ])
            ->assertSessionHasErrors('body');

        // 1 link in comment is allowed
        $this->actingAs($newUser)
            ->post("/posts/{$this->post->slug}/comments", [
                'body' => 'Check this out: https://link1.com for details.',
            ])
            ->assertRedirect();
    }

    public function test_signups_disabled_setting_blocks_registration(): void
    {
        app(SettingService::class)->set('signups_enabled', false);

        $this->post('/register', [
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }
}
