<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

/**
 * Retrouve l'identifiant d'une vidéo YouTube dans n'importe quel lien de partage — l'ID brut, `youtu.be/…`,
 * `watch?v=…`, `/embed/…`, `/shorts/…`, `/live/…`, `/v/…`, avec ou sans `www.`/`m.` — sans passer par l'API
 * YouTube : la bibliothèque ne garde que cet ID et la vignette officielle qu'il permet de retrouver.
 *
 * Reprend les mêmes règles que `App\Livewire\Des\Concerns\ResolvesYoutubeEmbeds` de l'application, dupliquées
 * ici plutôt que partagées : ce paquet ne dépend jamais du code applicatif qui l'installe.
 */
final class YoutubeUrl
{
    public static function id(mixed $source): ?string
    {
        $source = trim((string) $source);

        if ($source === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $source)) {
            return $source;
        }

        if (preg_match('/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?[^\s]*v=|embed\/|shorts\/|live\/|v\/))([A-Za-z0-9_-]{11})/i', $source, $matches)) {
            return $matches[1];
        }

        $url = preg_match('/^https?:\/\//i', $source) ? $source : 'https://'.ltrim($source, '/');
        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        if ($host === 'youtu.be') {
            return self::validId($segments[0] ?? null);
        }

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);

            if (($segments[0] ?? null) === 'watch') {
                return self::validId($query['v'] ?? null);
            }

            if (in_array($segments[0] ?? null, ['embed', 'shorts', 'live', 'v'], true)) {
                return self::validId($segments[1] ?? null);
            }
        }

        return null;
    }

    public static function watchUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    public static function embedUrl(string $videoId): string
    {
        return "https://www.youtube.com/embed/{$videoId}";
    }

    /** La vignette officielle, toujours disponible (contrairement à `maxresdefault.jpg`, pas toujours généré). */
    public static function thumbnailUrl(string $videoId): string
    {
        return "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
    }

    private static function validId(mixed $videoId): ?string
    {
        $videoId = trim((string) $videoId);

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? $videoId : null;
    }
}
