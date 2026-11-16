<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_strips_scripts_iframes_styles_and_event_handlers(): void
    {
        $sanitizer = new HtmlSanitizer();

        $dirty = '<script>alert("xss")</script>'
            .'<style>body { color: red; }</style>'
            .'<iframe src="https://evil.example.com"></iframe>'
            .'<p onclick="steal()" style="color:red">Hello <strong onmouseover="hack()">World</strong></p>'
            .'<img src="https://tracker.example.com/1x1.gif" width="1" height="1" onerror="alert(1)" />';

        $clean = $sanitizer->sanitize($dirty);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert("xss")', $clean);
        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringContainsString('<p>Hello <strong>World</strong></p>', $clean);
    }

    public function test_it_sanitizes_links_and_preserves_safe_formatting(): void
    {
        $sanitizer = new HtmlSanitizer();

        $dirty = '<div class="wrapper">'
            .'<p>Read <a href="https://laravel.com/docs" onclick="bad()">Laravel Docs</a> or '
            .'<a href="javascript:alert(1)">Bad Link</a></p>'
            .'<pre><code>php artisan test</code></pre>'
            .'</div>';

        $clean = $sanitizer->sanitize($dirty);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<a href="https://laravel.com/docs" rel="noopener noreferrer" target="_blank">Laravel Docs</a>', $clean);
        $this->assertStringContainsString('<a>Bad Link</a>', $clean);
        $this->assertStringContainsString('<pre><code>php artisan test</code></pre>', $clean);
    }

    public function test_it_returns_null_when_only_unsafe_tags_or_whitespace_exist(): void
    {
        $sanitizer = new HtmlSanitizer();

        $this->assertNull($sanitizer->sanitize(null));
        $this->assertNull($sanitizer->sanitize('   '));
        $this->assertNull($sanitizer->sanitize('<script>alert(1)</script><img src="x" width="1" height="1">'));
    }

    public function test_plain_text_strips_tags_and_decodes_entities(): void
    {
        $sanitizer = new HtmlSanitizer();

        $this->assertSame(
            'Queue Workers & SQLite WAL Tuning',
            $sanitizer->plainText('<b>Queue</b> Workers &amp; SQLite   WAL Tuning')
        );
    }
}
