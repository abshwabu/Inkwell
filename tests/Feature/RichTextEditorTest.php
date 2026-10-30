<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\ContentSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichTextEditorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $bannedUser;
    protected ContentSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::USER,
            'is_banned' => false,
        ]);

        $this->bannedUser = User::factory()->create([
            'role' => UserRole::USER,
            'is_banned' => true,
        ]);

        $this->sanitizer = app(ContentSanitizerService::class);
    }

    /**
     * Test image upload endpoint authentication and banning.
     */
    public function test_image_upload_requires_auth_and_blocks_banned_users(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.jpg', 600, 400);

        // Guest is redirected to login
        $guestResponse = $this->postJson(route('uploads.images'), [
            'image' => $file,
        ]);
        $guestResponse->assertStatus(401);

        // Banned user receives 403 Forbidden
        $bannedResponse = $this->actingAs($this->bannedUser)->postJson(route('uploads.images'), [
            'image' => $file,
        ]);
        $bannedResponse->assertStatus(403);
    }

    /**
     * Test image upload validates mime type and max file size.
     */
    public function test_image_upload_validation(): void
    {
        Storage::fake('public');

        // Invalid file type (text instead of image)
        $txtFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        $response = $this->actingAs($this->user)->postJson(route('uploads.images'), [
            'image' => $txtFile,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('image');

        // Oversized file (> 5 MB)
        $largeFile = UploadedFile::fake()->image('huge.jpg')->size(6000);
        $response = $this->actingAs($this->user)->postJson(route('uploads.images'), [
            'image' => $largeFile,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    /**
     * Test image upload resizes to max-width 1600px and re-encodes to WebP.
     */
    public function test_image_upload_resizes_to_1600px_and_encodes_webp(): void
    {
        Storage::fake('public');

        // Create an image wider than 1600px (2400x1200)
        $file = UploadedFile::fake()->image('wide-banner.png', 2400, 1200);

        $response = $this->actingAs($this->user)->postJson(route('uploads.images'), [
            'image' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['url']);

        $url = $response->json('url');
        $this->assertStringEndsWith('.webp', $url);

        // Extract filename from URL
        $pathParts = explode('/storage/', $url);
        $relativePath = end($pathParts);

        Storage::disk('public')->assertExists($relativePath);

        // Verify stored image dimensions
        $fullPath = Storage::disk('public')->path($relativePath);
        $imgInfo = getimagesize($fullPath);

        $this->assertEquals(1600, $imgInfo[0], 'Width should be scaled to 1600px');
        $this->assertEquals(800, $imgInfo[1], 'Height should maintain 2:1 aspect ratio');
        $this->assertEquals('image/webp', $imgInfo['mime'], 'Image must be WebP format');
    }

    /**
     * Test image upload rate limiting.
     */
    public function test_image_upload_rate_limiting(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        // 30 requests within 1 minute limit
        for ($i = 0; $i < 30; $i++) {
            $file = UploadedFile::fake()->image("test_{$i}.jpg", 50, 50);
            $response = $this->actingAs($user)->postJson(route('uploads.images'), [
                'image' => $file,
            ]);
            $response->assertStatus(201);
        }

        // 31st request within the same minute is throttled
        $file = UploadedFile::fake()->image('test_throttled.jpg', 50, 50);
        $throttled = $this->actingAs($user)->postJson(route('uploads.images'), [
            'image' => $file,
        ]);
        $throttled->assertStatus(429);
    }

    /**
     * Test server-side sanitization strips malicious payloads.
     */
    public function test_sanitization_strips_scripts_event_handlers_and_javascript_links(): void
    {
        // Script tag
        $scriptPayload = '<p>Hello <script>alert("XSS")</script>World</p>';
        $cleanedScript = $this->sanitizer->sanitize($scriptPayload);
        $this->assertStringNotContainsString('<script', $cleanedScript);
        $this->assertStringNotContainsString('alert', $cleanedScript);
        $this->assertStringContainsString('Hello World', $cleanedScript);

        // Inline event handlers
        $onerrorPayload = '<p><img src="https://example.com/pic.jpg" onerror="alert(document.cookie)" onload="hack()"></p>';
        $cleanedOnerror = $this->sanitizer->sanitize($onerrorPayload);
        $this->assertStringNotContainsString('onerror', $cleanedOnerror);
        $this->assertStringNotContainsString('onload', $cleanedOnerror);
        $this->assertStringNotContainsString('alert', $cleanedOnerror);

        // Javascript protocol link
        $jsLinkPayload = '<p><a href="javascript:alert(1)">Click here</a></p>';
        $cleanedJsLink = $this->sanitizer->sanitize($jsLinkPayload);
        $this->assertStringNotContainsString('javascript:', $cleanedJsLink);

        // Disallowed iframes
        $evilIframePayload = '<p><iframe src="https://malicious-phishing.com/login"></iframe></p>';
        $cleanedIframe = $this->sanitizer->sanitize($evilIframePayload);
        $this->assertStringNotContainsString('malicious-phishing.com', $cleanedIframe);
        $this->assertStringNotContainsString('<iframe', $cleanedIframe);
    }

    /**
     * Test external links are forced with rel="noopener noreferrer nofollow ugc".
     */
    public function test_external_links_force_nofollow_and_ugc(): void
    {
        $externalPayload = '<p>Check out <a href="https://externalsite.com/article">External Article</a>.</p>';
        $cleaned = $this->sanitizer->sanitize($externalPayload);

        $this->assertStringContainsString('rel="noopener noreferrer nofollow ugc"', $cleaned);
        $this->assertStringContainsString('target="_blank"', $cleaned);
    }

    /**
     * Test YouTube iframes are permitted and forced to youtube-nocookie.
     */
    public function test_youtube_iframes_use_privacy_enhanced_nocookie(): void
    {
        $ytWatchPayload = '<p><iframe src="https://www.youtube.com/watch?v=dQw4w9WgXcQ"></iframe></p>';
        $cleanedWatch = $this->sanitizer->sanitize($ytWatchPayload);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $cleanedWatch);

        $ytEmbedPayload = '<p><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe></p>';
        $cleanedEmbed = $this->sanitizer->sanitize($ytEmbedPayload);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $cleanedEmbed);
    }

    /**
     * Test allowed formatting elements round-trip cleanly.
     */
    public function test_formatting_elements_preserved(): void
    {
        $html = '<h1>Header 1</h1>'
            . '<h2>Header 2</h2>'
            . '<p>Normal <strong>bold</strong> <em>italic</em> <u>underline</u> <s>strike</s> <mark>highlight</mark> <code>code</code></p>'
            . '<blockquote>A wise quote</blockquote>'
            . '<pre><code class="language-python">print("hello")</code></pre>'
            . '<ul><li>Item 1</li><li>Item 2</li></ul>'
            . '<ol><li>Step 1</li><li>Step 2</li></ol>'
            . '<table><thead><tr><th>Title</th></tr></thead><tbody><tr><td>Content</td></tr></tbody></table>'
            . '<hr>';

        $cleaned = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<h1>Header 1</h1>', $cleaned);
        $this->assertStringContainsString('<strong>bold</strong>', $cleaned);
        $this->assertStringContainsString('<em>italic</em>', $cleaned);
        $this->assertStringContainsString('<u>underline</u>', $cleaned);
        $this->assertStringContainsString('<blockquote>A wise quote</blockquote>', $cleaned);
        $this->assertStringContainsString('<pre><code class="language-python">print("hello")</code></pre>', $cleaned);
        $this->assertStringContainsString('<table>', $cleaned);
    }

    /**
     * Test TipTap JSON conversion to HTML.
     */
    public function test_render_html_from_tiptap_json(): void
    {
        $json = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [
                        ['type' => 'text', 'text' => 'TipTap JSON Heading'],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Paragraph with '],
                        [
                            'type' => 'text',
                            'marks' => [['type' => 'bold']],
                            'text' => 'bold text',
                        ],
                    ],
                ],
            ],
        ];

        $rendered = $this->sanitizer->renderFromJSON($json);
        $this->assertStringContainsString('<h2>TipTap JSON Heading</h2>', $rendered);
        $this->assertStringContainsString('<strong>bold text</strong>', $rendered);
    }

    /**
     * Test draft autosave endpoint creates new draft and updates existing draft.
     */
    public function test_draft_autosave_lifecycle(): void
    {
        $category = Category::factory()->create();

        // 1. Initial autosave creates new draft
        $response = $this->actingAs($this->user)->postJson(route('posts.autosave'), [
            'title' => 'My Work In Progress',
            'category_id' => $category->id,
            'content_html' => '<p>Initial draft paragraph <script>alert(1)</script></p>',
            'content_json' => ['type' => 'doc', 'content' => []],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'slug', 'saved_at']);

        $postId = $response->json('id');
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'title' => 'My Work In Progress',
            'status' => PostStatus::DRAFT->value,
            'user_id' => $this->user->id,
        ]);

        $post = Post::find($postId);
        $this->assertEquals('<p>Initial draft paragraph </p>', $post->content_html);

        // 2. Subsequent autosave updates existing draft
        $updateResponse = $this->actingAs($this->user)->postJson(route('posts.autosave'), [
            'id' => $postId,
            'title' => 'My Updated Title',
            'content_html' => '<p>Second revision of draft</p>',
        ]);

        $updateResponse->assertStatus(200);

        $post->refresh();
        $this->assertEquals('My Updated Title', $post->title);
        $this->assertEquals('<p>Second revision of draft</p>', $post->content_html);
        $this->assertEquals(PostStatus::DRAFT, $post->status);

        // 3. Another user cannot autosave this post
        $otherUser = User::factory()->create();
        $unauthResponse = $this->actingAs($otherUser)->postJson(route('posts.autosave'), [
            'id' => $postId,
            'title' => 'Malicious takeover',
        ]);
        $unauthResponse->assertStatus(403);
    }

    /**
     * Test post store and update sanitizes content.
     */
    public function test_post_store_and_update_sanitizes_content(): void
    {
        $category = Category::factory()->create();

        $content100Words = '<p>' . str_repeat('Inkwell is an open blogging platform designed for thoughtful writers. ', 12) . '</p><script>evil()</script><a href="https://external.com">Link</a>';

        // Store new post with malicious payload
        $response = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => 'Story with Malicious Payload',
            'category_id' => $category->id,
            'content_html' => $content100Words,
            'status' => 'published',
        ]);

        $response->assertSessionHasNoErrors();

        $post = Post::where('title', 'Story with Malicious Payload')->firstOrFail();
        $this->assertStringNotContainsString('<script', $post->content_html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow ugc"', $post->content_html);

        $updatedContent = '<p>' . str_repeat('Updating thoughtful content for Inkwell readers across the world. ', 12) . '</p><img src=x onerror=hack()>';

        // Update post with malicious payload
        $updateResponse = $this->actingAs($this->user)->put(route('posts.update', $post->slug), [
            'title' => 'Updated Story',
            'category_id' => $category->id,
            'content_html' => $updatedContent,
        ]);

        $updateResponse->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertEquals('Updated Story', $post->title);
        $this->assertStringNotContainsString('onerror', $post->content_html);
    }
}
