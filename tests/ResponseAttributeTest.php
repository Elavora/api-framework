<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Attributes\Details;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\Response as ResponseDoc;
use Elavora\Api\Framework\Contracts\DataType;
use Elavora\Api\Framework\Contracts\DescribesResponse;
use Elavora\Api\Framework\Contracts\Responseable;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * #[Response] aceita um array ou uma classe que implementa DescribesResponse, e expande as classes que
 * encontra em qualquer nivel do schema. Veja docs/DOCUMENTACAO-VIA-OPTIONS.md.
 */
final class ResponseAttributeTest extends TestCase
{
    /** O schema de DocumentedUser depois de expandido: o DataType aparece como o primitivo dele. */
    private const USER_SCHEMA = [
        'id' => 'uuid',
        'name' => 'string',
        'email' => ['type' => 'string'],
        'organization_id' => 'uuid|null',
    ];

    public function testArraySchemaIsExposedAsIs(): void
    {
        $options = (new ResponseDoc(['status' => 200, 'body' => ['id' => 'int']]))->options();

        self::assertSame(['response' => ['status' => 200, 'body' => ['id' => 'int']]], $options);
    }

    public function testClassSchemaComesFromTheClass(): void
    {
        $options = (new ResponseDoc(DocumentedUser::class))->options();

        self::assertSame(['response' => self::USER_SCHEMA], $options);
    }

    public function testClassInsideTheSchemaIsExpanded(): void
    {
        $options = (new ResponseDoc(['status' => 201, 'message' => 'string', 'data' => DocumentedUser::class]))->options();

        self::assertSame(
            ['response' => ['status' => 201, 'message' => 'string', 'data' => self::USER_SCHEMA]],
            $options,
        );
    }

    public function testClassInsideAListIsExpanded(): void
    {
        $options = (new ResponseDoc(['status' => 200, 'data' => [DocumentedUser::class]]))->options();

        self::assertSame(['response' => ['status' => 200, 'data' => [self::USER_SCHEMA]]], $options);
    }

    public function testClassesInsideADescribedClassAreExpanded(): void
    {
        $options = (new ResponseDoc(DocumentedOrder::class))->options();

        self::assertSame(['response' => ['id' => 'uuid', 'buyer' => self::USER_SCHEMA]], $options);
    }

    public function testDataTypeAppearsAsItsPrimitiveAndOtherClassesByShortName(): void
    {
        $options = (new ResponseDoc(['code' => DocumentedEmail::class, 'other' => stdClass::class]))->options();

        self::assertSame(['response' => ['code' => ['type' => 'string'], 'other' => ['type' => 'stdClass']]], $options);
    }

    public function testClassWithoutTheContractIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(DescribesResponse::class);

        (new ResponseDoc(DocumentedEmail::class))->options();
    }

    public function testCircularSchemaIsRejected(): void
    {
        $this->expectException(LogicException::class);

        (new ResponseDoc(DocumentedLoop::class))->options();
    }

    public function testOptionsRequestExposesTheExpandedSchemaAndTheDetails(): void
    {
        $application = Application::create();
        $application->get('/users', [DocumentedUserController::class, 'show']);

        $response = $application->handle(new Request(method: 'OPTIONS', path: '/users'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'attributes' => [
                    'methods' => ['GET', 'HEAD'],
                    'summary' => 'Mostra um usuario',
                    'tags' => ['users'],
                    'response' => ['status' => 200, 'data' => self::USER_SCHEMA],
                ],
            ]),
            $response->body(),
        );
        self::assertStringNotContainsString('Elavora', $response->body());
    }
}

final readonly class DocumentedEmail implements DataType
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

final class DocumentedUser implements Responseable, DescribesResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function responseSchema(): array
    {
        return [
            'id' => 'uuid',
            'name' => 'string',
            'email' => DocumentedEmail::class,
            'organization_id' => 'uuid|null',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [];
    }
}

final class DocumentedOrder implements DescribesResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function responseSchema(): array
    {
        return ['id' => 'uuid', 'buyer' => DocumentedUser::class];
    }
}

final class DocumentedLoop implements DescribesResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function responseSchema(): array
    {
        return ['next' => self::class];
    }
}

final class DocumentedUserController
{
    #[Method('GET')]
    #[Details(['summary' => 'Mostra um usuario', 'tags' => ['users']])]
    #[ResponseDoc(['status' => 200, 'data' => DocumentedUser::class])]
    public function show(): Response
    {
        return Response::json(payload: []);
    }
}
