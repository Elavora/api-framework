<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Attributes\Permission;
use App\DataTypes\ProjectCode;
use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Cache;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\Transaction;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class TaskController
{
    #[Action]
    #[Method('GET')]
    #[Cache(60)]
    public function index(Request $request): Response
    {
        return Response::json([
            'tasks' => [
                ['code' => 'APP-0001', 'title' => 'Escrever a documentação'],
            ],
        ]);
    }

    #[Action]
    #[Method('POST')]
    #[RequiredFields(['code' => ProjectCode::class, 'title' => 'string'])]
    #[Permission('tasks.write')]
    #[Transaction]
    public function store(Request $request): Response
    {
        $code = ProjectCode::from($request->input('code'));

        return Response::created([
            'code' => $code,
            'title' => $request->input('title'),
        ]);
    }

    public function archive(Request $request): Response
    {
        return Response::json(['archived' => true]);
    }
}
