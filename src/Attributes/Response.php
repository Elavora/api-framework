<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Attributes;

use Attribute;
use Elavora\Api\Framework\Contracts\DescribesResponse;
use Elavora\Api\Framework\Contracts\HttpAttribute;
use Elavora\Api\Framework\Validation\ValidationRule;
use InvalidArgumentException;
use LogicException;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Descreve o formato esperado da resposta de uma action HTTP.
 *
 * O schema e livre para permitir documentacao simples ou integracao futura
 * com geradores de contrato. Ele pode ser:
 *
 * - um array: #[Response(['status' => 200, 'body' => ['id' => 'int']])]
 * - uma classe que implementa DescribesResponse: #[Response(Usuario::class)]
 *
 * Em qualquer nivel do array, o nome de uma classe que implementa DescribesResponse e substituido pelo
 * schema dela: #[Response(['status' => 201, 'data' => Usuario::class])]. Uma lista de itens fica como
 * `[Usuario::class]`. Qualquer outro nome de classe vira o tipo que a API mostra: um DataType aparece como o
 * primitivo que ele substitui (`string`, `int`), e uma classe comum pelo nome curto. O nome completo de uma
 * classe PHP nunca sai na resposta de OPTIONS.
 */
final class Response implements HttpAttribute
{
    private const MAX_DEPTH = 16;

    /**
     * @param array<string, mixed>|string $schema Schema, ou o nome da classe que o descreve (DescribesResponse).
     */
    public function __construct(private readonly array|string $schema) {}

    /**
     * @return array{response: array<array-key, mixed>} Metadados da resposta, com as classes ja expandidas.
     *
     * @throws InvalidArgumentException Se a classe informada nao implementa DescribesResponse.
     * @throws LogicException Se o schema referencia a si mesmo ou e profundo demais.
     */
    public function options(): array
    {
        $schema = $this->schema;

        if (is_string($schema)) {
            if (!is_a($schema, DescribesResponse::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    '#[Response] com uma classe exige que %s implemente %s.',
                    $schema,
                    DescribesResponse::class,
                ));
            }

            $schema = $schema::responseSchema();
        }

        return ['response' => self::expandSchema($schema, 0)];
    }

    /**
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>
     */
    private static function expandSchema(array $schema, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new LogicException('O schema da resposta referencia a si mesmo ou e profundo demais.');
        }

        $expanded = [];
        foreach ($schema as $key => $value) {
            $expanded[$key] = self::expandValue($value, $depth);
        }

        return $expanded;
    }

    private static function expandValue(mixed $value, int $depth): mixed
    {
        if (is_string($value) && is_a($value, DescribesResponse::class, true)) {
            return self::expandSchema($value::responseSchema(), $depth + 1);
        }

        if (is_string($value) && (class_exists($value) || enum_exists($value))) {
            return ValidationRule::describe($value);
        }

        if (is_array($value)) {
            return self::expandSchema($value, $depth + 1);
        }

        return $value;
    }
}
