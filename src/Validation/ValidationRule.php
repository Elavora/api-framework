<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Validation;

use Elavora\Api\Framework\Contracts\DataType;
use Elavora\Api\Framework\Contracts\DescribesType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Valida valores usados pelos attributes de request.
 *
 * Aceita regras nomeadas, DataTypes ou callables.
 */
final class ValidationRule
{
    /**
     * Verifica se um valor atende a uma regra.
     *
     * Regras nomeadas suportadas: int, int-string, string, float, numeric, bool,
     * array, object, null, email, url, base64, json e uuid.
     *
     * @param mixed $rule Nome da regra, classe DataType, instancia DataType ou callable.
     */
    public static function validate(mixed $value, mixed $rule): bool
    {
        if (is_string($rule) && enum_exists($rule) === false && class_exists($rule) === false) {
            return self::validateNamedRule($value, strtolower($rule));
        }

        if (is_string($rule) && is_subclass_of($rule, DataType::class)) {
            return $rule::isValid($value);
        }

        if ($rule instanceof DataType) {
            return $rule::isValid($value);
        }

        if (is_callable($rule)) {
            return (bool) $rule($value);
        }

        return false;
    }

    /**
     * Descreve uma regra em formato legivel para metadados de endpoint.
     *
     * O resultado e pensado para quem consome a API, entao nunca e o nome completo de uma classe PHP:
     *
     * - regra nomeada (`int`, `email`, ...): `['type' => 'int']`;
     * - DataType: o tipo que declara (`DescribesType`) ou o primitivo do retorno de `value()`, ou `mixed`;
     * - outra classe: o nome curto, sem namespace.
     *
     * O resultado e sempre um array com `type` e, se o tipo declarar, `format`, `mask`, `pattern`, `enum`, `example` e
     * `description` (chaves fixas de `DescribesType`).
     *
     * @return array<string, mixed>
     */
    public static function describe(mixed $rule): array
    {
        if (is_string($rule)) {
            return class_exists($rule) || enum_exists($rule) ? self::describeClass($rule) : ['type' => $rule];
        }

        if (is_object($rule)) {
            return self::describeClass($rule::class);
        }

        return ['type' => get_debug_type($rule)];
    }

    /**
     * @param class-string $class
     * @return array<string, mixed>
     */
    private static function describeClass(string $class): array
    {
        if (is_a($class, DescribesType::class, true)) {
            return self::normalizeDocumentedType($class::documentedType());
        }

        if (is_a($class, DataType::class, true)) {
            return ['type' => self::valueType($class)];
        }

        return ['type' => (new ReflectionClass($class))->getShortName()];
    }

    /**
     * Mantem so as chaves fixas do contrato (`type`, `format`, `mask`, `pattern`, `enum`, `example`,
     * `description`) e descarta as vazias, para um tipo nao vazar mais do que deve.
     *
     * @param array<array-key, mixed> $documented
     * @return array<string, mixed>
     */
    private static function normalizeDocumentedType(array $documented): array
    {
        $type = isset($documented['type']) && is_string($documented['type']) && $documented['type'] !== ''
            ? $documented['type']
            : 'mixed';
        $normalized = ['type' => $type];

        foreach (['format', 'mask', 'pattern', 'description'] as $key) {
            if (isset($documented[$key]) && is_string($documented[$key]) && $documented[$key] !== '') {
                $normalized[$key] = $documented[$key];
            }
        }

        if (isset($documented['enum']) && is_array($documented['enum'])) {
            $options = array_values(array_filter($documented['enum'], is_scalar(...)));
            if ($options !== []) {
                $normalized['enum'] = $options;
            }
        }

        if (isset($documented['example']) && (is_scalar($documented['example']) || is_array($documented['example']))) {
            $normalized['example'] = $documented['example'];
        }

        return $normalized;
    }

    /**
     * Primitivo declarado no retorno de `value()`. Uniao de tipos, `mixed` ou ausencia de tipo viram `mixed`.
     */
    private static function valueType(string $class): string
    {
        $type = (new ReflectionMethod($class, 'value'))->getReturnType();

        if ($type instanceof ReflectionNamedType && $type->isBuiltin() && $type->getName() !== 'void') {
            return $type->getName();
        }

        return 'mixed';
    }

    private static function validateNamedRule(mixed $value, string $rule): bool
    {
        return match ($rule) {
            'int', 'integer' => is_int($value),
            'int-string', 'integer-string' => is_string($value) && ctype_digit($value),
            'string' => is_string($value),
            'float' => is_float($value),
            'numeric' => is_numeric($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => $value === null,
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'base64' => is_string($value) && base64_decode($value, true) !== false,
            'json' => is_string($value) && self::isValidJson($value),
            'uuid' => is_string($value)
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            default => false,
        };
    }

    private static function isValidJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
