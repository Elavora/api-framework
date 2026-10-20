<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

#[Attribute(Attribute::TARGET_METHOD)]
final class Permission implements BeforeRequestAttribute
{
    public function __construct(private readonly string $required) {}

    public function before(Request $request, Container $container): ?Response
    {
        if ($request->header('X-App-Permission') === $this->required) {
            return null;
        }

        return Response::json(['message' => 'Permissão insuficiente.'], 403);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return ['permission' => $this->required];
    }
}
