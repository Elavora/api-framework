<?php

declare(strict_types=1);

namespace App\Support;

use Elavora\Api\Framework\Contracts\CacheStore;

final class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, array{value: mixed, expiresAt: ?int}> */
    private array $items = [];

    public function get(string $key): mixed
    {
        $item = $this->items[$key] ?? null;
        if ($item === null) {
            return null;
        }

        if ($item['expiresAt'] !== null && $item['expiresAt'] <= time()) {
            unset($this->items[$key]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->items[$key] = [
            'value' => $value,
            'expiresAt' => $ttlSeconds === null ? null : time() + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
