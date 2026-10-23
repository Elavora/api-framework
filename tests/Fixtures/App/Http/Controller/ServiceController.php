<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

require_once __DIR__ . '/Mailer.php';

final class ServiceController
{
    public function __construct(private readonly Mailer $mailer) {}

    #[Action]
    public function show(Request $request): Response
    {
        return Response::json(['action' => 'service-show', 'from' => $this->mailer->from()]);
    }
}
