<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

abstract class BaseController
{
    #[Action]
    public function show(Request $request): Response
    {
        return Response::json(['action' => 'base-show']);
    }

    #[Action]
    public function inherited(Request $request): Response
    {
        return Response::json(['action' => 'inherited']);
    }
}
