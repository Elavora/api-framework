<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Details;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

class SecuredControllerParent
{
    #[Action]
    public function inherited(Request $request): Response
    {
        return Response::json(['action' => 'inherited']);
    }
}

final class SecuredController extends SecuredControllerParent
{
    public static int $executions = 0;
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    #[Action]
    #[Method('GET')]
    public function exposed(Request $request): Response
    {
        return Response::json(['action' => 'exposed']);
    }

    public function helper(Request $request): Response
    {
        return Response::json(['action' => 'helper']);
    }

    #[Action]
    public static function staticAction(Request $request): Response
    {
        return Response::json(['action' => 'static']);
    }

    #[Action]
    #[Method('POST')]
    #[Details(['summary' => 'Executa operacao segura'])]
    public function mutating(Request $request): Response
    {
        self::$executions++;

        return Response::json(['executed' => true]);
    }

    #[Action]
    protected function protectedAction(Request $request): Response
    {
        return Response::json(['action' => 'protected']);
    }
}
