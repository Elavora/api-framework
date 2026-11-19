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
     * Regras nomeadas suportadas: int, string, float, numeric, bool,
     * array, object, null, email, url, base64, json e uuid (e os apelidos integer e boolean).
     *
     * Por padrao `int` e `bool` sao estritas (`is_int`, `is_bool`), o que serve ao corpo JSON, onde o tipo ja vem
     * correto. Com `$fromText = true` (valor vindo da query string, que chega sempre como texto), `int` e `bool`
     * tambem aceitam o texto equivalente, veja `isIntLike` e `isBoolLike`. As demais regras nao mudam. A validacao
     * nunca converte o valor.
     *
     * @param mixed $rule Nome da regra, classe DataType, instancia DataType ou callable.
     * @param bool $fromText Quando true, `int` e `bool` aceitam tambem o texto (uso em parametros de query).
     */
    public static function validate(mixed $value, mixed $rule, bool $fromText = false): bool
    {
        if (is_string($rule) && enum_exists($rule) === false && class_exists($rule) === false) {
            $name = strtolower($rule);

            if ($fromText) {
                if ($name === 'int' || $name === 'integer') {
                    return self::isIntLike($value);
                }

                if ($name === 'bool' || $name === 'boolean') {
                    return self::isBoolLike($value);
                }
            }

            return self::validateNamedRule($value, $name);
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

    /**
     * Inteiro de verdade ou texto de inteiro: sinal opcional (`+`/`-`) e digitos, com ou sem zeros a esquerda
     * (`'007'`, `'00'`, `'-007'`, `'+0'` passam). Recusa vazio, espacos, decimais, notacao cientifica, hexadecimal,
     * estouro do limite de `int` (zeros a esquerda nao contam), bool e float.
     */
    private static function isIntLike(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (!is_string($value) || preg_match('/^([+-]?)([0-9]+)$/D', $value, $parts) !== 1) {
            return false;
        }

        $digits = ltrim($parts[2], '0');
        $normalized = ($parts[1] === '-' ? '-' : '') . ($digits === '' ? '0' : $digits);

        return filter_var($normalized, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Bool do PHP, os inteiros 0 e 1 ou os textos `0`, `1`, `true` e `false` (sem diferenciar maiusculas, sem espacos
     * nas pontas). Nada de `yes`, `no`, `on` ou `off`.
     */
    private static function isBoolLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value)) {
            return $value === 0 || $value === 1;
        }

        return is_string($value) && in_array(strtolower($value), ['0', '1', 'true', 'false'], true);
    }

    private static function isValidJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
