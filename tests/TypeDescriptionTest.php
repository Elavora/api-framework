<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\OptionalFields;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\RequiredParams;
use Elavora\Api\Framework\Contracts\DataType;
use Elavora\Api\Framework\Contracts\DescribesType;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Elavora\Api\Framework\Validation\ValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * ValidationRule::describe() e o que a API mostra em OPTIONS: nunca o nome completo de uma classe PHP.
 * Veja docs/DOCUMENTACAO-VIA-OPTIONS.md, "O tipo de um campo".
 */
final class TypeDescriptionTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function rules(): iterable
    {
        yield 'regra nomeada fica como esta' => ['int', ['type' => 'int']];
        yield 'regra nomeada de formato' => ['email', ['type' => 'email']];
        yield 'DataType de string vira string' => [TypeTextValue::class, ['type' => 'string']];
        yield 'DataType de int vira int' => [TypeCountValue::class, ['type' => 'int']];
        yield 'DataType com retorno anulavel vira o primitivo' => [TypeNullableValue::class, ['type' => 'string']];
        yield 'DataType com value mixed vira mixed' => [TypeAnyValue::class, ['type' => 'mixed']];
        yield 'DataType com uniao vira mixed' => [TypeUnionValue::class, ['type' => 'mixed']];
        yield 'DescribesType vence a reflexao' => [TypeDeclaredValue::class, ['type' => 'uuid']];
        yield 'classe comum vira o nome curto' => [stdClass::class, ['type' => 'stdClass']];
        yield 'instancia de DataType' => [new TypeCountValue(1), ['type' => 'int']];
        yield 'instancia de classe comum' => [new stdClass(), ['type' => 'stdClass']];
        yield 'valor que nao e regra' => [42, ['type' => 'int']];
    }

    #[DataProvider('rules')]
    public function testDescribe(mixed $rule, array $expected): void
    {
        self::assertSame($expected, ValidationRule::describe($rule));
    }

    public function testRichTypeKeepsOnlyKnownNonEmptyKeys(): void
    {
        self::assertSame(
            ['type' => 'string', 'mask' => '000.000.000-00', 'pattern' => '^[0-9]{11}$', 'example' => '12345678901'],
            ValidationRule::describe(TypeRichValue::class),
        );
    }

    public function testEnumKeepsOnlyScalarOptions(): void
    {
        self::assertSame(
            ['type' => 'string', 'description' => 'Situacao', 'enum' => ['pending', 'paid']],
            ValidationRule::describe(TypeOptionsValue::class),
        );
    }

    public function testTypeWithoutExtrasHasOnlyTheType(): void
    {
        self::assertSame(['type' => 'int'], ValidationRule::describe(TypeOnlyTypeValue::class));
    }

    public function testDescribeNeverReturnsANamespace(): void
    {
        foreach (self::rules() as [$rule]) {
            self::assertStringNotContainsString('\\', (string) json_encode(ValidationRule::describe($rule)));
        }
    }

    public function testOptionsShowsPrimitivesInsteadOfClassNames(): void
    {
        $application = Application::create();
        $application->route(method: 'POST', path: '/items', handler: [TypeDocumentedController::class, 'store']);

        $response = $application->handle(new Request(method: 'OPTIONS', path: '/items'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'attributes' => [
                    'methods' => ['POST'],
                    'params' => ['id' => ['type' => 'uuid']],
                    'fields' => ['name' => ['type' => 'string'], 'quantity' => ['type' => 'int']],
                    'optionalFields' => ['note' => ['type' => 'string']],
                ],
            ]),
            $response->body(),
        );
        self::assertStringNotContainsString('Elavora', $response->body());
    }
}

final readonly class TypeTextValue implements DataType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return is_string($value);
    }

    public function value(): string
    {
        return (string) $this->value;
    }
}

final readonly class TypeCountValue implements DataType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return is_int($value);
    }

    public function value(): int
    {
        return (int) $this->value;
    }
}

final readonly class TypeNullableValue implements DataType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return true;
    }

    public function value(): ?string
    {
        return $this->value === null ? null : (string) $this->value;
    }
}

final readonly class TypeAnyValue implements DataType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return true;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}

final readonly class TypeUnionValue implements DataType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return true;
    }

    public function value(): string|int
    {
        return is_int($this->value) ? $this->value : (string) $this->value;
    }
}

final readonly class TypeDeclaredValue implements DataType, DescribesType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return is_string($value);
    }

    public static function documentedType(): array
    {
        return ['type' => 'uuid'];
    }

    public function value(): string
    {
        return (string) $this->value;
    }
}

final class TypeDocumentedController
{
    #[Method('POST')]
    #[RequiredParams(['id' => TypeDeclaredValue::class])]
    #[RequiredFields(['name' => TypeTextValue::class, 'quantity' => TypeCountValue::class])]
    #[OptionalFields(['note' => TypeNullableValue::class])]
    public function store(): Response
    {
        return Response::json(payload: []);
    }
}

final readonly class TypeRichValue implements DataType, DescribesType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return is_string($value);
    }

    public static function documentedType(): array
    {
        return [
            'type' => 'string',
            'mask' => '000.000.000-00',
            'pattern' => '^[0-9]{11}$',
            'example' => '12345678901',
            'extra' => 'descartada',
            'format' => '',
            'enum' => [],
        ];
    }

    public function value(): string
    {
        return (string) $this->value;
    }
}

final readonly class TypeOptionsValue implements DataType, DescribesType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return in_array($value, ['pending', 'paid'], true);
    }

    public static function documentedType(): array
    {
        return ['type' => 'string', 'enum' => ['pending', 'paid', new stdClass()], 'description' => 'Situacao'];
    }

    public function value(): string
    {
        return (string) $this->value;
    }
}

final readonly class TypeOnlyTypeValue implements DataType, DescribesType
{
    public function __construct(private mixed $value) {}

    public static function isValid(mixed $value): bool
    {
        return true;
    }

    public static function documentedType(): array
    {
        return ['type' => 'int', 'mask' => ''];
    }

    public function value(): int
    {
        return (int) $this->value;
    }
}
