<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Default headers applied to every response.
     * Can be overridden or extended by passing $extra to the constructor.
     */
    private array $headers;

    public function __construct(array $extra = [])
    {
        $this->headers = array_merge([
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'          => '1; mode=block',
        ], $extra);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Let the rest of the app handle the request and build a response
        $response = $handler->handle($request);

        // Attach each security header to the response
        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}