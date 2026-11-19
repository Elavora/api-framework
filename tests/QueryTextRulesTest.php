<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Attributes\OptionalFields;
use Elavora\Api\Framework\Attributes\OptionalParams;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\RequiredParams;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Validation\ValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryTextRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function validInts(): iterable
    {
        yield 'int' => [10];
        yield 'int zero' => [0];
        yield 'int negativo' => [-5];
        yield 'texto' => ['10'];
        yield 'texto zero' => ['0'];
        yield 'texto negativo' => ['-5'];
        yield 'texto com mais' => ['+1'];
        yield 'mais zero' => ['+0'];
        yield 'menos zero' => ['-0'];
        yield 'maior int' => [(string) PHP_INT_MAX];
        yield 'menor int' => [(string) PHP_INT_MIN];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidInts(): iterable
    {
        yield 'vazio' => [''];
        yield 'decimal' => ['1.0'];
        yield 'virgula' => ['1,5'];
        yield 'espaco antes' => [' 1'];
        yield 'espaco depois' => ['1 '];
        yield 'quebra de linha no fim' => ["1\n"];
        yield 'notacao cientifica' => ['1e3'];
        yield 'hexadecimal' => ['0x1A'];
        yield 'zeros a esquerda' => ['007'];
        yield 'zero duplo' => ['00'];
        yield 'sinal sozinho' => ['-'];
        yield 'dois sinais' => ['--1'];
        yield 'texto' => ['abc'];
        yield 'estoura o int' => ['9223372036854775808'];
        yield 'float' => [1.0];
        yield 'bool' => [true];
        yield 'null' => [null];
        yield 'array' => [['1']];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function validBools(): iterable
    {
        yield 'bool true' => [true];
        yield 'bool false' => [false];
        yield 'int 0' => [0];
        yield 'int 1' => [1];
        yield 'texto 0' => ['0'];
        yield 'texto 1' => ['1'];
        yield 'texto true' => ['true'];
        yield 'texto false' => ['false'];
        yield 'texto TRUE' => ['TRUE'];
        yield 'texto False' => ['False'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidBools(): iterable
    {
        yield 'yes' => ['yes'];
        yield 'no' => ['no'];
        yield 'on' => ['on'];
        yield 'off' => ['off'];
        yield 'vazio' => [''];
        yield 'dois' => ['2'];
        yield 'int 2' => [2];
        yield 'int negativo' => [-1];
        yield 'espaco antes' => [' true'];
        yield 'espaco depois' => ['false '];
        yield 'texto 01' => ['01'];
        yield 'float' => [1.0];
        yield 'null' => [null];
        yield 'array' => [['true']];
    }

    #[DataProvider('validInts')]
    public function testIntFromTextAccepts(mixed $value): void
    {
        self::assertTrue(ValidationRule::validate($value, 'int', fromText: true));
        self::assertTrue(ValidationRule::validate($value, 'integer', fromText: true));
        self::assertTrue(ValidationRule::validate($value, 'INT', fromText: true));
    }

    #[DataProvider('invalidInts')]
    public function testIntFromTextRejects(mixed $value): void
    {
        self::assertFalse(ValidationRule::validate($value, 'int', fromText: true));
    }

    #[DataProvider('validBools')]
    public function testBoolFromTextAccepts(mixed $value): void
    {
        self::assertTrue(ValidationRule::validate($value, 'bool', fromText: true));
        self::assertTrue(ValidationRule::validate($value, 'boolean', fromText: true));
    }

    #[DataProvider('invalidBools')]
    public function testBoolFromTextRejects(mixed $value): void
    {
        self::assertFalse(ValidationRule::validate($value, 'bool', fromText: true));
    }

    public function testStrictModeIsTheDefault(): void
    {
        self::assertTrue(ValidationRule::validate(10, 'int'));
        self::assertFalse(ValidationRule::validate('10', 'int'));
        self::assertTrue(ValidationRule::validate(true, 'bool'));
        self::assertFalse(ValidationRule::validate('true', 'bool'));
        self::assertFalse(ValidationRule::validate(1, 'bool'));
    }

    #[DataProvider('validInts')]
    public function testRemovedIntStringRuleIsNowUnknownAndRejected(mixed $value): void
    {
        self::assertFalse(ValidationRule::validate($value, 'int-string'));
        self::assertFalse(ValidationRule::validate($value, 'int-string', fromText: true));
        self::assertFalse(ValidationRule::validate($value, 'integer-string', fromText: true));
    }

    public function testOtherRulesIgnoreTheTextFlag(): void
    {
        self::assertTrue(ValidationRule::validate('10', 'string', fromText: true));
        self::assertFalse(ValidationRule::validate('1.5', 'float', fromText: true));
        self::assertTrue(ValidationRule::validate('1.5', 'numeric', fromText: true));
    }

    public function testQueryParamsAcceptTextAndKeepTheValue(): void
    {
        $request = new Request(method: 'GET', path: '/', query: ['limit' => '10', 'active' => 'TRUE']);

        self::assertNull((new OptionalParams(['limit' => 'int', 'active' => 'bool']))->validate($request));
        self::assertNull((new RequiredParams(['limit' => 'int', 'active' => 'bool']))->validate($request));
        self::assertSame('10', $request->query('limit'));
        self::assertSame('TRUE', $request->query('active'));
    }

    public function testQueryParamsRejectInvalidText(): void
    {
        $request = new Request(method: 'GET', path: '/', query: ['limit' => '1.5', 'active' => 'yes']);

        self::assertSame(400, (new OptionalParams(['limit' => 'int']))->validate($request)?->status());
        self::assertSame(400, (new OptionalParams(['active' => 'bool']))->validate($request)?->status());
        self::assertSame(400, (new RequiredParams(['limit' => 'int']))->validate($request)?->status());
        self::assertSame(400, (new RequiredParams(['active' => 'bool']))->validate($request)?->status());
    }

    public function testJsonBodyStaysStrict(): void
    {
        $request = new Request(method: 'POST', path: '/', body: ['limit' => '10', 'active' => 'true', 'flag' => 1]);

        self::assertNotNull((new RequiredFields(['limit' => 'int']))->validate($request));
        self::assertNotNull((new RequiredFields(['active' => 'bool']))->validate($request));
        self::assertNotNull((new RequiredFields(['flag' => 'bool']))->validate($request));
        self::assertNotNull((new OptionalFields(['limit' => 'int']))->validate($request));
        self::assertNotNull((new OptionalFields(['active' => 'bool']))->validate($request));

        $typed = new Request(method: 'POST', path: '/', body: ['limit' => 10, 'active' => true]);
        self::assertNull((new RequiredFields(['limit' => 'int', 'active' => 'bool']))->validate($typed));
        self::assertNull((new OptionalFields(['limit' => 'int', 'active' => 'bool']))->validate($typed));
    }

    public function testDescriptionIsUnchanged(): void
    {
        self::assertSame(['type' => 'int'], ValidationRule::describe('int'));
        self::assertSame(['type' => 'bool'], ValidationRule::describe('bool'));
    }
}
