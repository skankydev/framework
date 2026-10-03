<?php

namespace SkankyTest\TestCase\Core;

use PHPUnit\Framework\TestCase;
use SkankyDev\Core\MasterFactory;
use SkankyDev\Exception\ClassNotFoundException;
use SkankyDev\Exception\UnknownMethodException;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\UrlBuilder;
use TestApp\Controller\TestController;

// Fixture : classe avec un paramètre de constructeur ayant une valeur par défaut.
class DefaultParamFixture {
    public function __construct(public string $label = 'default') {}
}

class MasterFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singletons HTTP pour que Request soit propre
        (new \ReflectionProperty(Router::class,     '_instance'))->setValue(null, null);
        (new \ReflectionProperty(Request::class,    '_instance'))->setValue(null, null);
        (new \ReflectionProperty(UrlBuilder::class, '_instance'))->setValue(null, null);
        (new \ReflectionProperty(MasterFactory::class, '_instance'))->setValue(null, null);

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/test/index',
            'REMOTE_ADDR'    => '127.0.0.1',
        ];
        $_GET = $_POST = $_COOKIE = $_FILES = [];

        Router::_findCurrentRoute('/test/index');
    }

    // ── make() ────────────────────────────────────────────────────────────────

    public function testMakeInstantiatesSimpleClass(): void
    {
        $obj = MasterFactory::_make(TestController::class);
        $this->assertInstanceOf(TestController::class, $obj);
    }

    public function testMakeReturnsSingletonInstance(): void
    {
        // Request utilise le trait Singleton directement
        $a = MasterFactory::_make(Request::class);
        $b = MasterFactory::_make(Request::class);
        $this->assertSame($a, $b);
        $this->assertInstanceOf(Request::class, $a);
    }

    public function testMakeThrowsForUnknownClass(): void
    {
        $this->expectException(ClassNotFoundException::class);
        MasterFactory::_make('App\\Controller\\DoesNotExist');
    }

    public function testMakeThrowsForAbstractClass(): void
    {
        $this->expectException(ClassNotFoundException::class);
        MasterFactory::_make(\SkankyDev\Queue\Job\MasterJob::class);
    }

    public function testMakeResolvesClassDependency(): void
    {
        // TestController dépend de rien dans son constructeur
        // mais ses méthodes dépendent de Request — testé dans call()
        $ctrl = MasterFactory::_make(TestController::class);
        $this->assertInstanceOf(TestController::class, $ctrl);
    }

    // ── call() ────────────────────────────────────────────────────────────────

    public function testCallInvokesMethodWithNoDependencies(): void
    {
        $ctrl   = new TestController();
        $result = MasterFactory::_call($ctrl, 'noArgs');
        $this->assertEquals('no args', $result);
    }

    public function testCallResolvesRequestDependency(): void
    {
        $ctrl   = new TestController();
        $result = MasterFactory::_call($ctrl, 'index');
        $this->assertEquals('from index', $result);
    }

    public function testCallPassesStringParameter(): void
    {
        $ctrl   = new TestController();
        $result = MasterFactory::_call($ctrl, 'greet', ['name' => 'Simon']);
        $this->assertEquals('hello Simon', $result);
    }

    public function testCallUsesDefaultParameterValue(): void
    {
        $ctrl   = new TestController();
        $result = MasterFactory::_call($ctrl, 'greet');
        $this->assertEquals('hello world', $result);
    }

    public function testCallPositionalArgumentOverridesDefault(): void
    {
        // Un argument positionnel explicite doit gagner sur la valeur par défaut
        $ctrl   = new TestController();
        $result = MasterFactory::_call($ctrl, 'greet', ['Bob']);
        $this->assertEquals('hello Bob', $result);
    }

    // ── arguments explicites vs valeur par défaut (constructeur) ────────────────

    public function testMakePositionalArgumentOverridesConstructorDefault(): void
    {
        $obj = MasterFactory::_make(DefaultParamFixture::class, ['explicit']);
        $this->assertEquals('explicit', $obj->label);
    }

    public function testMakeUsesConstructorDefaultWhenNoArgument(): void
    {
        $obj = MasterFactory::_make(DefaultParamFixture::class, []);
        $this->assertEquals('default', $obj->label);
    }

    public function testCallThrowsForUnknownMethod(): void
    {
        $ctrl = new TestController();
        $this->expectException(UnknownMethodException::class);
        MasterFactory::_call($ctrl, 'methodThatDoesNotExist');
    }
}
