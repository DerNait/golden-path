<?php

namespace App\Support;

final class YouTubeUrl
{
    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    public static function videoId(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts=parse_url(trim($url));
        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            return null;
        }

        $host=strtolower(rtrim($parts['host'] ?? '', '.'));
        $segments=array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/')), static fn (string $segment): bool => $segment !== ''));
        $candidate=null;

        if ($host === 'youtu.be' && count($segments) === 1) {
            $candidate=$segments[0];
        } elseif (in_array($host,['youtube.com','www.youtube.com','m.youtube.com'],true)) {
            if (($parts['path'] ?? '') === '/watch') {
                parse_str($parts['query'] ?? '',$query);
                $candidate=is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (count($segments) === 2 && in_array($segments[0],['shorts','embed'],true)) {
                $candidate=$segments[1];
            }
        }

        return is_string($candidate) && preg_match(self::VIDEO_ID_PATTERN,$candidate) === 1
            ? $candidate
            : null;
    }

    public static function canonical(?string $url): ?string
    {
        $videoId=self::videoId($url);

        return $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null;
    }

    public static function embed(?string $url): ?string
    {
        $videoId=self::videoId($url);

        return $videoId ? "https://www.youtube-nocookie.com/embed/{$videoId}" : null;
    }
}
