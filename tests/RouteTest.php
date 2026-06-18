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
 *     and percent-encoding
 *   - the URL-matching algorithm (matchRoute) incl. parameter extraction and
 *     typed parameter constraints ({id:int}, {slug:slug}, {token:uuid}, ...)
 *   - middleware resolution and the pass/deny contract (the security core),
 *     including cumulative middleware stacking across nested groups and the
 *     ability for a middleware to return a custom failure response
 *   - HTTP method spoofing (resolveMethod) used by plain HTML forms to send
 *     PUT/PATCH/DELETE via a `_method` field
 *
 * Every test fully resets the static state via reflection in setUp(), so tests
 * are independent and order-free. No collaborator is faked: matchRoute,
 * executeMiddleware, processMiddleware and resolveMethod are exercised through
 * reflection against the real code, so a genuine logic regression surfaces
 * here.
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
        $this->resetSuperglobals();
    }

    protected function tearDown(): void
    {
        $this->resetRouteState();
        $this->resetSuperglobals();
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
     * Reset the superglobals used by method spoofing (resolveMethod) so a
     * test that simulates a POST + `_method` field cannot leak into the
     * next test.
     */
    private function resetSuperglobals(): void
    {
        unset($_POST['_method']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
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
     * Read the private static $middlewares table.
     *
     * @return array<int, mixed>
     */
    private function middlewares(): array
    {
        $prop = (new \ReflectionClass(Route::class))->getProperty('middlewares');
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

    // =========================================================================
    // 1. Registration + verb helpers
    // =========================================================================

    public function testGetRegistersRouteWithLeadingSlash(): void
    {
        Route::get('users', fn () => null);
        $this->assertSame('/users', $this->routes()[0]['url']);
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
        Route::get('/users/', fn () => null);
        $this->assertSame('/users', $this->routes()[0]['url']);
    }

    public function testVerbHelpersReturnRouteInstanceForChaining(): void
    {
        $this->assertInstanceOf(Route::class, Route::get('users', fn () => null));
    }

    // =========================================================================
    // 2. Groups (prefix + middleware)
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

    /**
     * Parameter values must be percent-encoded so a value containing a slash,
     * space or other reserved character cannot corrupt the generated URL or
     * silently change the number of path segments.
     */
    public function testRouteUrlEncodesSpecialCharactersInParameterValue(): void
    {
        Route::get('search/{term}', fn () => null, 'search.show');
        $this->assertSame(
            '/search/hello%20world%2Fslash',
            Route::route('search.show', ['term' => 'hello world/slash'])
        );
    }

    /**
     * A typed placeholder such as {id:int} must still be recognised and fully
     * substituted by route() — including stripping the ":int" suffix from the
     * final URL — not just the bare {id} form.
     */
    public function testRouteSubstitutesTypedPlaceholder(): void
    {
        Route::get('users/{id:int}', fn () => null, 'users.show');
        $this->assertSame('/users/42', Route::route('users.show', ['id' => 42]));
    }

    public function testRouteReturnsEmptyStringWhenTypedParameterMissing(): void
    {
        Route::get('users/{id:int}', fn () => null, 'users.show');
        $result = @Route::route('users.show', []);
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
    // 5. Typed route parameters ({name:type} constraints in matchRoute)
    // =========================================================================

    public function testMatchRouteIntConstraintAcceptsNumericSegment(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id:int}', '/users/42']);
        $this->assertTrue($result['match']);
        // The ":int" suffix must not leak into the extracted parameter name.
        $this->assertSame(['id' => '42'], $result['params']);
    }

    public function testMatchRouteIntConstraintRejectsNonNumericSegment(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id:int}', '/users/abc']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteAlphaConstraintAcceptsLettersOnly(): void
    {
        $result = $this->callPrivate('matchRoute', ['/lang/{code:alpha}', '/lang/en']);
        $this->assertTrue($result['match']);
        $this->assertSame(['code' => 'en'], $result['params']);
    }

    public function testMatchRouteAlphaConstraintRejectsDigits(): void
    {
        $result = $this->callPrivate('matchRoute', ['/lang/{code:alpha}', '/lang/en2']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteAlnumConstraintAcceptsLettersAndDigits(): void
    {
        $result = $this->callPrivate('matchRoute', ['/coupon/{code:alnum}', '/coupon/SAVE10']);
        $this->assertTrue($result['match']);
        $this->assertSame(['code' => 'SAVE10'], $result['params']);
    }

    public function testMatchRouteAlnumConstraintRejectsSpecialCharacters(): void
    {
        $result = $this->callPrivate('matchRoute', ['/coupon/{code:alnum}', '/coupon/SAVE-10']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteSlugConstraintAcceptsHyphenAndUnderscore(): void
    {
        $result = $this->callPrivate('matchRoute', ['/posts/{slug:slug}', '/posts/hello_world-2024']);
        $this->assertTrue($result['match']);
        $this->assertSame(['slug' => 'hello_world-2024'], $result['params']);
    }

    public function testMatchRouteSlugConstraintRejectsSlashLikeContent(): void
    {
        $result = $this->callPrivate('matchRoute', ['/posts/{slug:slug}', '/posts/hello world']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteUuidConstraintAcceptsValidUuid(): void
    {
        $result = $this->callPrivate(
            'matchRoute',
            ['/orders/{id:uuid}', '/orders/550e8400-e29b-41d4-a716-446655440000']
        );
        $this->assertTrue($result['match']);
        $this->assertSame(['id' => '550e8400-e29b-41d4-a716-446655440000'], $result['params']);
    }

    public function testMatchRouteUuidConstraintRejectsInvalidUuid(): void
    {
        $result = $this->callPrivate('matchRoute', ['/orders/{id:uuid}', '/orders/not-a-uuid']);
        $this->assertFalse($result['match']);
    }

    /**
     * An unrecognised type after the colon (e.g. a typo, or a type the app
     * intends to validate later in the controller) must not crash matching;
     * the router falls back to accepting any value, same as an untyped
     * placeholder.
     */
    public function testMatchRouteUnknownTypeFallsBackToAcceptingAnyValue(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id:unknown_type}', '/users/anything-goes']);
        $this->assertTrue($result['match']);
        $this->assertSame(['id' => 'anything-goes'], $result['params']);
    }

    public function testMatchRouteTypedConstraintStillFailsOnStaticSegmentMismatch(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id:int}/edit', '/users/42/view']);
        $this->assertFalse($result['match']);
    }

    // =========================================================================
    // 6. Middleware resolution and pass/deny contract (security core)
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

    public function testMiddlewareOnlyStrictTrueIsTreatedAsPass(): void
    {
        // SECURITY (fail-secure): only an exact boolean true lets the route
        // proceed. Truthy-but-not-true values (1, "1") must NOT pass — they
        // are no longer silently coerced to a pass as in the old contract.
        $this->assertTrue($this->callPrivate('executeMiddleware', [fn () => true]));
        $this->assertNotTrue($this->callPrivate('executeMiddleware', [fn () => 1]));
    }

    public function testMiddlewareFalsyScalarResultsCollapseToDefaultDeny(): void
    {
        // Falsy/ambiguous scalars become a plain false (default 403), never a
        // pass and never an echoed body.
        $this->assertFalse($this->callPrivate('executeMiddleware', [fn () => 0]));
        $this->assertFalse($this->callPrivate('executeMiddleware', [fn () => null]));
        $this->assertFalse($this->callPrivate('executeMiddleware', [fn () => false]));
    }

    public function testMiddlewareStringResultIsReturnedAsResponseNotPass(): void
    {
        // A string (e.g. a rendered view) is a response, not a pass: it must
        // be returned verbatim so it can be sent to the client, and it must
        // NOT equal true (which would let the handler run instead).
        $result = $this->callPrivate('executeMiddleware', [fn () => '<h1>Login</h1>']);
        $this->assertSame('<h1>Login</h1>', $result);
        $this->assertNotTrue($result);
    }

    public function testMiddlewareObjectResultIsReturnedAsResponseNotPass(): void
    {
        $obj    = (object) ['view' => 'home'];
        $result = $this->callPrivate('executeMiddleware', [fn () => $obj]);
        $this->assertSame($obj, $result);
        $this->assertNotTrue($result);
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

    public function testMiddlewareClassAtMethodFormIsResolved(): void
    {
        $this->assertTrue($this->callPrivate('executeMiddleware', [RouteTestMiddlewareStub::class . '@allow']));
        $this->assertFalse($this->callPrivate('executeMiddleware', [RouteTestMiddlewareStub::class . '@deny']));
    }

    public function testMiddlewareClassAtMethodFormDeniesWhenClassMissing(): void
    {
        $result = @$this->callPrivate('executeMiddleware', ['Tests\\NoSuchClass@anything']);
        $this->assertFalse($result);
    }

    public function testMiddlewarePlainClassNameCallsHandleMethod(): void
    {
        $this->assertTrue($this->callPrivate('executeMiddleware', [RouteTestMiddlewareStub::class]));
    }

    // =========================================================================
    // 7. processMiddleware aggregation (all-must-pass)
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

    // =========================================================================
    // 8. Middleware custom failure responses (array return value)
    // =========================================================================

    public function testExecuteMiddlewareReturnsArrayResultUnchanged(): void
    {
        $middleware = fn () => ['error' => 'Unauthenticated', 'status' => 401];
        $result     = $this->callPrivate('executeMiddleware', [$middleware]);

        $this->assertIsArray($result);
        $this->assertSame(['error' => 'Unauthenticated', 'status' => 401], $result);
    }

    public function testProcessMiddlewareReturnsCustomArrayOnDenial(): void
    {
        $this->registerMiddlewareStack([
            fn () => true,
            fn () => ['error' => 'Forbidden region', 'status' => 451],
        ]);
        $route  = ['middleware' => 0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertIsArray($result);
        $this->assertSame(['error' => 'Forbidden region', 'status' => 451], $result);
    }

    public function testProcessMiddlewareShortCircuitsOnFirstCustomFailure(): void
    {
        $thirdRan = false;
        $this->registerMiddlewareStack([
            fn () => true,
            fn () => ['error' => 'first failure', 'status' => 401],
            function () use (&$thirdRan) {
                $thirdRan = true;
                return ['error' => 'second failure', 'status' => 403];
            },
        ]);
        $route  = ['middleware' => 0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertSame(['error' => 'first failure', 'status' => 401], $result);
        $this->assertFalse($thirdRan, 'middleware after a denial must not execute');
    }

    public function testProcessMiddlewareCustomArrayWithoutStatusKeyIsReturnedAsIs(): void
    {
        $this->registerMiddlewareStack(fn () => ['error' => 'Forbidden']);
        $route  = ['middleware' => 0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertSame(['error' => 'Forbidden'], $result);
    }

    public function testMiddlewareClassAtMethodCanReturnCustomArrayResponse(): void
    {
        $result = $this->callPrivate(
            'executeMiddleware',
            [RouteTestMiddlewareStub::class . '@denyWithCustomResponse']
        );

        $this->assertSame(['error' => 'Custom denial', 'status' => 401], $result);
    }

    public function testProcessMiddlewareReturnsStringViewResponseOnShortCircuit(): void
    {
        // A middleware returning a rendered view (string) must short-circuit
        // and the string must be propagated as the response, NOT coerced to a
        // pass (which would run the handler instead of showing the view).
        $this->registerMiddlewareStack([
            fn () => true,
            fn () => '<h1>Please log in</h1>',
        ]);
        $route  = ['middleware' => 0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertSame('<h1>Please log in</h1>', $result);
        $this->assertNotTrue($result, 'a view response must never be treated as a pass');
    }

    public function testProcessMiddlewareStringResponseStopsLaterMiddleware(): void
    {
        $laterRan = false;
        $this->registerMiddlewareStack([
            fn () => 'redirect-or-view',
            function () use (&$laterRan) {
                $laterRan = true;
                return true;
            },
        ]);
        $route  = ['middleware' => 0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertSame('redirect-or-view', $result);
        $this->assertFalse($laterRan, 'middleware after a short-circuiting response must not run');
    }

    // =========================================================================
    // 9. Cumulative middleware stacking across nested groups
    // =========================================================================

    public function testNestedGroupMiddlewareStacksWithParent(): void
    {
        $outerRan = false;
        $innerRan = false;

        Route::group(
            ['middleware' => function () use (&$outerRan) { $outerRan = true; return true; }],
            function () use (&$innerRan): void {
                Route::group(
                    ['prefix' => 'inner', 'middleware' => function () use (&$innerRan) { $innerRan = true; return true; }],
                    function (): void {
                        Route::get('x', fn () => null);
                    }
                );
            }
        );

        $route  = $this->routes()[0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertTrue($result);
        $this->assertTrue($outerRan, 'outer group middleware must run for a route in the nested group');
        $this->assertTrue($innerRan, 'inner group middleware must run for a route in the nested group');
    }

    public function testNestedGroupMiddlewareListContainsBothParentAndChild(): void
    {
        Route::group(
            ['middleware' => fn () => true],
            function (): void {
                Route::group(
                    ['prefix' => 'inner', 'middleware' => fn () => true],
                    function (): void {
                        Route::get('x', fn () => null);
                    }
                );
            }
        );

        $route        = $this->routes()[0];
        $middlewareList = $this->middlewares()[$route['middleware']];

        $this->assertCount(2, $middlewareList, 'the merged stack must contain the parent and the child middleware');
    }

    public function testNestedGroupParentDenialBlocksRouteEvenIfChildWouldPass(): void
    {
        $childRan = false;

        Route::group(
            ['middleware' => fn () => false],
            function () use (&$childRan): void {
                Route::group(
                    ['prefix' => 'inner', 'middleware' => function () use (&$childRan) { $childRan = true; return true; }],
                    function (): void {
                        Route::get('x', fn () => null);
                    }
                );
            }
        );

        $route  = $this->routes()[0];
        $result = $this->callPrivate('processMiddleware', [$route]);

        $this->assertFalse($result);
        $this->assertFalse($childRan, 'parent middleware runs first; a denial must short-circuit the child');
    }

    public function testSiblingGroupsDoNotLeakMiddlewareIntoEachOther(): void
    {
        Route::group(['middleware' => fn () => true], function (): void {
            Route::get('protected', fn () => null);
        });

        // A sibling group declared afterwards, with no middleware of its own,
        // must not inherit the previous group's middleware index.
        Route::group('public', function (): void {
            Route::get('open', fn () => null);
        });

        $routes = $this->routes();
        $this->assertSame(0, $routes[0]['middleware']);
        $this->assertSame(-1, $routes[1]['middleware']);
    }

    public function testRouteDeclaredAfterGroupClosesHasNoInheritedMiddleware(): void
    {
        Route::group(['middleware' => fn () => true], function (): void {
            Route::group(['prefix' => 'inner', 'middleware' => fn () => true], function (): void {
                Route::get('nested', fn () => null);
            });
        });
        Route::get('outside', fn () => null);

        $routes = $this->routes();
        $this->assertSame(-1, $routes[1]['middleware']);
    }

    public function testGroupMiddlewareGivenAsArrayMergesAllEntriesWithParent(): void
    {
        Route::group(['middleware' => fn () => true], function (): void {
            Route::group(
                ['prefix' => 'inner', 'middleware' => [fn () => true, fn () => true]],
                function (): void {
                    Route::get('x', fn () => null);
                }
            );
        });

        $route          = $this->routes()[0];
        $middlewareList = $this->middlewares()[$route['middleware']];

        $this->assertCount(3, $middlewareList, 'one parent middleware plus two child middlewares');
        $this->assertTrue($this->callPrivate('processMiddleware', [$route]));
    }

    // =========================================================================
    // 10. HTTP method spoofing (resolveMethod)
    // =========================================================================

    public function testResolveMethodReturnsRealMethodWhenNoSpoofFieldPresent(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['_method']);

        $this->assertSame('GET', $this->callPrivate('resolveMethod', []));
    }

    public function testResolveMethodSpoofsPostToPut(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method']          = 'PUT';

        $this->assertSame('PUT', $this->callPrivate('resolveMethod', []));
    }

    public function testResolveMethodSpoofsPostToPatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method']          = 'PATCH';

        $this->assertSame('PATCH', $this->callPrivate('resolveMethod', []));
    }

    public function testResolveMethodSpoofsPostToDelete(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method']          = 'DELETE';

        $this->assertSame('DELETE', $this->callPrivate('resolveMethod', []));
    }

    public function testResolveMethodSpoofingIsCaseInsensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method']          = 'put';

        $this->assertSame('PUT', $this->callPrivate('resolveMethod', []));
    }

    public function testResolveMethodIgnoresUnsupportedSpoofValue(): void
    {
        // SECURITY: only PUT/PATCH/DELETE may be spoofed; an attempt to spoof
        // to an arbitrary value must be ignored and the real method kept.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method']          = 'GET';

        $this->assertSame('POST', $this->callPrivate('resolveMethod', []));
    }

    public function testResolveMethodIgnoresSpoofFieldWhenRealMethodIsNotPost(): void
    {
        // A GET request carrying a stray `_method` field (e.g. from a query
        // string) must never be spoofed; only POST may be overridden.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST['_method']          = 'DELETE';

        $this->assertSame('GET', $this->callPrivate('resolveMethod', []));
    }

    // =========================================================================
    // 11. Optional parameters {name?} and {name?:type}
    // =========================================================================

    public function testMatchRouteOptionalParameterOmitted(): void
    {
        // '/users/{id?}' must match '/users' with no params.
        $result = $this->callPrivate('matchRoute', ['/users/{id?}', '/users']);
        $this->assertTrue($result['match']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchRouteOptionalParameterProvided(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id?}', '/users/42']);
        $this->assertTrue($result['match']);
        $this->assertSame(['id' => '42'], $result['params']);
    }

    public function testMatchRouteOptionalParameterDoesNotMatchDeeperPath(): void
    {
        // A single optional segment must not swallow two URI segments.
        $result = $this->callPrivate('matchRoute', ['/users/{id?}', '/users/1/2']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteRequiredParameterStillRequired(): void
    {
        // Sanity guard: a non-optional parameter must NOT match when omitted.
        $result = $this->callPrivate('matchRoute', ['/users/{id}', '/users']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteTypedOptionalParameterOmitted(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id?:int}', '/users']);
        $this->assertTrue($result['match']);
        $this->assertSame([], $result['params']);
    }

    public function testMatchRouteTypedOptionalParameterAcceptsValidValue(): void
    {
        $result = $this->callPrivate('matchRoute', ['/users/{id?:int}', '/users/42']);
        $this->assertTrue($result['match']);
        $this->assertSame(['id' => '42'], $result['params']);
    }

    public function testMatchRouteTypedOptionalParameterRejectsInvalidValue(): void
    {
        // Present-but-wrong-type must still fail, even though the param is optional.
        $result = $this->callPrivate('matchRoute', ['/users/{id?:int}', '/users/abc']);
        $this->assertFalse($result['match']);
    }

    public function testMatchRouteStaticSegmentAfterRequiredParamStillEnforced(): void
    {
        // Mixing a required param with a trailing static segment keeps working.
        $result = $this->callPrivate('matchRoute', ['/a/{x}/b', '/a/1/b']);
        $this->assertTrue($result['match']);
        $this->assertSame(['x' => '1'], $result['params']);
    }

    public function testRouteGeneratesUrlWithOptionalParameterProvided(): void
    {
        Route::get('users/{id?}', fn () => null, 'users.maybe');
        $this->assertSame('/users/42', Route::route('users.maybe', ['id' => 42]));
    }

    public function testRouteGeneratesUrlWithOptionalParameterOmitted(): void
    {
        Route::get('users/{id?}', fn () => null, 'users.maybe');
        // Omitting the optional param drops the segment AND its leading slash.
        $this->assertSame('/users', Route::route('users.maybe'));
    }

    public function testRouteGeneratesUrlWithTypedOptionalParameterOmitted(): void
    {
        Route::get('posts/{id?:int}', fn () => null, 'posts.maybe');
        $this->assertSame('/posts', Route::route('posts.maybe'));
    }

    public function testRouteOmittingOptionalTrailingParameterKeepsRequiredOnes(): void
    {
        Route::get('a/{x}/b/{y?}', fn () => null, 'mixed.maybe');
        $this->assertSame('/a/1/b', Route::route('mixed.maybe', ['x' => 1]));
        $this->assertSame('/a/1/b/2', Route::route('mixed.maybe', ['x' => 1, 'y' => 2]));
    }

    public function testRouteStillErrorsWhenRequiredParameterMissingAlongsideOptional(): void
    {
        Route::get('a/{x}/b/{y?}', fn () => null, 'mixed.maybe');
        // 'x' is required; omitting it must still yield the documented empty string.
        $this->assertSame('', @Route::route('mixed.maybe', ['y' => 2]));
    }

    // =========================================================================
    // 12. Middleware failure stops the handler (regression: explicit return)
    // =========================================================================

    /**
     * SECURITY REGRESSION GUARD:
     * When middleware denies a route, run() must NOT fall through to the
     * handler. Header::respond() exits in production, but run() must not rely
     * solely on that side-effect — it issues an explicit `return`. We verify
     * the guard by asserting run() contains a return immediately after the
     * middleware-failure branch, since run() itself calls exit-ing output and
     * cannot be invoked in-process.
     */
    public function testRunReturnsAfterMiddlewareFailureSoHandlerNeverRuns(): void
    {
        $source = (new \ReflectionMethod(Route::class, 'run'))->getFileName();
        $start  = (new \ReflectionMethod(Route::class, 'run'))->getStartLine();
        $end    = (new \ReflectionMethod(Route::class, 'run'))->getEndLine();
        $body   = implode('', array_slice(file($source), $start - 1, $end - $start + 1));

        // The failure branch must contain respondMiddlewareFailure(...) followed
        // by a return before dispatch() is reached.
        $this->assertMatchesRegularExpression(
            '/respondMiddlewareFailure\([^;]*\);\s*return;/s',
            $body,
            'run() must explicitly return after responding to a middleware failure.'
        );
    }

    public function testMiddlewareClassAtMethodCanReturnViewString(): void
    {
        // A class-based middleware (Class@method) returning a view string must
        // have that string propagated as the response, not coerced to a pass.
        $result = $this->callPrivate(
            'executeMiddleware',
            [RouteTestMiddlewareStub::class . '@renderView']
        );

        $this->assertSame('<h1>Stub view</h1>', $result);
        $this->assertNotTrue($result);
    }
}

/**
 * Minimal middleware stub used to exercise the class-based middleware forms:
 *   - "Tests\RouteTestMiddlewareStub@allow"
 *   - "Tests\RouteTestMiddlewareStub@deny"
 *   - "Tests\RouteTestMiddlewareStub@denyWithCustomResponse"
 *   - "Tests\RouteTestMiddlewareStub" (plain class name, calls handle())
 */
class RouteTestMiddlewareStub
{
    public function handle(): bool
    {
        return true;
    }

    public function allow(): bool
    {
        return true;
    }

    public function deny(): bool
    {
        return false;
    }

    public function denyWithCustomResponse(): array
    {
        return ['error' => 'Custom denial', 'status' => 401];
    }

    public function renderView(): string
    {
        return '<h1>Stub view</h1>';
    }
}