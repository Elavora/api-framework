<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class HealthController
{
    public function index(Request $request): Response
    {
        return Response::json(['action' => 'index']);
    }

    public function show(Request $request): Response
    {
        return Response::json(['action' => 'show']);
    }

    private function internal(Request $request): Response
    {
        return Response::json(['action' => 'internal']);
    }
}

