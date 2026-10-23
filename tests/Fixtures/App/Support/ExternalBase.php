<?php

declare(strict_types=1);

namespace App\Support;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

/** Classe pai fora do namespace dos controllers. */
class ExternalBase
{
    #[Action]
    public function external(Request $request): Response
    {
        return Response::json(['action' => 'external']);
    }
}
