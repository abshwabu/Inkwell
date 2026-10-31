<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndXssTest extends TestCase
{
    use RefreshDatabase;

    protected ContentSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = app(ContentSanitizerService::class);
    }

    public function test_editor_pipeline_strips_script_tags(): void
    {
        $dirty = '<p>Normal text</p><script>alert("xss")</script><p>More text</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(', $clean);
        $this->assertStringContainsString('Normal text', $clean);
    }

    public function test_editor_pipeline_strips_all_event_handlers(): void
    {
        $dirty = '<img src="https://example.com/pic.jpg" onerror="alert(1)" onload="fetch(\'/steal\')" onclick="doEvil()">';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('src="https://example.com/pic.jpg"', $clean);
        $this->assertStringContainsString('loading="lazy"', $clean);
    }

    public function test_editor_pipeline_strips_javascript_and_vbscript_hrefs(): void
    {
        $dirty = '<a href="javascript:alert(\'pwned\')">Click Here</a><a href="vbscript:msgbox(1)">Click 2</a>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('vbscript:', $clean);
        $this->assertStringNotContainsString('alert(', $clean);
    }

    public function test_editor_pipeline_restricts_iframes_strictly_to_youtube_nocookie(): void
    {
        $malicious = '<iframe src="https://attacker.com/cookie-stealer.html"></iframe>';
        $cleanMalicious = $this->sanitizer->sanitize($malicious);
        $this->assertStringNotContainsString('attacker.com', $cleanMalicious);

        $validYoutube = '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>';
        $cleanYoutube = $this->sanitizer->sanitize($validYoutube);
        $this->assertStringContainsString('youtube-nocookie.com', $cleanYoutube);
    }

    public function test_editor_pipeline_strips_css_javascript_expressions(): void
    {
        $dirty = '<p style="color: red; background-image: url(\'javascript:alert(1)\'); behavior: url(xss.htc)">Colored</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('behavior', $clean);
        $this->assertStringContainsString('color: red', $clean);
    }

    public function test_http_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_malicious_upload_extensions_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // 1. PHP script disguised as image
        $phpFile = UploadedFile::fake()->create('shell.php', 100, 'application/x-php');
        $response = $this->actingAs($user)->postJson(route('uploads.images'), [
            'image' => $phpFile,
        ]);
        $response->assertStatus(422);

        // 2. SVG file containing executable scripts
        $svgFile = UploadedFile::fake()->create('vector.svg', 100, 'image/svg+xml');
        $response = $this->actingAs($user)->postJson(route('uploads.images'), [
            'image' => $svgFile,
        ]);
        $response->assertStatus(422);

        // 3. Executable binary
        $exeFile = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');
        $response = $this->actingAs($user)->postJson(route('uploads.images'), [
            'image' => $exeFile,
        ]);
        $response->assertStatus(422);
    }

    public function test_health_check_endpoint_returns_sqlite_diagnostics(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('sqlite.connected', true)
            ->assertJsonPath('sqlite.foreign_keys', true)
            ->assertJsonPath('storage.writable', true)
            ->assertJsonPath('cache.working', true);

        $this->assertContains($response->json('sqlite.journal_mode'), ['wal', 'memory']);
    }

    public function test_sitemap_endpoint_returns_valid_xml(): void
    {
        $category = Category::factory()->create();
        $author = User::factory()->create();
        Post::factory()->create([
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<urlset', $response->getContent());
        $this->assertStringContainsString(route('categories.show', $category->slug), $response->getContent());
        $this->assertStringContainsString(route('author.show', $author->username), $response->getContent());
    }

    public function test_rss_feeds_validate(): void
    {
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        $author = User::factory()->create(['name' => 'Jane Dev', 'username' => 'janedev']);
        $post = Post::factory()->create([
            'category_id' => $category->id,
            'user_id' => $author->id,
            'title' => 'Building Fast Software',
            'slug' => 'building-fast-software',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        // 1. Main RSS feed
        $mainResponse = $this->get(route('feed'));
        $mainResponse->assertOk();
        $this->assertStringContainsString('application/rss+xml', $mainResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('<rss version="2.0"', $mainResponse->getContent());
        $this->assertStringContainsString('Building Fast Software', $mainResponse->getContent());

        // 2. Category feed
        $categoryResponse = $this->get(route('feed.category', $category->slug));
        $categoryResponse->assertOk();
        $this->assertStringContainsString('Technology', $categoryResponse->getContent());

        // 3. Author feed
        $authorResponse = $this->get(route('feed.author', $author->username));
        $authorResponse->assertOk();
        $this->assertStringContainsString('Jane Dev', $authorResponse->getContent());
    }

    public function test_guest_response_caching_and_etag(): void
    {
        // First request is a MISS
        $response1 = $this->withHeaders(['X-Test-Guest-Cache' => '1'])->get('/');
        $response1->assertOk();
        $this->assertEquals('MISS', $response1->headers->get('X-Cache'));
        $etag = $response1->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // Second request is a HIT
        $response2 = $this->withHeaders(['X-Test-Guest-Cache' => '1'])->get('/');
        $response2->assertOk();
        $this->assertEquals('HIT', $response2->headers->get('X-Cache'));

        // Conditional GET with If-None-Match returns 304 Not Modified
        $response3 = $this->withHeaders([
            'X-Test-Guest-Cache' => '1',
            'If-None-Match' => $etag,
        ])->get('/');
        $response3->assertStatus(304);
    }
}
