<?php
declare(strict_types=1);



use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use RedBeanPHP\R;
use Slim\Factory\AppFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\SecurityHeadersMiddleware;



require __DIR__ . '/vendor/autoload.php';


// ============== DATABASE ============== 
//   Connect RedBeanPHP to your MariaDB/MySQL database.
//   R::freeze(true) = use existing schema only (no auto-creation).
R::setup('mysql:host=localhost;dbname=ticketmaestrix', 'root', '');
R::freeze(true);


// ============== TEMPLATE ENGINE ==============
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig   = new Environment($loader, [
    'cache'       => false,
    'auto_reload' => true,
]);



// ============== DEPENDENCY INJECTION CONTAINER ==============
//   PHP-DI container wires dependencies together.
//   TodoController receives Twig\Environment and TodoModel via its constructor.

// $basePath = '/todo-app';

// $container = new \DI\Container();
// $container->set(Environment::class, $twig);
// $container->set(TodoController::class, fn() => new TodoController($twig, new TodoModel(), $basePath));


// ==============   APPLICATION ==============

// AppFactory::setContainer($container);

$app = AppFactory::create();

// Set the base path so Slim knows it's not running at the server root.
$app->setBasePath('/Ticketmaestrix');

// Add error middleware so you get useful error pages instead of blank screens
$app->addErrorMiddleware(true, true, true);

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

$app->add($loggerMiddleware);


// ==============  Invokable Class middleware  ==============
$app->add(new MaintenanceMiddleware(
    flagFile:        __DIR__ . '/var/maintenance.flag',
    responseFactory: $app->getResponseFactory()
));



// ==============  PSR-15 middleware  ==============

$app->add(new SecurityHeadersMiddleware());

// $app->add(new SecurityHeadersMiddleware([
//     'X-Frame-Options' => 'SAMEORIGIN',   // override default
//     'Cache-Control'   => 'no-store',      // add new one
// ]));




// ============== ROUTES ==============
$basePath = '/Ticketmaestrix';

$app->get('/', function ($request, $response) use ($twig, $basePath) {
    $html = $twig->render('home.html.twig', [
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Route for signup page
$app->get('/signup', function ($request, $response) use ($twig, $basePath) {
    $html = $twig->render('signup.html.twig', [
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Route for login page
$app->get('/login', function ($request, $response) use ($twig, $basePath) {
    $html = $twig->render('login.html.twig', [
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Route for forgot password page
$app->get('/forgotpassword', function ($request, $response) use ($twig, $basePath) {
    $html = $twig->render('forgot_password_p1.html.twig', [
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Route for verification code page
$app->get('/verificationcode', function ($request, $response) use ($twig, $basePath) {
    $html = $twig->render('forgot_password_p2.html.twig', [
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Route for new password page
$app->get('/newpassword', function ($request, $response) use ($twig, $basePath) {
    $html = $twig->render('forgot_password_p3.html.twig', [
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});


$app->run();
