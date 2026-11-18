<?php

declare(strict_types=1);

namespace App;

use League\OAuth2\Client\Provider\Google;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    /** @param string[] $allowedEmails lowercase */
    public function __construct(
        private Google $provider,
        private Twig $view,
        private array $allowedEmails,
    ) {}

    public function login(Request $request, Response $response): Response
    {
        if (!empty($_SESSION['user'])) {
            return $this->redirect($response, '/');
        }
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        return $this->view->render($response, 'login.twig', ['error' => $error]);
    }

    public function redirectToGoogle(Request $request, Response $response): Response
    {
        $url = $this->provider->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
            'prompt' => 'select_account',
        ]);
        $_SESSION['oauth2_state'] = $this->provider->getState();
        return $this->redirect($response, $url);
    }

    public function callback(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $state = $_SESSION['oauth2_state'] ?? null;
        unset($_SESSION['oauth2_state']);

        if (isset($q['error'])) {
            return $this->fail($response, 'Google sign-in was cancelled.');
        }
        if (empty($q['code']) || empty($q['state']) || $state === null || !hash_equals($state, (string) $q['state'])) {
            return $this->fail($response, 'Invalid sign-in request. Please try again.');
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', ['code' => $q['code']]);
            $owner = $this->provider->getResourceOwner($token);
        } catch (\Throwable $e) {
            error_log('OAuth error: ' . $e->getMessage());
            return $this->fail($response, 'Could not complete sign-in with Google.');
        }

        $email = strtolower((string) $owner->getEmail());
        $data = $owner->toArray();
        $verified = $data['email_verified'] ?? $data['verified_email'] ?? false;

        if ($email === '' || !$verified || !in_array($email, $this->allowedEmails, true)) {
            return $this->fail($response, 'The account ' . $email . ' is not allowed to access this site.');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'email' => $email,
            'name' => $owner->getName(),
            'avatar' => $owner->getAvatar(),
        ];

        return $this->redirect($response, '/');
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        session_regenerate_id(true);
        return $this->redirect($response, '/login');
    }

    private function fail(Response $response, string $message): Response
    {
        $_SESSION['flash_error'] = $message;
        return $this->redirect($response, '/login');
    }

    private function redirect(Response $response, string $to): Response
    {
        return $response->withHeader('Location', $to)->withStatus(302);
    }
}
