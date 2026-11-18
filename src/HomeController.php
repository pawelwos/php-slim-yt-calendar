<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(private Twig $view) {}

    public function index(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'home.twig', ['user' => $_SESSION['user']]);
    }
}
