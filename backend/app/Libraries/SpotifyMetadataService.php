<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;

class SpotifyMetadataService
{
    /**
     * @return array{
     *   spotify_url: string,
     *   song_title: string|null,
     *   artist_name: string|null,
     *   album_name: string|null,
     *   cover_url: string|null,
     *   embed_url: string|null,
     *   embed_html: string|null
     * }|null
     */
    public function resolve(?string $spotifyUrl): ?array
    {
        $spotifyUrl = trim((string) $spotifyUrl);
        if ($spotifyUrl === '') {
            return null;
        }

        $normalizedUrl = $this->normalizeSpotifyUrl($spotifyUrl);
        if ($normalizedUrl === null) {
            return null;
        }

        $metadata = [
            'spotify_url' => $normalizedUrl,
            'song_title'  => null,
            'artist_name' => null,
            'album_name'  => null,
            'cover_url'   => null,
            'embed_url'   => $this->buildEmbedUrl($normalizedUrl),
            'embed_html'  => null,
        ];

        try {
            $client   = Services::curlrequest();
            $response = $client->get('https://open.spotify.com/oembed', [
                'query'       => ['url' => $normalizedUrl],
                'timeout'     => 5,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return $metadata;
            }

            $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                return $metadata;
            }

            $metadata['song_title'] = isset($payload['title']) ? (string) $payload['title'] : null;

            $author = isset($payload['author_name']) ? trim((string) $payload['author_name']) : '';
            if ($author !== '' && strcasecmp($author, 'spotify') !== 0) {
                $metadata['artist_name'] = $author;
            }

            $metadata['cover_url']  = isset($payload['thumbnail_url']) ? (string) $payload['thumbnail_url'] : null;
            $metadata['embed_html'] = isset($payload['html']) ? (string) $payload['html'] : null;
        } catch (\Throwable) {
        }

        $openGraph = $this->fetchOpenGraphMetadata($normalizedUrl);
        if ($openGraph !== null) {
            if ($metadata['song_title'] === null || $metadata['song_title'] === '') {
                $metadata['song_title'] = $openGraph['title'] ?? null;
            }

            if ($metadata['cover_url'] === null || $metadata['cover_url'] === '') {
                $metadata['cover_url'] = $openGraph['image'] ?? null;
            }

            if (($metadata['artist_name'] === null || $metadata['artist_name'] === '')
                && isset($openGraph['artist']) && $openGraph['artist'] !== ''
            ) {
                $metadata['artist_name'] = $openGraph['artist'];
            }

            if (($metadata['album_name'] === null || $metadata['album_name'] === '')
                && isset($openGraph['album']) && $openGraph['album'] !== ''
            ) {
                $metadata['album_name'] = $openGraph['album'];
            }
        }

        if (($metadata['artist_name'] === null || $metadata['artist_name'] === '') && $metadata['song_title']) {
            $parsed = $this->parseSongArtistFromTitle((string) $metadata['song_title']);
            if ($parsed['artist'] !== null) {
                $metadata['artist_name'] = $parsed['artist'];
            }
            if ($parsed['title'] !== null) {
                $metadata['song_title'] = $parsed['title'];
            }
        }

        return $metadata;
    }

    public function isValidSpotifyUrl(?string $spotifyUrl): bool
    {
        return $this->normalizeSpotifyUrl((string) $spotifyUrl) !== null;
    }

    private function normalizeSpotifyUrl(string $spotifyUrl): ?string
    {
        if (! filter_var($spotifyUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($spotifyUrl);
        if (! is_array($parts) || ! isset($parts['host'], $parts['path'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if (! in_array($host, ['open.spotify.com', 'www.open.spotify.com'], true)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim((string) $parts['path'], '/'))));
        if ($segments === []) {
            return null;
        }

        // Acepta URLs de Spotify con prefijo de idioma, ej. /intl-es/track/{id}
        if (preg_match('/^intl-[a-z]{2}$/i', $segments[0]) === 1) {
            array_shift($segments);
        }

        // Acepta URLs de embed, ej. /embed/track/{id}
        if (($segments[0] ?? '') === 'embed') {
            array_shift($segments);
        }

        $type = $segments[0] ?? '';
        $id   = $segments[1] ?? '';

        if (! in_array($type, ['track', 'album', 'playlist', 'episode', 'show'], true)) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9]+$/', $id) !== 1) {
            return null;
        }

        return 'https://open.spotify.com/' . $type . '/' . $id;
    }

    private function buildEmbedUrl(string $spotifyUrl): ?string
    {
        $parts = parse_url($spotifyUrl);
        if (! is_array($parts) || ! isset($parts['path'])) {
            return null;
        }

        $path = trim((string) $parts['path'], '/');
        if ($path === '') {
            return null;
        }

        return 'https://open.spotify.com/embed/' . $path;
    }

    /**
     * @return array{title: string|null, artist: string|null, album: string|null, image: string|null}|null
     */
    private function fetchOpenGraphMetadata(string $spotifyUrl): ?array
    {
        try {
            $client = Services::curlrequest();
            $response = $client->get($spotifyUrl, [
                'timeout'     => 5,
                'http_errors' => false,
                'headers'     => [
                    'User-Agent'      => 'Mozilla/5.0 RemindMe/1.0',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = (string) $response->getBody();
            if ($html === '') {
                return null;
            }

            $title       = $this->extractMetaTag($html, 'property', 'og:title');
            $description = $this->extractMetaTag($html, 'property', 'og:description');
            $image       = $this->extractMetaTag($html, 'property', 'og:image');

            [$artist, $album] = $this->parseArtistAlbumFromDescription($description);

            return [
                'title'  => $title,
                'artist' => $artist,
                'album'  => $album,
                'image'  => $image,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractMetaTag(string $html, string $attrName, string $attrValue): ?string
    {
        $patternA = sprintf(
            '/<meta[^>]*%s=["\']%s["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i',
            preg_quote($attrName, '/'),
            preg_quote($attrValue, '/')
        );
        $patternB = sprintf(
            '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*%s=["\']%s["\'][^>]*>/i',
            preg_quote($attrName, '/'),
            preg_quote($attrValue, '/')
        );

        if (preg_match($patternA, $html, $matches) !== 1 && preg_match($patternB, $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseArtistAlbumFromDescription(?string $description): array
    {
        if ($description === null || $description === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*\x{00B7}\s*/u', $description) ?: [];
        $parts = array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts)));

        if (count($parts) < 2) {
            return [null, null];
        }

        $artist = $parts[0] ?? null;
        $album  = $parts[1] ?? null;

        if ($this->isNonArtistToken($artist)) {
            $artist = null;
        }

        if ($this->isNonAlbumToken($album)) {
            $album = null;
        }

        return [$artist !== '' ? $artist : null, $album !== '' ? $album : null];
    }

    private function isNonArtistToken(?string $value): bool
    {
        if (! is_string($value) || $value === '') {
            return true;
        }

        return strcasecmp($value, 'spotify') === 0;
    }

    private function isNonAlbumToken(?string $value): bool
    {
        if (! is_string($value) || $value === '') {
            return true;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['song', 'single', 'album', 'playlist', 'episode', 'show', 'podcast'], true)) {
            return true;
        }

        return preg_match('/^\d{4}$/', $normalized) === 1;
    }

    /**
     * @return array{title: string|null, artist: string|null}
     */
    private function parseSongArtistFromTitle(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['title' => null, 'artist' => null];
        }

        if (preg_match('/^(.*?)\s+-\s+(.*?)$/u', $title, $matches) === 1) {
            return [
                'title'  => trim($matches[1]),
                'artist' => trim($matches[2]),
            ];
        }

        if (preg_match('/^(.*?)\s+by\s+(.*?)$/ui', $title, $matches) === 1) {
            return [
                'title'  => trim($matches[1]),
                'artist' => trim($matches[2]),
            ];
        }

        return ['title' => $title, 'artist' => null];
    }
}
