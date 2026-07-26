<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Http;

/**
 * Envia uma Response para o runtime HTTP do PHP.
 */
final class ResponseEmitter
{
    /**
     * Define status, headers e imprime o corpo da resposta.
     */
    public function emit(Response $response): void
    {
        http_response_code($response->status());
        foreach ($response->headers() as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->allowsBody($response->status())) {
            echo $response->body();
        }
    }

    private function allowsBody(int $status): bool
    {
        return ($status < 100 || $status >= 200)
            && $status !== 204
            && $status !== 304;
    }
}


