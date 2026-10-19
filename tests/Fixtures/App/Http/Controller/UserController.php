<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/ExportActions.php';

final class UserController extends BaseController
{
    use ExportActions;

    public static int $instances = 0;
    public static int $executions = 0;

    public function __construct()
    {
        self::$instances++;
    }

    #[Action]
    public function show(Request $request): Response
    {
        self::$executions++;

        return Response::json(['id' => 1]);
    }

    #[Action]
    public function listActive(Request $request): Response
    {
        self::$executions++;

        return Response::json([]);
    }

    /** Segmento com exatamente 64 caracteres (limite da regra do path). */
    #[Action]
    public function abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ab(Request $request): Response
    {
        self::$executions++;

        return Response::json(['action' => 'long']);
    }

    public function helper(Request $request): Response
    {
        self::$executions++;

        return Response::json(['action' => 'helper']);
    }

    #[Action]
    public static function build(Request $request): Response
    {
        self::$executions++;

        return Response::json(['action' => 'build']);
    }

    #[Action]
    public function __invoke(Request $request): Response
    {
        self::$executions++;

        return Response::json(['action' => 'invoke']);
    }

    #[Action]
    protected function audit(Request $request): Response
    {
        self::$executions++;

        return Response::json(['action' => 'audit']);
    }

    #[Action]
    private function secret(Request $request): Response
    {
        self::$executions++;

        return Response::json(['action' => 'secret']);
    }
}
