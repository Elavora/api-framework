<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\OptionalParams;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\RequiredParams;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class QueryProbeController
{
    #[Action]
    #[Method('QUERY')]
    #[RequiredFields(['filter' => 'string'])]
    #[RequiredParams(['page' => 'string'])]
    #[OptionalParams(['sort' => 'string'])]
    public function search(Request $request): Response
    {
        return Response::json(['filter' => $request->input('filter'), 'page' => $request->query('page')]);
    }

    #[Action]
    #[Method('GET', 'QUERY')]
    public function both(Request $request): Response
    {
        return Response::json(['method' => $request->method()]);
    }

    #[Action]
    public function open(Request $request): Response
    {
        return Response::json(['method' => $request->method()]);
    }
}
