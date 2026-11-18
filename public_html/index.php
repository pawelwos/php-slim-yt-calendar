<?php

declare(strict_types=1);

use App\AuthController;
use App\Database;
use App\CalendarController;
use App\Localizer;
use App\TwigExtension;
use App\VideoRepository;
use App\Middleware\AuthMiddleware;
use DI\Container;
use Dotenv\Dotenv;
use League\OAuth2\Client\Provider\Google;
use Slim\Csrf\Guard;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createImmutable($root)->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Warsaw');
$debug = ($_ENV['APP_ENV'] ?? 'production') === 'development';
$appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => str_starts_with($appUrl, 'https://'),
]);
session_start();

$container = new Container();
$container->set(PDO::class, fn () => Database::connect($root . '/' . ($_ENV['DB_PATH'] ?? 'storage/database.sqlite')));
$container->set(VideoRepository::class, fn ($c) => new VideoRepository($c->get(PDO::class)));
$container->set(DateTimeZone::class, fn () => new DateTimeZone(date_default_timezone_get()));
$container->set(Localizer::class, fn () => new Localizer($_ENV['APP_LOCALE'] ?? 'pl_PL', date_default_timezone_get()));
$container->set(Twig::class, function ($c) use ($root) {
    $twig = Twig::create($root . '/templates', ['cache' => false]);
    $twig->addExtension(new TwigExtension($c->get(Localizer::class)));
    return $twig;
});
$container->set(Google::class, fn () => new Google([
    'clientId' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
    'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
    'redirectUri' => $appUrl . '/auth/google/callback',
]));
$container->set(AuthController::class, fn ($c) => new AuthController(
    $c->get(Google::class),
    $c->get(Twig::class),
    array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', $_ENV['ALLOWED_EMAILS'] ?? '')
    ))),
));

AppFactory::setContainer($container);
$app = AppFactory::create();

$responseFactory = $app->getResponseFactory();
$csrf = new Guard($responseFactory);
$csrf->setPersistentTokenMode(true);

// Expose CSRF tokens to Twig (used by the logout form).
$app->add(function ($request, $handler) use ($container) {
    $twig = $container->get(Twig::class);
    $twig->getEnvironment()->addGlobal('user', $_SESSION['user'] ?? null);
    $twig->getEnvironment()->addGlobal('csrf', [
        'name_key' => $request->getAttribute('csrf_name_key') ?? 'csrf_name',
        'value_key' => $request->getAttribute('csrf_value_key') ?? 'csrf_value',
        'name' => $request->getAttribute('csrf_name'),
        'value' => $request->getAttribute('csrf_value'),
    ]);
    return $handler->handle($request);
});
$app->add($csrf);
$app->add(TwigMiddleware::create($app, $container->get(Twig::class)));

// Public routes
$app->get('/login', [AuthController::class, 'login']);
$app->get('/auth/google', [AuthController::class, 'redirectToGoogle']);
$app->get('/auth/google/callback', [AuthController::class, 'callback']);

// Protected routes
$app->group('', function ($group) {
    $group->get('/', [CalendarController::class, 'home']);
    $group->get('/calendar', [CalendarController::class, 'current']);
    $group->get('/calendar/{year:[0-9]{4}}/{month:[0-9]{1,2}}', [CalendarController::class, 'calendar']);
    $group->get('/calendar/{year:[0-9]{4}}/{month:[0-9]{1,2}}/{day:[0-9]{1,2}}', [CalendarController::class, 'calendar']);
    $group->post('/logout', [AuthController::class, 'logout']);
})->add(new AuthMiddleware());

$app->addErrorMiddleware($debug, true, true);
$app->run();
