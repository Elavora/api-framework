<?php

declare(strict_types=1);

use App\Extensions\InMemoryExtension;
use App\Http\Controller\TaskController;
use App\Http\Middleware\ApiVersionHeader;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Response;

$app = Application::create();

$app->extend(new InMemoryExtension());
$app->middleware(new ApiVersionHeader());

$app->get('/health', static fn(): Response => Response::json(['status' => 'ok']));
$app->post('/tasks/archive', [TaskController::class, 'archive']);

return $app;
