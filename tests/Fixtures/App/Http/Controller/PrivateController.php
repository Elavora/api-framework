<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class PrivateController
{
    public static int $instances = 0;

    private function __construct()
    {
        self::$instances++;
    }

    #[Action]
    public function show(Request $request): Response
    {
        return Response::json(['action' => 'private-show']);
    }
}
