<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Support\ExternalBase;
use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

require_once dirname(__DIR__, 2) . '/Support/ExternalBase.php';

final class ExternalChildController extends ExternalBase
{
    #[Action]
    public function show(Request $request): Response
    {
        return Response::json(['action' => 'external-child-show']);
    }
}
