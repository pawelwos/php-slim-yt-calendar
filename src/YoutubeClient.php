<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use Google\Client;
use Google\Service\YouTube;

/**
 * Lists the authorized channel's recent uploads through the YouTube Data API.
 *
 * Authorization is OAuth as the channel owner (not an API key), because the
 * channel's videos are mostly unlisted/private and an API key only sees public ones.
 */
final class YoutubeClient implements VideoSource
{
    public const SCOPE = YouTube::YOUTUBE_READONLY;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $tokenPath,
    ) {}

    public static function newOAuthClient(string $clientId, string $clientSecret): Client
    {
        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes([self::SCOPE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // always return a refresh token
        return $client;
    }

    public function recentUploads(int $days): array
    {
        $youtube = new YouTube($this->authorizedClient());

        // Every channel has a hidden "uploads" playlist containing all its videos, newest first.
        $channels = $youtube->channels->listChannels('contentDetails', ['mine' => true]);
        $channel = $channels->getItems()[0] ?? throw new \RuntimeException('No YouTube channel found for the authorized account.');
        $playlistId = $channel->getContentDetails()->getRelatedPlaylists()->getUploads();

        $cutoff = new DateTimeImmutable("-{$days} days");
        $videos = [];
        $pageToken = null;

        do {
            $params = ['playlistId' => $playlistId, 'maxResults' => 50];
            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }
            $page = $youtube->playlistItems->listPlaylistItems('snippet,contentDetails', $params);

            foreach ($page->getItems() as $item) {
                $snippet = $item->getSnippet();
                // snippet.publishedAt = when it entered the uploads playlist (drives the ordering and the stop condition)
                if (new DateTimeImmutable($snippet->getPublishedAt()) < $cutoff) {
                    return $videos;
                }

                $title = (string) $snippet->getTitle();
                if ($title === 'Private video' || $title === 'Deleted video') {
                    continue; // placeholders for videos we can't read
                }

                $videos[] = [
                    'videoId' => $item->getContentDetails()->getVideoId(),
                    'title' => $title,
                    'publishedAt' => $item->getContentDetails()->getVideoPublishedAt() ?: $snippet->getPublishedAt(),
                ];
            }
            $pageToken = $page->getNextPageToken();
        } while ($pageToken !== null);

        return $videos;
    }

    private function authorizedClient(): Client
    {
        if (!is_file($this->tokenPath)) {
            throw new \RuntimeException("No token file at {$this->tokenPath}. Run: php cron/authorize-youtube.php");
        }
        $token = json_decode((string) file_get_contents($this->tokenPath), true);
        if (empty($token['refresh_token'])) {
            throw new \RuntimeException("Token file {$this->tokenPath} has no refresh_token. Re-run: php cron/authorize-youtube.php");
        }

        $client = self::newOAuthClient($this->clientId, $this->clientSecret);
        $result = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
        if (isset($result['error'])) {
            throw new \RuntimeException(
                'Google rejected the refresh token (' . $result['error'] . '). '
                . 'If the OAuth consent screen is in "Testing", tokens expire after 7 days. Re-run: php cron/authorize-youtube.php'
            );
        }
        return $client;
    }
}
