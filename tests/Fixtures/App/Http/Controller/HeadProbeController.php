<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class HeadProbeController
{
    public static int $executions = 0;

    #[Action]
    #[Method('GET')]
    public function index(Request $request): Response
    {
        self::$executions++;

        return Response::json(['tasks' => [1, 2]], status: 200)->withHeader('X-Resource', 'task');
    }

    #[Action]
    #[Method('GET', 'HEAD')]
    public function explicit(Request $request): Response
    {
        return Response::json(['action' => 'explicit'])->withHeader('X-Resource', 'explicit');
    }

    #[Action]
    #[Method('POST')]
    public function store(Request $request): Response
    {
        return Response::json(['stored' => true], status: 201);
    }
}
