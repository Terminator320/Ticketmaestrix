<?php
declare(strict_types=1);

// Suppress deprecation warnings for libraries not yet fully compatible with PHP 8.4+
error_reporting(E_ALL & ~E_DEPRECATED);

// Prevent warnings from being prepended to JSON/HTML output
ini_set('display_errors', '0');

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\EventController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
use App\Controllers\OrderItemController;
use App\Controllers\TicketController;
use App\Controllers\UserController;
use App\Controllers\VenueController;
use App\Helpers\Auth;
use App\Helpers\Cart;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Models\CategoryModel;
use App\Models\EventModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Models\VenueModel;
use App\Services\OtpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use RedBeanPHP\R;
use Slim\Factory\AppFactory;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;



require __DIR__ . '/vendor/autoload.php';



$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();



// ============== SESSION ==============
// Bootstraps PHP sessions so Auth + Cart helpers can read/write $_SESSION.
// Must run before any output and before the DI container builds controllers
// that may consult Auth during construction.
// cookie_lifetime=0 means the PHPSESSID cookie expires when the browser closes.
ini_set('session.cookie_lifetime', '0');
session_start();



// ============== DATABASE ==============
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

// Twig globals — exposed to every template so the navbar (and any other
// partial) can render auth-aware UI without each controller passing them.
$twig->addGlobal('current_user',    Auth::user());
$twig->addGlobal('is_admin',        Auth::isAdmin());
$twig->addGlobal('cart_count',      Cart::count());
// Unix timestamp so the JS can compute seconds-remaining without server drift.
$twig->addGlobal('cart_expires_at', (int) ($_SESSION['cart_expires_at'] ?? 0));



// ============== I18N — symfony/translation ================
// Reads locale from session; defaults to 'en'.
// trans('key') is available in every Twig template.

$translator = new Translator($_SESSION['lang'] ?? 'en');
$translator->addLoader('array', new ArrayLoader());
$translator->addResource('array', require __DIR__ . '/translations/messages.en.php', 'en');
$translator->addResource('array', require __DIR__ . '/translations/messages.fr.php', 'fr');

// Expose trans() to templates and inject the active locale on every render.
$twig->addFunction(new TwigFunction('trans', function (string $key, array $params = []) use ($translator) {
    $locale = $_SESSION['lang'] ?? 'en';
    return $translator->trans($key, $params, null, $locale);
}));
$twig->addGlobal('current_locale', $_SESSION['lang'] ?? 'en');



// ============== DEPENDENCY INJECTION CONTAINER ==============
//   PHP-DI container wires dependencies together.
//   Each controller receives Twig\Environment, its model(s), and the base path
//   through its constructor instead of pulling them from global scope.

$basePath = $_ENV['APP_BASE_PATH'] ?? '';


$container = new \DI\Container();
$container->set(Environment::class, $twig);

$container->set(HomeController::class, fn() => new HomeController(
    $twig,
    new EventModel(),
    new CategoryModel(),
    new TicketModel(),
    new VenueModel(),
    new OrderItemModel(),
    $basePath,
));

$container->set(CartController::class, fn() => new CartController(
    $twig,
    new TicketModel(),
    new EventModel(),
    new VenueModel(),
    new OrderModel(),
    new OrderItemModel(),
    $basePath,
));

$container->set(AuthController::class, fn() => new AuthController(
    $twig,
    new UserModel(),
    new OtpService(new UserModel()),
    $basePath,
));

$container->set(UserController::class, fn() => new UserController(
    $twig,
    new UserModel(),
    new TicketModel(),
    new OrderModel(),
    $basePath,
));

$container->set(AdminController::class, fn() => new AdminController(
    $twig,
    new UserModel(),
    new EventModel(),
    new OrderModel(),
    new OrderItemModel(),
    new CategoryModel(),
    new VenueModel(),
    new TicketModel(),
    $basePath,
));

$container->set(CategoryController::class, fn() => new CategoryController(
    $twig,
    new CategoryModel(),
    $basePath,
));

$container->set(EventController::class, fn() => new EventController(
    $twig,
    new EventModel(),
    new CategoryModel(),
    new VenueModel(),
    $basePath,
));

$container->set(OrderController::class, fn() => new OrderController(
    $twig,
    new OrderModel(),
    $basePath,
));

$container->set(OrderItemController::class, fn() => new OrderItemController(
    $twig,
    new OrderItemModel(),
    $basePath,
));

$container->set(TicketController::class, fn() => new TicketController(
    $twig,
    new TicketModel(),
    new EventModel(),
    new VenueModel(),
    $basePath,
));

$container->set(VenueController::class, fn() => new VenueController(
    $twig,
    new VenueModel(),
    $basePath,
));


// ==============   APPLICATION ==============

AppFactory::setContainer($container);

$app = AppFactory::create();

$app->setBasePath($basePath);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Add error middleware so you get useful error pages instead of blank screens
// $app->addErrorMiddleware(true, true, true);

$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
$app->addErrorMiddleware($debug, true, true);

// ============== MIDDLEWARE ==============

$logFile = __DIR__ . '/var/app.log';

$loggerMiddleware = function (Request $request, RequestHandler $handler) use ($logFile) {
    $start  = microtime(true);
    $method = $request->getMethod();
    $path   = $request->getUri()->getPath();

    $response = $handler->handle($request);

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

$app->add(new MaintenanceMiddleware(
    flagFile:        __DIR__ . '/var/maintenance.flag',
    responseFactory: $app->getResponseFactory()
));

$app->add(new SecurityHeadersMiddleware());
$app->add($loggerMiddleware);


// ============== ROUTES ==============

// --- Home ---
$app->get('/',     [HomeController::class, 'index']);
$app->get('/cart', [HomeController::class, 'showCart']);
$app->get('/map',  [HomeController::class, 'showMap']);

// --- Auth ---
$app->group('', function ($group) {
    $group->get('/signup',           [AuthController::class, 'showSignup']);
    $group->post('/signup',          [AuthController::class, 'signup']);
    $group->get('/login',            [AuthController::class, 'showLogin']);
    $group->post('/login',           [AuthController::class, 'login']);
    $group->post('/logout',          [AuthController::class, 'logout']);
    $group->get('/forgotpassword',   [AuthController::class, 'showForgotPassword']);
    $group->get('/verificationcode', [AuthController::class, 'showVerificationCode']);
    $group->get('/newpassword',      [AuthController::class, 'showNewPassword']);
    $group->get('/2fa/setup',        [AuthController::class, 'show2faSetup']);
    $group->post('/2fa/setup',       [AuthController::class, 'verify2faSetup']);
    $group->get('/2fa/login',        [AuthController::class, 'show2faLogin']);
    $group->post('/2fa/login',       [AuthController::class, 'verify2faLogin']);
});

// --- Admin ---
$app->get('/admin', [AdminController::class, 'showAdminDashboard']);
$app->post('/admin/users/create', [AdminController::class, 'createAdmin']);
$app->post('/admin/users/{id}/edit', [AdminController::class, 'updateAdmin']);
$app->post('/admin/users/{id}/delete', [AdminController::class, 'deleteAdmin']);

// --- Users ---
$app->group('/users', function ($group) {
    $group->get('',               [UserController::class, 'index']);
    $group->post('',              [UserController::class, 'store']);
    $group->get('/{id}',         [UserController::class, 'viewDetails']);
    $group->post('/{id}',        [UserController::class, 'update']);
    $group->post('/{id}/role',   [UserController::class, 'roleToggle']);
    $group->post('/{id}/delete', [UserController::class, 'delete']);
});
$app->get('/profile',    [UserController::class, 'showProfile']);
$app->get('/editprofile',[UserController::class, 'editProfile']);
$app->post('/editprofile',[UserController::class, 'updateProfile']);

// --- Categories ---
$app->group('/categories', function ($group) {
    $group->get('',               [CategoryController::class, 'index']);
    $group->get('/create',        [CategoryController::class, 'create']);
    $group->post('',              [CategoryController::class, 'store']);
    $group->get('/{id}',         [CategoryController::class, 'viewDetails']);
    $group->get('/{id}/edit',    [CategoryController::class, 'edit']);
    $group->post('/{id}/update', [CategoryController::class, 'update']);
    $group->post('/{id}/delete', [CategoryController::class, 'destroy']);
});

// --- Events ---
$app->group('/events', function ($group) {
    $group->get('',                   [EventController::class, 'index']);
    $group->get('/create',            [EventController::class, 'create']);
    $group->post('',                  [EventController::class, 'store']);
    $group->get('/category/{id}',     [EventController::class, 'byCategory']);
    $group->get('/{id}',              [EventController::class, 'viewDetails']);
    $group->get('/{id}/edit',         [EventController::class, 'edit']);
    $group->post('/{id}/update',      [EventController::class, 'update']);
    $group->post('/{id}/delete',      [EventController::class, 'destroy']);
});

// --- Venues ---
$app->group('/venues', function ($group) {
    $group->get('',               [VenueController::class, 'index']);
    $group->get('/create',        [VenueController::class, 'create']);
    $group->post('',              [VenueController::class, 'store']);
    $group->get('/{id}',         [VenueController::class, 'viewDetails']);
    $group->get('/{id}/edit',    [VenueController::class, 'edit']);
    $group->post('/{id}/update', [VenueController::class, 'update']);
    $group->post('/{id}/delete', [VenueController::class, 'destroy']);
});

// --- Tickets ---
$app->group('/tickets', function ($group) {
    $group->get('',               [TicketController::class, 'index']);
    $group->get('/create',        [TicketController::class, 'create']);
    $group->post('',              [TicketController::class, 'store']);
    $group->get('/event/{id}',   [TicketController::class, 'byEvent']);
    $group->get('/{id}',         [TicketController::class, 'viewDetails']);
    $group->get('/{id}/edit',    [TicketController::class, 'edit']);
    $group->post('/{id}/update', [TicketController::class, 'update']);
    $group->post('/{id}/delete', [TicketController::class, 'destroy']);
});

// --- Orders ---
$app->group('/orders', function ($group) {
    $group->post('',              [OrderController::class, 'store']);
    $group->get('/user/{id}',    [OrderController::class, 'byUser']);
    $group->get('/{id}',         [OrderController::class, 'viewDetails']);
    $group->post('/{id}',        [OrderController::class, 'update']);
    $group->post('/{id}/delete', [OrderController::class, 'delete']);
});

// --- Order Items ---
$app->group('/order-items', function ($group) {
    $group->post('',              [OrderItemController::class, 'store']);
    $group->get('/order/{id}',   [OrderItemController::class, 'byOrder']);
    $group->get('/{id}',         [OrderItemController::class, 'viewDetails']);
    $group->post('/{id}',        [OrderItemController::class, 'update']);
    $group->post('/{id}/delete', [OrderItemController::class, 'delete']);
});

// --- Cart ---
$app->group('/cart', function ($group) {
    $group->post('/add',                  [CartController::class, 'add']);
    $group->post('/remove/{ticket_id}',   [CartController::class, 'remove']);
    $group->post('/clear',                [CartController::class, 'clear']);
    $group->post('/checkout',             [CartController::class, 'checkout']);
    $group->post('/expire',               [CartController::class, 'expire']);
});


// --- Language switcher ---
$app->get('/lang/{locale}', function (Request $request, Response $response, array $args) use ($basePath) {
    $allowed = ['en', 'fr'];
    // Store the chosen locale in session so it persists across requests.
    if (in_array($args['locale'], $allowed, true)) {
        $_SESSION['lang'] = $args['locale'];
    }
    return $response->withHeader('Location', $basePath . '/')->withStatus(302);
});



$app->run();
