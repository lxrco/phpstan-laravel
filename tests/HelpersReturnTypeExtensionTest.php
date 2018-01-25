<?php

namespace Tests\Weebly\PHPStan\Laravel;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ObjectType;
use Symfony\Component\HttpFoundation\Response;
use Weebly\PHPStan\Laravel\HelpersReturnTypeExtension;

class HelpersReturnTypeExtensionTest extends \PHPStan\Testing\TestCase
{
    /** @var HelpersReturnTypeExtension */
    private $extension;

    protected function setUp()
    {
        $this->extension = new HelpersReturnTypeExtension();
    }

    public function redirectDataProvider()
    {
        return [
            'No arguments' => [[], Redirector::class],
            '1 arguments' => [['/'], RedirectResponse::class],
            '2 arguments' => [['/', 301], RedirectResponse::class],
            '3 arguments' => [['/', 307, ['expires' => '2018-01-25 10:43:00']], RedirectResponse::class],
            '4 arguments' => [['/', 307, ['expires' => '2018-01-25 10:43:00'], true], RedirectResponse::class],
        ];
    }

    /** @dataProvider redirectDataProvider */
    public function testRedirectHelper($arguments, $expectedType)
    {
        $functionReflection = $this->createMock(FunctionReflection::class);
        $functionReflection->method('getName')
            ->willReturn('redirect');

        $this->assertTrue($this->extension->isFunctionSupported($functionReflection));

        $functionCall = new FuncCall(new Name('redirect'), $arguments, []);
        $scope = $this->createMock(Scope::class);

        $type = $this->extension->getTypeFromFunctionCall($functionReflection, $functionCall, $scope);
        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertEquals($expectedType, $type->getClassName());
    }

    public function responseDataProvider()
    {
        return [
            'No arguments' => [[], ResponseFactory::class],
            '1 arguments' => [['Hello World'], Response::class],
            '2 arguments' => [['Hello World', 204], Response::class],
            '3 arguments' => [['Hello World', 200, ['expires' => '2018-01-25 10:43:00']], Response::class],
        ];
    }

    /** @dataProvider responseDataProvider */
    public function testResponseHelper($arguments, $expectedType)
    {
        $functionReflection = $this->createMock(FunctionReflection::class);
        $functionReflection->method('getName')
            ->willReturn('response');

        $this->assertTrue($this->extension->isFunctionSupported($functionReflection));

        $functionCall = new FuncCall(new Name('response'), $arguments, []);
        $scope = $this->createMock(Scope::class);

        $type = $this->extension->getTypeFromFunctionCall($functionReflection, $functionCall, $scope);
        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertEquals($expectedType, $type->getClassName());
    }
}
