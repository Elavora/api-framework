<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class ApiVersionHeader
{
    /**
     * @param callable(Request): Response $next
     */
    public function __invoke(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Api-Version', '1');
    }
}
