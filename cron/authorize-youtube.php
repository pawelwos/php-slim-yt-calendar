#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-time setup: lets you authorize read-only access to YOUR YouTube channel and stores
 * the resulting refresh token in storage/youtube-token.json for the sync job.
 *
 *   php cron/authorize-youtube.php [--redirect=http://localhost:8080/auth/google/callback]
 *
 * Sign in with the Google account that owns the channel. The redirect URI must be one of the
 * "Authorized redirect URIs" of your OAuth client (the login callback already is). Stop the
 * dev server first if it is using that port; this script listens there briefly to catch the code.
 * Run it on your own computer; afterwards copy the token file to the server.
 */

require __DIR__ . '/bootstrap.php';

use App\YoutubeClient;

$opts = getopt('', ['redirect:']);
$redirect = (string) ($opts['redirect'] ?? 'http://localhost:8080/auth/google/callback');
$tokenPath = project_path($root, $_ENV['YOUTUBE_TOKEN_PATH'] ?? 'storage/youtube-token.json');

if (empty($_ENV['GOOGLE_CLIENT_ID']) || empty($_ENV['GOOGLE_CLIENT_SECRET'])) {
    fail('GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are not set in .env');
}

$client = YoutubeClient::newOAuthClient($_ENV['GOOGLE_CLIENT_ID'], $_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($redirect);
$state = bin2hex(random_bytes(16));
$client->setState($state);

$parts = parse_url($redirect);
$server = ($parts['host'] ?? '') === 'localhost' || ($parts['host'] ?? '') === '127.0.0.1'
    ? @stream_socket_server('tcp://127.0.0.1:' . ($parts['port'] ?? 80), $errno, $errstr)
    : false;

echo "\n1. Open this URL in your browser and approve access (read-only YouTube):\n\n   " . $client->createAuthUrl() . "\n\n";

$code = null;
if ($server) {
    echo "2. Waiting for Google to redirect back to {$redirect} ...\n";
    $conn = @stream_socket_accept($server, 300);
    if ($conn) {
        $request = (string) fgets($conn);
        preg_match('#^GET (\S+)#', $request, $m);
        parse_str((string) parse_url($m[1] ?? '', PHP_URL_QUERY), $query);
        $ok = isset($query['code'], $query['state']) && hash_equals($state, (string) $query['state']);
        $body = $ok ? 'Authorization received. You can close this tab and return to the terminal.' : 'Authorization failed. Check the terminal.';
        fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/plain; charset=utf-8\r\nConnection: close\r\n\r\n$body\n");
        fclose($conn);
        if (isset($query['error'])) {
            fail('Google returned an error: ' . $query['error']);
        }
        $code = $ok ? (string) $query['code'] : null;
    }
    fclose($server);
} else {
    echo "2. Could not listen on the redirect port (is the dev server running?).\n"
       . "   After approving, your browser is sent to {$redirect}?code=...\n"
       . "   Paste that full URL here: ";
    $pasted = trim((string) fgets(STDIN));
    parse_str((string) parse_url($pasted, PHP_URL_QUERY), $query);
    if (isset($query['code'], $query['state']) && hash_equals($state, (string) $query['state'])) {
        $code = (string) $query['code'];
    }
}

if ($code === null) {
    fail('No valid authorization code received.');
}

$token = $client->fetchAccessTokenWithAuthCode($code);
if (isset($token['error'])) {
    fail('Token exchange failed: ' . ($token['error_description'] ?? $token['error']));
}
if (empty($token['refresh_token'])) {
    fail('Google did not return a refresh token. Revoke the app at https://myaccount.google.com/permissions and try again.');
}

@mkdir(dirname($tokenPath), 0775, true);
file_put_contents($tokenPath, json_encode([
    'refresh_token' => $token['refresh_token'],
    'scope' => $token['scope'] ?? YoutubeClient::SCOPE,
    'created' => date('c'),
], JSON_PRETTY_PRINT) . "\n");
chmod($tokenPath, 0600);

echo "\nSaved refresh token to {$tokenPath}\nNow test with: php cron/sync-youtube.php --dry-run --days=30\n";
