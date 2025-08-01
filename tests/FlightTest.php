<?php

declare(strict_types=1);

namespace tests;

use Exception;
use Flight;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use tests\classes\User;
use PHPUnit\Framework\TestCase;

class FlightTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
        Flight::init();
        Flight::setEngine(new Engine());
        Flight::set('flight.views.path', __DIR__ . '/views');
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
        Flight::clear();
    }

    // Checks that default components are loaded
    public function testDefaultComponents(): void
    {
        $request = Flight::request();
        $response = Flight::response();
        $router = Flight::router();
        $view = Flight::view();

        $this->assertEquals(Request::class, get_class($request));
        $this->assertEquals(Response::class, get_class($response));
        $this->assertEquals(Router::class, get_class($router));
        $this->assertEquals(View::class, get_class($view));
    }

    // Test get/set of variables
    public function testGetAndSet(): void
    {
        Flight::set('a', 1);
        $var = Flight::get('a');

        $this->assertEquals(1, $var);

        Flight::clear();
        $vars = Flight::get();

        $this->assertCount(0, $vars);

        Flight::set('a', 1);
        Flight::set('b', 2);
        $vars = Flight::get();

        $this->assertCount(2, $vars);
        $this->assertEquals(1, $vars['a']);
        $this->assertEquals(2, $vars['b']);
    }

    // Register a class
    public function testRegister(): void
    {
        Flight::path(__DIR__ . '/classes');

        Flight::register('user', User::class);
        $user = Flight::user();

        $loaders = spl_autoload_functions();

        self::assertTrue(count($loaders) > 0);
        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);

        Flight::unregister('user');

        self::expectException(Exception::class);
        self::expectExceptionMessage('user must be a mapped method.');
        $user = Flight::user();
    }

    // Map a function
    public function testMap(): void
    {
        Flight::map('map1', function () {
            return 'hello';
        });

        $result = Flight::map1();

        self::assertEquals('hello', $result);
    }

    // Unmapped method
    public function testUnmapped(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('doesNotExist must be a mapped method.');

        Flight::doesNotExist();
    }

    public function testStaticRoute(): void
    {
        Flight::route('/test', function () {
            echo 'test';
        });
        Flight::request()->url = '/test';

        $this->expectOutputString('test');
        Flight::start();
    }

    public function testStaticRouteGroup(): void
    {
        Flight::group('/group', function () {
            Flight::route('/test', function () {
                echo 'test';
            });
        });
        Flight::request()->url = '/group/test';

        $this->expectOutputString('test');
        Flight::start();
    }

    public function testStaticNestedGroups(): void
    {
        Flight::group('/', static function (): void {
            Flight::group('/', static function (): void {
                Flight::route('GET /', static function (): void {
                    echo "test";
                });
            });
        });

        Flight::request()->url = '/';

        $this->expectOutputString('test');
        Flight::start();
    }

    public function testStaticRouteGet(): void
    {

        // can't actually get "get" because that gets a variable
        Flight::route('GET /test', function () {
            echo 'test get';
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        Flight::request()->url = '/test';

        $this->expectOutputString('test get');
        Flight::start();
    }

    public function testStaticRoutePost(): void
    {

        Flight::post('/test', function () {
            echo 'test post';
        });

        $_SERVER['REQUEST_METHOD'] = 'POST';
        Flight::request()->url = '/test';

        $this->expectOutputString('test post');
        Flight::start();
    }

    public function testStaticRoutePut(): void
    {
        Flight::put('/test', function () {
            echo 'test put';
        });

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        Flight::request()->url = '/test';

        $this->expectOutputString('test put');
        Flight::start();
    }

    public function testStaticRoutePatch(): void
    {

        Flight::patch('/test', function () {
            echo 'test patch';
        });

        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        Flight::request()->url = '/test';

        $this->expectOutputString('test patch');
        Flight::start();
    }

    public function testStaticRouteDelete(): void
    {

        Flight::delete('/test', function () {
            echo 'test delete';
        });

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        Flight::request()->url = '/test';

        $this->expectOutputString('test delete');
        Flight::start();
    }

    public function testGetUrl(): void
    {
        Flight::route('/path1/@param:[a-zA-Z0-9]{2,3}', function () {
            echo 'I win';
        }, false, 'path1');
        $url = Flight::getUrl('path1', ['param' => 123]);
        $this->assertEquals('/path1/123', $url);
    }

    public function testRouteGetUrlWithGroupSimpleParams(): void
    {
        Flight::group('/path1/@id', function () {
            Flight::route('/@name', function () {
                echo 'whatever';
            }, false, 'path1');
        });
        $url = Flight::getUrl('path1', ['id' => 123, 'name' => 'abc']);

        $this->assertEquals('/path1/123/abc', $url);
    }

    public function testRouteGetUrlNestedGroups(): void
    {
        Flight::group('/user', function () {
            Flight::group('/all_users', function () {
                Flight::group('/check_user', function () {
                    Flight::group('/check_one', function () {
                        Flight::route("/normalpath", function () {
                            echo "normalpath";
                        }, false, "normalpathalias");
                    });
                });
            });
        });

        $url = Flight::getUrl('normalpathalias');

        $this->assertEquals('/user/all_users/check_user/check_one/normalpath', $url);
    }

    public function testHookOutputBuffering(): void
    {
        Flight::route('/test', function () {
            echo 'test';
        });

        Flight::before('start', function ($output) {
            echo 'hooked before start';
        });

        Flight::request()->url = '/test';

        $this->expectOutputString('hooked before starttest');
        Flight::start();
        $this->assertEquals('test', Flight::response()->getBody());
    }

    public function testHookOutputBufferingV2OutputBuffering(): void
    {
        Flight::route('/test', function () {
            echo 'test';
        });

        Flight::before('start', function ($output) {
            echo 'hooked before start';
        });

        Flight::set('flight.v2.output_buffering', true);
        Flight::request()->url = '/test';

        $this->expectOutputString('hooked before starttest');
        ob_start();
        Flight::start();
        $this->assertEquals('hooked before starttest', Flight::response()->getBody());
    }

    public function testStreamRoute(): void
    {
        $response_mock = new class extends Response {
            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): Response
            {
                return $this;
            }
        };
        $mock_response_class_name = get_class($response_mock);
        Flight::register('response', $mock_response_class_name);
        Flight::route('/stream', function () {
            echo 'stream';
        })->stream();
        Flight::request()->url = '/stream';
        $this->expectOutputString('stream');
        Flight::start();
        $this->assertEquals('', Flight::response()->getBody());
        $this->assertEquals([
            'X-Accel-Buffering' => 'no',
            'Connection' => 'close'
        ], Flight::response()->getHeaders());
        $this->assertEquals(200, Flight::response()->status());
    }

    public function testStreamRouteWithHeaders(): void
    {
        $response_mock = new class extends Response {
            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): Response
            {
                return $this;
            }
        };
        $mock_response_class_name = get_class($response_mock);
        Flight::register('response', $mock_response_class_name);
        Flight::route('/stream', function () {
            echo 'stream';
        })->streamWithHeaders(['Content-Type' => 'text/plain', 'X-Test' => 'test', 'status' => 200]);
        Flight::request()->url = '/stream';
        $this->expectOutputString('stream');
        Flight::start();
        $this->assertEquals('', Flight::response()->getBody());
        $this->assertEquals([
            'Content-Type' => 'text/plain',
            'X-Test' => 'test',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'close'
        ], Flight::response()->getHeaders());
        $this->assertEquals(200, Flight::response()->status());
    }

    public function testOverwriteBodyWithMiddleware(): void
    {
        $middleware = new class {
            public function after()
            {
                $response = Flight::response();
                $body = $response->getBody();
                $body = strip_tags($body);
                // remove spaces for fun
                $body = str_replace(' ', '', $body);
                $response->write($body, true);
                return $response;
            }
        };

        Flight::route('/route-with-html', function () {
            echo '<p>This is a route with html</p>';
        })->addMiddleware($middleware);

        Flight::request()->url = '/route-with-html';

        Flight::start();

        $this->expectOutputString('Thisisaroutewithhtml');
    }

    /** @dataProvider \tests\ViewTest::renderDataProvider */
    public function testDoesNotPreserveVarsWhenFlagIsDisabled(
        string $output,
        array $renderParams,
        string $regexp
    ): void {
        Flight::view()->preserveVars = false;

        $this->expectOutputString($output);
        Flight::render(...$renderParams);

        set_error_handler(function (int $code, string $message) use ($regexp): void {
            $this->assertMatchesRegularExpression($regexp, $message);
        });

        Flight::render($renderParams[0]);

        restore_error_handler();
    }

    public function testKeepThePreviousStateOfOneViewComponentByDefault(): void
    {
        $html = <<<'html'
        <div>Hi</div>
        <div>Hi</div>
        <input type="number" />
        <input type="number" />
        html; // phpcs:ignore

        $html = str_replace(["\n", "\r"], '', $html);

        $this->expectOutputString($html);

        Flight::render('myComponent', ['prop' => 'Hi']);
        Flight::render('myComponent');
        Flight::render('input', ['type' => 'number']);
        Flight::render('input');
    }
}
