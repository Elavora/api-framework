<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Attributes\OptionalFields;
use Elavora\Api\Framework\Attributes\OptionalParams;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\RequiredParams;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Elavora\Api\Framework\Validation\ValidationRule;
use PHPUnit\Framework\TestCase;

final class RequestInputValidationTest extends TestCase
{
    private array $server;
    private array $get;
    private array $post;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        $_POST = $this->post;
    }

    public function testMalformedJsonReturnsBadRequestWithoutExecutingAction(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users',
            'HTTP_X_REQUEST_ID' => 'req-malformed-json',
        ];
        $_GET = [];
        $_POST = [];

        $executions = 0;
        $application = Application::create();
        $application->post('/users', static function () use (&$executions): Response {
            $executions++;

            return Response::created();
        });

        $response = $application->handle(Request::fromGlobals('{"name":'));

        self::assertSame(400, $response->status());
        self::assertSame(0, $executions);
        self::assertSame('req-malformed-json', $response->headers()['X-Request-Id']);
        self::assertJsonStringEqualsJsonString(
            '{"message":"Malformed JSON body","request_id":"req-malformed-json"}',
            $response->body()
        );
    }

    public function testFromGlobalsAcceptsJsonObjectAndArray(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];
        $_GET = [];
        $_POST = [];

        self::assertSame(['name' => 'Elavora'], Request::fromGlobals('{"name":"Elavora"}')->input());
        self::assertSame(['one', 'two'], Request::fromGlobals('["one","two"]')->input());
    }

    public function testFromGlobalsKeepsFormAndEmptyBodiesValid(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];
        $_GET = [];
        $_POST = ['name' => 'Elavora'];

        self::assertSame(['name' => 'Elavora'], Request::fromGlobals('name=Elavora')->input());
        self::assertNull(Request::fromGlobals('')->bodyError());
    }

    public function testFromGlobalsRejectsJsonScalarAsUnsupportedTopLevelBody(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];
        $_GET = [];
        $_POST = [];

        self::assertSame(
            'JSON body must be an object or array',
            Request::fromGlobals('"value"')->bodyError()
        );
    }

    public function testAccessorsOnlyUseDefaultWhenKeyIsAbsent(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/',
            query: ['filter' => null],
            body: ['nickname' => null]
        );

        self::assertTrue($request->hasQuery('filter'));
        self::assertNull($request->query('filter', 'default'));
        self::assertSame('default', $request->query('missing', 'default'));
        self::assertTrue($request->hasInput('nickname'));
        self::assertNull($request->input('nickname', 'default'));
        self::assertSame('default', $request->input('missing', 'default'));
    }

    public function testRequiredAndOptionalValidatorsDistinguishAbsentFromNull(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/',
            query: ['required' => null, 'optional' => null],
            body: ['required' => null, 'optional' => null]
        );

        self::assertNull((new RequiredFields(['required' => 'null']))->validate($request));
        self::assertNull((new RequiredParams(['required' => 'null']))->validate($request));
        self::assertNull((new OptionalFields(['optional' => 'null']))->validate($request));
        self::assertNull((new OptionalParams(['optional' => 'null']))->validate($request));
        self::assertNotNull((new OptionalFields(['optional' => 'string']))->validate($request));
        self::assertNotNull((new OptionalParams(['optional' => 'string']))->validate($request));
        self::assertNotNull((new RequiredFields(['missing' => 'null']))->validate($request));
        self::assertNotNull((new RequiredParams(['missing' => 'null']))->validate($request));
    }

    public function testJsonRuleAcceptsLiteralNullAndRejectsMalformedJson(): void
    {
        self::assertTrue(ValidationRule::validate('null', 'json'));
        self::assertTrue(ValidationRule::validate('{"valid":true}', 'json'));
        self::assertFalse(ValidationRule::validate('{"invalid":', 'json'));
    }
}
