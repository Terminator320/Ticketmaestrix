<?php
declare(strict_types=1);



use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use RedBeanPHP\R;
use Slim\Factory\AppFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;



require __DIR__ . '/vendor/autoload.php';



$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ============== DATABASE ==============
//   Connect RedBeanPHP to your MariaDB/MySQL database.
R::setup(
    'mysql:host=' . $_ENV['DB_SERVER'] . ';port=' . ($_ENV['DB_PORT'] ?? '3306') . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);
R::freeze(true);


// ============== TEMPLATE ENGINE ==============
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig   = new Environment($loader, [
    'cache'       => false,
    'auto_reload' => true,
]);



// ============== DEPENDENCY INJECTION CONTAINER ==============
//   PHP-DI container wires dependencies together.
//   Each controller receives Twig\Environment, its model(s), and the base path
//   through its constructor instead of pulling them from global scope.

$basePath = $_ENV['APP_BASE_PATH'] ?? '';


$container = new \DI\Container();
$container->set(Environment::class, $twig);

$container->set(HomeController::class, fn() => new HomeController(
    $twig,
    $basePath,
));

$container->set(AuthController::class, fn() => new AuthController(
    $twig,
    new UserModel(),
    $basePath,
));


// ==============   APPLICATION ==============

AppFactory::setContainer($container);

$app = AppFactory::create();

// Set the base path so Slim knows it's not running at the server root.
$app->setBasePath($basePath);

// Enable the Slim routing middleware (required for route matching)
$app->addRoutingMiddleware();

// Add error middleware so you get useful error pages instead of blank screens
// $app->addErrorMiddleware(true, true, true);

$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
$app->addErrorMiddleware($debug, true, true);

// ============== YOUR MIDDLEWARE ==============

// ==============  Closure middleware  ==============
$logFile = __DIR__ . '/var/app.log';

$loggerMiddleware = function (Request $request, RequestHandler $handler) use ($logFile) {

    // --- BEFORE the request is handled ---
    $start  = microtime(true);
    $method = $request->getMethod();
    $path   = $request->getUri()->getPath();

    // Pass control to the next layer (route handler or next middleware)
    $response = $handler->handle($request);

    // --- AFTER the response is ready ---
    $status  = $response->getStatusCode();
    $elapsed = round((microtime(true) - $start) * 1000);

    $timestamp = date('Y-m-d H:i:s');
    $line      = sprintf(
        "[%s] %-6s %-25s → %d  (%dms)\n",
        $timestamp, $method, $path, $status, $elapsed
    );

    file_put_contents($logFile, $line, FILE_APPEND);

    return $response;
};

// ==============  Invokable Class middleware  ==============
$app->add(new MaintenanceMiddleware(
    flagFile:        __DIR__ . '/var/maintenance.flag',
    responseFactory: $app->getResponseFactory()
));



// ==============  PSR-15 middleware  ==============
$app->add(new SecurityHeadersMiddleware());

// Logger must be added last so it wraps all other middleware and always runs
$app->add($loggerMiddleware);


// $app->add(new SecurityHeadersMiddleware([
//     'X-Frame-Options' => 'SAMEORIGIN',   // override default
//     'Cache-Control'   => 'no-store',      // add new one
// ]));

// ============== ROUTES ==============


$app->get('/',                 [HomeController::class, 'index']);
$app->get('/signup',           [AuthController::class, 'showSignup']);
$app->get('/login',            [AuthController::class, 'showLogin']);
$app->get('/forgotpassword',   [AuthController::class, 'showForgotPassword']);
$app->get('/verificationcode', [AuthController::class, 'showVerificationCode']);
$app->get('/newpassword',      [AuthController::class, 'showNewPassword']);


$app->run();
