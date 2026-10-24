<?php

declare(strict_types=1);

namespace App\DataTypes;

use Elavora\Api\Framework\Contracts\DataType;
use Elavora\Api\Framework\Contracts\Responseable;
use InvalidArgumentException;

final readonly class ProjectCode implements DataType, Responseable
{
    private function __construct(private string $value) {}

    public static function from(mixed $value): static
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException('ProjectCode deve usar o formato APP-1234.');
        }

        return new self(strtoupper((string) $value));
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[a-z]+-[0-9]{4}$/i', $value) === 1;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
