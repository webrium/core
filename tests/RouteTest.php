<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Route;

/**
 * Unit Tests for Webrium\Route
 *
 * Scope and rationale
 * --------------------
 * Route is a static router holding all of its state in private static
 * properties ($routes, $routeNames, $prefix, $middlewares, $middlewareIndex,
 * $notFoundHandler). Two parts of the class perform real I/O and terminate the
 * process:
 *   - run() ultimately calls Header::respond(), which echoes and `exit`s
 *     (its return type is `never`); and
 *   - handleNotFound() calls http_response_code()/echo.
 * Those output side-effects make run() unsuitable for in-process assertions, so
 * this suite deliberately targets the deterministic, side-effect-free logic
 * that the security and correctness of the router actually depend on:
 *
 *   - registration + verb helpers build the route table correctly
 *   - prefixes (direct + nested groups) compose as documented
 *   - named routes and route() URL generation, including parameter handling
 *   - the URL-matching algorithm (matchRoute) incl. parameter extraction
 *   - middleware resolution and the pass/deny contract (the security core)
 *
 * Every test fully resets the static state via reflection in setUp(), so tests
 * are independent and order-free. No collaborator is faked: matchRoute and
 * executeMiddleware are exercised through reflection against the real code, so
 * a genuine logic regression surfaces here.
 *
 * Note on integration with HttpClient: an HttpClient<->Route round trip would
 * require booting a real HTTP server (curl talks to a socket) and would couple
 * Route, Header, the network and a child process into one assertion — that is
 * an e2e test, not a unit test: slow, flaky, and unable to localise a failure.
 * The two classes are therefore tested separately.
 */
class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetRouteState();
    }

    protected function tearDown(): void
    {
        $this->resetRouteState();
    }

    /**
     * Reset every private static property of Route to its declared default so
     * no route, name, prefix or middleware leaks between tests.
     */
    private function resetRouteState(): void
    {
        $ref = new \ReflectionClass(Route::class);
        $defaults = [
            'routes'          => [],
            'routeNames'      => [],
            'prefix'          => '',
            'notFoundHandler' => null,
            'middlewares'     => [],
            'middlewareIndex' => -1,
        ];
        foreach ($defaults as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }
    }

    /**
     * Read the private static $routes table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function routes(): array
    {
        $prop = (new \ReflectionClass(Route::class))->getProperty('routes');
        $prop->setAccessible(true);
        return $prop->getValue();
    }

    /**
     * Invoke a private static method on Route via reflection.
     */
    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(Route::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }

    // =========================================================================
    // 1. Registration + verb helpers
    // =========================================================================

    public function testGetRegistersRouteWithLeadingSlash(): void
    {
        Route::get('users', fn () => 'ok');

        $routes = $this->routes();
        $this->assertCount(1, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        // Stored url is normalised to a single leading slash.
        $this->assertSame('/users', $routes[0]['url']);
    }

    public function testEachVerbStoresItsMethod(): void
    {
        Route::get('a', fn () => null);
        Route::post('b', fn () => null);
        Route::put('c', fn () => null);
        Route::patch('d', fn () => null);
        Route::delete('e', fn () => null);
        Route::any('f', fn () => null);

        $methods = array_column($this->routes(), 'method');
        $this->assertSame(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'ANY'], $methods);
    }

    public function testLeadingAndTrailingSlashesAreTrimmedConsistently(): void
    {
        Route::get('/products/', fn () => null);
        $this->assertSame('/products', $this->routes()[0]['url']);
    }

    public function testVerbHelpersReturnRouteInstanceForChaining(): void
    {
        $this->assertInstanceOf(Route::class, Route::get('x', fn () => null));
    }

    // =========================================================================
    // 2. Prefixes and groups
    // =========================================================================

    public function testStringPrefixGroupIsApplied(): void
    {
        Route::group('admin', function (): void {
            Route::get('dashboard', fn () => null);
        });

        $this->assertSame('/admin/dashboard', $this->routes()[0]['url']);
    }

    public function testArrayPrefixGroupIsApplied(): void
    {
        Route::group(['prefix' => 'api'], function (): void {
            Route::get('status', fn () => null);
        });

        $this->assertSame('/api/status', $this->routes()[0]['url']);
    }

    public function testNestedGroupsComposePrefixes(): void
    {
        Route::group('api', function (): void {
            Route::group('v1', function (): void {
                Route::get('ping', fn () => null);
            });
        });

        $this->assertSame('/api/v1/ping', $this->routes()[0]['url']);
    }

    public function testPrefixIsRestoredAfterGroupCloses(): void
    {
        Route::group('admin', function (): void {
            Route::get('inside', fn () => null);
        });
        // A route declared after the group must NOT inherit the prefix.
        Route::get('outside', fn () => null);

        $urls = array_column($this->routes(), 'url');
        $this->assertSame(['/admin/inside', '/outside'], $urls);
    }

    public function testGroupMiddlewareIndexIsAttachedToContainedRoutes(): void
    {
        Route::group(['prefix' => 'secure', 'middleware' => fn () => true], function (): void {
            Route::get('area', fn () => null);
        });
        // The single route in the group should reference middleware index 0.
        $this->assertSame(0, $this->routes()[0]['middleware']);
    }

    public function testRouteOutsideGroupHasNoMiddleware(): void
    {
        Route::get('open', fn () => null);
        // -1 is the sentinel meaning "no middleware".
        $this->assertSame(-1, $this->routes()[0]['middleware']);
    }

    // =========================================================================
    // 3. Named routes + route() URL generation
    // =========================================================================

    public function testNamedRouteGeneratesUrl(): void
    {
        Route::get('users', fn () => null, 'users.index');
        $this->assertSame('/users', Route::route('users.index'));
    }

    public function testNameMethodAssignsNameToLastRoute(): void
    {
        Route::get('profile', fn () => null)->name('profile.show');
        $this->assertSame('/profile', Route::route('profile.show'));
    }

    public function testRouteSubstitutesParameters(): void
    {
        Route::get('users/{id}', fn () => null, 'users.show');
        // Passing the value as a string works as intended.
        $this->assertSame('/users/42', Route::route('users.show', ['id' => '42']));
    }

    /**
     * Regression test for the int-parameter fix:
     * Route::route() casts each parameter to string before substituting, so an
     * integer parameter (the very common Route::route('users.show', ['id' => 42]))
     * is rendered correctly instead of throwing TypeError on PHP 8.1+.
     */
    public function testRouteAcceptsIntegerParameter(): void
    {
        Route::get('users/{id}', fn () => null, 'users.show');
        $this->assertSame('/users/42', Route::route('users.show', ['id' => 42]));
    }

    public function testRouteAcceptsFloatAndMixedScalarParameters(): void
    {
        Route::get('items/{price}/{code}', fn () => null, 'items.show');
        // float and int values must both render without error.
        $this->assertSame('/items/9.99/7', Route::route('items.show', ['price' => 9.99, 'code' => 7]));
    }

    public function testRouteSubstitutesMultipleParameters(): void
    {
        Route::get('posts/{post}/comments/{comment}', fn () => null, 'comments.show');
        $this->assertSame(
            '/posts/7/comments/3',
            Route::route('comments.show', ['post' => '7', 'comment' => '3'])
        );
    }

    public function testRouteReturnsEmptyStringForUnknownName(): void
    {
        // Debug::triggerError is invoked internally; suppress its side effects
        // and assert the documented empty-string return.
        $result = @Route::route('does.not.exist');
        $this->assertSame('', $result);
    }

    public function testRouteReturnsEmptyStringWhenParameterMissing(): void
    {
        Route::get('users/{id}', fn () => null, 'users.show');
        $result = @Route::route('users.show', []); // missing 'id'
        $this->assertSame('', $result);
    }

    // =========================================================================
    // 4. URL matching algorithm (matchRoute)
    // =========================================================================

    public function testMatchRouteExactMatch(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users', '/users']);
        $this->assertTrue($result['match']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchRouteIgnoresSurroundingSlashes(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/', 'users']);
        $this->assertTrue($result['match']);
    }

    public function testMatchRouteExtractsSingleParameter(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id}', '/users/42']);
        $this->assertTrue($result['match']);
        $this->assertSame(['id' => '42'], $result['params']);
    }

    public function testMatchRouteExtractsMultipleParameters(): void
    {
        $result = $this->callPrivate('matchRoute', ['/posts/{post}/comments/{comment}', '/posts/7/comments/3']);
        $this->assertTrue($result['match']);
        $this->assertSame(['post' => '7', 'comment' => '3'], $result['params']);
    }

    public function testMatchRouteFailsOnDifferentStaticSegment(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id}', '/orders/42']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteFailsOnSegmentCountMismatch(): void
    {
        // A {id} route must not match a deeper path: this is the guard that
        // stops '/users/{id}' from greedily swallowing '/users/42/edit'.
        $result = $this->callPrivate('matchRoute', ['/users/{id}', '/users/42/edit']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteStaticPatternDoesNotMatchDifferentUri(): void
    {
        $result = $this->callPrivate('matchRoute', ['/about', '/contact']);
        $this->assertFalse($result['match']);
    }

    // =========================================================================
    // 5. Middleware resolution and pass/deny contract (security core)
    // =========================================================================

    public function testMiddlewareBooleanTruePasses(): void
    {
        $this->assertTrue($this->callPrivate('executeMiddleware', [true]));
    }

    public function testMiddlewareBooleanFalseDenies(): void
    {
        $this->assertFalse($this->callPrivate('executeMiddleware', [false]));
    }

    public function testMiddlewareClosureReturningTruePasses(): void
    {
        $this->assertTrue($this->callPrivate('executeMiddleware', [fn () => true]));
    }

    public function testMiddlewareClosureReturningFalseDenies(): void
    {
        $this->assertFalse($this->callPrivate('executeMiddleware', [fn () => false]));
    }

    public function testMiddlewareClosureResultIsCoercedToBool(): void
    {
        // A truthy non-bool must be treated as a pass, a falsy one as a deny.
        $this->assertTrue($this->callPrivate('executeMiddleware', [fn () => 1]));
        $this->assertFalse($this->callPrivate('executeMiddleware', [fn () => 0]));
        $this->assertFalse($this->callPrivate('executeMiddleware', [fn () => null]));
    }

    public function testMiddlewareGlobalFunctionIsResolved(): void
    {
        // Define a real global function and ensure it is resolved by name.
        if (!function_exists('Tests\\route_mw_allows')) {
            eval('namespace Tests; function route_mw_allows() { return true; }');
        }
        if (!function_exists('Tests\\route_mw_denies')) {
            eval('namespace Tests; function route_mw_denies() { return false; }');
        }

        $this->assertTrue($this->callPrivate('executeMiddleware', ['Tests\\route_mw_allows']));
        $this->assertFalse($this->callPrivate('executeMiddleware', ['Tests\\route_mw_denies']));
    }

    public function testMiddlewareUnresolvableStringDeniesByDefault(): void
    {
        // SECURITY: an unknown middleware reference must FAIL CLOSED (deny),
        // never silently pass. Debug::triggerError fires internally; suppress
        // it and assert the deny.
        $result = @$this->callPrivate('executeMiddleware', ['NoSuchMiddlewareClassOrFn']);
        $this->assertFalse($result);
    }

    public function testMiddlewareInvalidTypeDeniesByDefault(): void
    {
        // SECURITY: a nonsensical middleware value must also fail closed.
        $result = @$this->callPrivate('executeMiddleware', [42]);
        $this->assertFalse($result);
    }

    // =========================================================================
    // 6. processMiddleware aggregation (all-must-pass)
    // =========================================================================

    public function testProcessMiddlewareReturnsTrueWhenRouteHasNone(): void
    {
        $route = ['middleware' => -1];
        $this->assertTrue($this->callPrivate('processMiddleware', [$route]));
    }

    public function testProcessMiddlewarePassesWhenAllPass(): void
    {
        $this->registerMiddlewareStack([fn () => true, fn () => true]);
        $route = ['middleware' => 0];
        $this->assertTrue($this->callPrivate('processMiddleware', [$route]));
    }

    public function testProcessMiddlewareDeniesIfAnyDenies(): void
    {
        // SECURITY: a single failing middleware in the stack must deny the whole
        // route, regardless of the others passing.
        $this->registerMiddlewareStack([fn () => true, fn () => false, fn () => true]);
        $route = ['middleware' => 0];
        $this->assertFalse($this->callPrivate('processMiddleware', [$route]));
    }

    public function testProcessMiddlewareShortCircuitsOnFirstDenial(): void
    {
        // The second middleware denies; the third must never run.
        $thirdRan = false;
        $this->registerMiddlewareStack([
            fn () => true,
            fn () => false,
            function () use (&$thirdRan) { $thirdRan = true; return true; },
        ]);
        $route = ['middleware' => 0];

        $this->assertFalse($this->callPrivate('processMiddleware', [$route]));
        $this->assertFalse($thirdRan, 'middleware after a denial must not execute');
    }

    public function testProcessMiddlewareAcceptsSingleNonArrayMiddleware(): void
    {
        // A group registered with a single callable (not wrapped in an array)
        // must still be honoured.
        $this->registerMiddlewareStack(fn () => true);
        $route = ['middleware' => 0];
        $this->assertTrue($this->callPrivate('processMiddleware', [$route]));
    }

    /**
     * Helper: place a middleware definition at index 0 of Route::$middlewares,
     * mirroring what group() does internally.
     *
     * @param mixed $definition A callable or an array of callables.
     */
    private function registerMiddlewareStack(mixed $definition): void
    {
        $prop = (new \ReflectionClass(Route::class))->getProperty('middlewares');
        $prop->setAccessible(true);
        $prop->setValue(null, [$definition]);
    }
}