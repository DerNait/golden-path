<?php

namespace Tests\Unit;

use App\Support\YouTubeUrl;
use PHPUnit\Framework\TestCase;

class YouTubeUrlTest extends TestCase
{
    public function test_supported_youtube_urls_are_normalized(): void
    {
        $videoId='dQw4w9WgXcQ';
        $urls=[
            "https://www.youtube.com/watch?v={$videoId}&feature=shared",
            "https://youtube.com/watch?v={$videoId}",
            "https://m.youtube.com/watch?v={$videoId}",
            "https://youtu.be/{$videoId}?si=reference",
            "https://www.youtube.com/shorts/{$videoId}",
            "https://www.youtube.com/embed/{$videoId}",
        ];

        foreach ($urls as $url) {
            $this->assertSame($videoId,YouTubeUrl::videoId($url));
            $this->assertSame("https://www.youtube.com/watch?v={$videoId}",YouTubeUrl::canonical($url));
            $this->assertSame("https://www.youtube-nocookie.com/embed/{$videoId}",YouTubeUrl::embed($url));
        }
    }

    public function test_non_youtube_or_unsafe_urls_are_rejected(): void
    {
        $urls=[
            'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=short',
            'https://www.youtube.com/channel/dQw4w9WgXcQ',
            'https://user@www.youtube.com/watch?v=dQw4w9WgXcQ',
            'not-a-url',
            '',
        ];

        foreach ($urls as $url) {
            $this->assertNull(YouTubeUrl::videoId($url));
            $this->assertNull(YouTubeUrl::canonical($url));
            $this->assertNull(YouTubeUrl::embed($url));
        }
    }
}
