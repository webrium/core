<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Url;

/**
 * Unit Tests for Webrium\Url
 *
 * پوشش کامل:
 *  - scheme / isSecure
 *  - domain / method
 *  - uri / queryString
 *  - clientIp / serverIp
 *  - trailing slash helpers
 *  - parse / build / withQuery / withoutQuery
 *  - referer / refererDomain / isRefererFrom / isInternalReferer
 *  - origin / originDomain / isOriginFrom / isSameOrigin
 *  - sourceDomain / source / isFromAllowedDomain
 *  - isAjax / isMobile / userAgent
 *  - server()
 *  - segments() / segment()
 *  - home() / current() / full() / uri()
 *  - previous() / referer()
 *  - input() ← تمرکز ویژه روی GET, POST, PUT, PATCH, DELETE
 *              با فرمت‌های application/json و application/x-www-form-urlencoded
 */
class UrlTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Setup
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        $_SERVER = [];
        $_GET    = [];
        $_POST   = [];
        Url::reset();
    }

    // =========================================================================
    // 1. scheme() / isSecure()
    // =========================================================================

    public function testSchemeReturnsHttpByDefault(): void
    {
        $this->assertSame('http', Url::scheme());
    }

    public function testSchemeReturnsHttpsWhenHttpsIsOn(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https', Url::scheme());
    }

    public function testSchemeReturnsHttpWhenHttpsIsOff(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $this->assertSame('http', Url::scheme());
    }

    public function testSchemeWithSeparatorAppendsColonSlashSlash(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https://', Url::scheme(true));
    }

    public function testSchemeHttpWithSeparator(): void
    {
        $this->assertSame('http://', Url::scheme(true));
    }

    public function testIsSecureReturnsFalseByDefault(): void
    {
        $this->assertFalse(Url::isSecure());
    }

    public function testIsSecureViaHttpsOn(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(Url::isSecure());
    }

    public function testIsSecureViaForwardedProto(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(Url::isSecure());
    }

    public function testIsSecureViaForwardedSsl(): void
    {
        $_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';
        $this->assertTrue(Url::isSecure());
    }

    public function testIsSecureViaPort443(): void
    {
        $_SERVER['SERVER_PORT'] = '443';
        $this->assertTrue(Url::isSecure());
    }

    public function testIsNotSecureWhenHttpsIsOff(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(Url::isSecure());
    }

    // =========================================================================
    // 2. domain() / method()
    // =========================================================================

    public function testDomainFromHttpHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertSame('example.com', Url::domain());
    }

    public function testDomainFallsBackToServerName(): void
    {
        $_SERVER['SERVER_NAME'] = 'fallback.com';
        $this->assertSame('fallback.com', Url::domain());
    }

    public function testDomainPrefersHttpHostOverServerName(): void
    {
        $_SERVER['HTTP_HOST']   = 'preferred.com';
        $_SERVER['SERVER_NAME'] = 'ignored.com';
        $this->assertSame('preferred.com', Url::domain());
    }

    public function testDomainReturnsEmptyStringWhenNeitherSet(): void
    {
        $this->assertSame('', Url::domain());
    }

    public function testMethodReturnsGetByDefault(): void
    {
        $this->assertSame('GET', Url::method());
    }

    public function testMethodUppercasesLowercaseInput(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $this->assertSame('POST', Url::method());
    }

    /** @dataProvider httpMethodProvider */
    public function testMethodRecognizesAllStandardVerbs(string $verb): void
    {
        $_SERVER['REQUEST_METHOD'] = $verb;
        $this->assertSame($verb, Url::method());
    }

    public static function httpMethodProvider(): array
    {
        return [
            ['GET'], ['POST'], ['PUT'], ['PATCH'], ['DELETE'], ['OPTIONS'], ['HEAD'],
        ];
    }

    // =========================================================================
    // 3. uri() / queryString() / home() / current() / full()
    // =========================================================================

    public function testUriStripsQueryStringByDefault(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/1?page=2';
        $this->assertSame('/users/1', Url::uri());
    }

    public function testUriIncludesQueryStringWhenRequested(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/1?page=2';
        $this->assertSame('/users/1?page=2', Url::uri(true));
    }

    public function testUriDecodesUrlEncodedCharacters(): void
    {
        $_SERVER['REQUEST_URI'] = '/path/%D8%B3%D9%84%D8%A7%D9%85';
        $this->assertSame('/path/سلام', Url::uri());
    }

    public function testUriDefaultsToSlashWhenNotSet(): void
    {
        $this->assertSame('/', Url::uri());
    }

    public function testQueryString(): void
    {
        $_SERVER['QUERY_STRING'] = 'foo=bar&baz=1';
        $this->assertSame('foo=bar&baz=1', Url::queryString());
    }

    public function testQueryStringReturnsEmptyByDefault(): void
    {
        $this->assertSame('', Url::queryString());
    }

    public function testHomeComposesSchemeAndDomain(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertSame('http://example.com', Url::home());
    }

    public function testHomeWithHttps(): void
    {
        $_SERVER['HTTPS']     = 'on';
        $_SERVER['HTTP_HOST'] = 'secure.com';
        $this->assertSame('https://secure.com', Url::home());
    }

    public function testCurrentUrl(): void
    {
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/about';
        $this->assertSame('http://example.com/about', Url::current());
    }

    public function testFullIncludesQueryString(): void
    {
        $_SERVER['HTTP_HOST']     = 'example.com';
        $_SERVER['REQUEST_URI']   = '/search?q=php';
        $this->assertStringContainsString('q=php', Url::full());
    }

    // =========================================================================
    // 4. segments() / segment()
    // =========================================================================

    public function testSegmentsReturnsPathParts(): void
    {
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/api/v1/users';
        $segments = Url::segments();
        $this->assertContains('api',   $segments);
        $this->assertContains('v1',    $segments);
        $this->assertContains('users', $segments);
    }

    public function testSegmentByIndex(): void
    {
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/posts/42';
        $this->assertSame('blog',  Url::segment(0));
        $this->assertSame('posts', Url::segment(1));
        $this->assertSame('42',    Url::segment(2));
    }

    public function testSegmentReturnsDefaultForMissingIndex(): void
    {
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/';
        $this->assertNull(Url::segment(0));
        $this->assertSame('fallback', Url::segment(5, 'fallback'));
    }

    // =========================================================================
    // 5. clientIp()
    // =========================================================================

    public function testClientIpFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.10';
        $this->assertSame('192.168.1.10', Url::clientIp());
    }

    public function testClientIpPrefersHttpClientIp(): void
    {
        $_SERVER['REMOTE_ADDR']    = '10.0.0.1';
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.5';
        $this->assertSame('203.0.113.5', Url::clientIp());
    }

    public function testClientIpHandlesMultipleForwardedIps(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 10.0.0.1, 172.16.0.1';
        $this->assertSame('203.0.113.5', Url::clientIp());
    }

    public function testClientIpSkipsInvalidAndUsesNext(): void
    {
        $_SERVER['HTTP_CLIENT_IP']       = 'not-valid-ip';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';
        $this->assertSame('198.51.100.20', Url::clientIp());
    }

    public function testClientIpReturnsEmptyStringWhenNothingSet(): void
    {
        $this->assertSame('', Url::clientIp());
    }

    // =========================================================================
    // 6. Trailing slash helpers
    // =========================================================================

    public function testRemoveTrailingSlash(): void
    {
        $this->assertSame('/path/to', Url::removeTrailingSlash('/path/to/'));
        $this->assertSame('/path/to', Url::removeTrailingSlash('/path/to'));
        $this->assertSame('',         Url::removeTrailingSlash('/'));
    }

    public function testHasTrailingSlash(): void
    {
        $this->assertTrue(Url::hasTrailingSlash('/path/'));
        $this->assertFalse(Url::hasTrailingSlash('/path'));
        $this->assertTrue(Url::hasTrailingSlash('/'));
    }

    public function testAddTrailingSlash(): void
    {
        $this->assertSame('/path/', Url::addTrailingSlash('/path'));
        $this->assertSame('/path/', Url::addTrailingSlash('/path/'));
    }

    // =========================================================================
    // 7. parse() / build() / withQuery() / withoutQuery()
    // =========================================================================

    public function testParseFull(): void
    {
        $result = Url::parse('https://example.com:8080/api/v1?key=val#section');
        $this->assertSame('https',       $result['scheme']);
        $this->assertSame('example.com', $result['host']);
        $this->assertSame(8080,          $result['port']);
        $this->assertSame('/api/v1',     $result['path']);
        $this->assertSame('key=val',     $result['query']);
        $this->assertSame('section',     $result['fragment']);
    }

    public function testParseMinimalUrl(): void
    {
        $result = Url::parse('https://example.com');
        $this->assertSame('https',       $result['scheme']);
        $this->assertSame('example.com', $result['host']);
        $this->assertNull($result['port']);
        $this->assertSame('/', $result['path']);
        $this->assertSame('', $result['query']);
        $this->assertSame('', $result['fragment']);
    }

    public function testBuildFromAllComponents(): void
    {
        $url = Url::build([
            'scheme'   => 'https',
            'host'     => 'example.com',
            'port'     => 8080,
            'path'     => '/api',
            'query'    => 'foo=bar',
            'fragment' => 'top',
        ]);
        $this->assertSame('https://example.com:8080/api?foo=bar#top', $url);
    }

    public function testBuildSkipsEmptyParts(): void
    {
        $url = Url::build(['scheme' => 'http', 'host' => 'example.com', 'path' => '/']);
        $this->assertSame('http://example.com/', $url);
    }

    public function testWithQueryAddsNewParameters(): void
    {
        $result = Url::withQuery(['page' => 2, 'sort' => 'asc'], 'https://example.com/items?status=active');
        $this->assertStringContainsString('page=2',        $result);
        $this->assertStringContainsString('sort=asc',      $result);
        $this->assertStringContainsString('status=active', $result);
    }

    public function testWithQueryOverwritesExistingParam(): void
    {
        $result = Url::withQuery(['page' => 5], 'https://example.com/items?page=1');
        $this->assertStringContainsString('page=5',    $result);
        $this->assertStringNotContainsString('page=1', $result);
    }

    public function testWithoutQueryRemovesSpecifiedKeys(): void
    {
        $result = Url::withoutQuery(['token'], 'https://example.com/page?token=abc&lang=en');
        $this->assertStringNotContainsString('token', $result);
        $this->assertStringContainsString('lang=en',  $result);
    }

    public function testWithoutQueryLeavesOtherParamsIntact(): void
    {
        $result = Url::withoutQuery(['debug'], 'https://example.com/?debug=1&version=2');
        $this->assertStringContainsString('version=2', $result);
        $this->assertStringNotContainsString('debug',  $result);
    }

    // =========================================================================
    // 8. Referer helpers
    // =========================================================================

    public function testRefererReturnsNullWhenNotSet(): void
    {
        $this->assertNull(Url::referer());
    }

    public function testRefererReturnsValueFromServer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://google.com/search';
        $this->assertSame('https://google.com/search', Url::referer());
    }

    public function testRefererReturnsDefaultWhenAbsent(): void
    {
        $this->assertSame('https://default.com', Url::referer('https://default.com'));
    }

    public function testRefererDomainExtractsDomain(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://www.google.com/search?q=php';
        $this->assertSame('www.google.com', Url::refererDomain());
    }

    public function testRefererDomainReturnsNullWhenNoReferer(): void
    {
        $this->assertNull(Url::refererDomain());
    }

    public function testIsRefererFromIgnoresWwwPrefix(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://www.example.com/page';
        $this->assertTrue(Url::isRefererFrom('example.com'));
    }

    public function testIsRefererFromReturnsFalseForDifferentDomain(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://other.com/page';
        $this->assertFalse(Url::isRefererFrom('example.com'));
    }

    public function testIsRefererFromReturnsFalseWhenNoReferer(): void
    {
        $this->assertFalse(Url::isRefererFrom('example.com'));
    }

    public function testIsInternalRefererReturnsTrueForSameDomain(): void
    {
        $_SERVER['HTTP_HOST']    = 'mysite.com';
        $_SERVER['HTTP_REFERER'] = 'https://mysite.com/about';
        $this->assertTrue(Url::isInternalReferer());
    }

    public function testIsInternalRefererReturnsFalseForExternal(): void
    {
        $_SERVER['HTTP_HOST']    = 'mysite.com';
        $_SERVER['HTTP_REFERER'] = 'https://google.com';
        $this->assertFalse(Url::isInternalReferer());
    }

    public function testPreviousReturnsReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://example.com/prev';
        $this->assertSame('https://example.com/prev', Url::previous());
    }

    // =========================================================================
    // 9. Origin helpers
    // =========================================================================

    public function testOriginReturnsNullWhenNotSet(): void
    {
        $this->assertNull(Url::origin());
    }

    public function testOriginReturnsValue(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $this->assertSame('https://app.example.com', Url::origin());
    }

    public function testOriginDomainExtractsDomain(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $this->assertSame('app.example.com', Url::originDomain());
    }

    public function testOriginDomainReturnsNullWhenNoOrigin(): void
    {
        $this->assertNull(Url::originDomain());
    }

    public function testIsOriginFromIgnoresWwwPrefix(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://www.example.com';
        $this->assertTrue(Url::isOriginFrom('example.com'));
    }

    public function testIsOriginFromReturnsFalseForDifferentDomain(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://attacker.com';
        $this->assertFalse(Url::isOriginFrom('example.com'));
    }

    public function testIsSameOriginReturnsTrueWhenMatches(): void
    {
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $this->assertTrue(Url::isSameOrigin());
    }

    public function testIsSameOriginReturnsFalseWhenDifferent(): void
    {
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['HTTP_ORIGIN'] = 'https://other.com';
        $this->assertFalse(Url::isSameOrigin());
    }

    // =========================================================================
    // 10. isFromAllowedDomain() / sourceDomain() / source()
    // =========================================================================

    public function testIsFromAllowedDomainViaOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://partner.com';
        $this->assertTrue(Url::isFromAllowedDomain(['partner.com', 'friend.com']));
    }

    public function testIsFromAllowedDomainViaReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://friend.com/page';
        $this->assertTrue(Url::isFromAllowedDomain(['partner.com', 'friend.com']));
    }

    public function testIsFromAllowedDomainReturnsFalseForUnknown(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://attacker.com';
        $this->assertFalse(Url::isFromAllowedDomain(['partner.com']));
    }

    public function testIsFromAllowedDomainReturnsFalseWhenNeitherSet(): void
    {
        $this->assertFalse(Url::isFromAllowedDomain(['partner.com']));
    }

    public function testSourceDomainPrefersOriginOverReferer(): void
    {
        $_SERVER['HTTP_ORIGIN']  = 'https://origin.com';
        $_SERVER['HTTP_REFERER'] = 'https://referer.com';
        $this->assertSame('origin.com', Url::sourceDomain());
    }

    public function testSourceFallsBackToReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://referer.com/page';
        $this->assertSame('https://referer.com/page', Url::source());
    }

    // =========================================================================
    // 11. isAjax() / isMobile() / userAgent()
    // =========================================================================

    public function testIsAjaxReturnsTrueWithXmlHttpRequestHeader(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(Url::isAjax());
    }

    public function testIsAjaxIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $this->assertTrue(Url::isAjax());
    }

    public function testIsAjaxReturnsFalseWithoutHeader(): void
    {
        $this->assertFalse(Url::isAjax());
    }

    public function testIsMobileDetectsAndroid(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 12; Pixel 6)';
        $this->assertTrue(Url::isMobile());
    }

    public function testIsMobileDetectsIphone(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0)';
        $this->assertTrue(Url::isMobile());
    }

    public function testIsMobileDetectsIpad(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X)';
        $this->assertTrue(Url::isMobile());
    }

    public function testIsMobileReturnsFalseForDesktop(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/114';
        $this->assertFalse(Url::isMobile());
    }

    public function testUserAgent(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        $this->assertSame('TestAgent/1.0', Url::userAgent());
    }

    public function testUserAgentReturnsEmptyStringWhenNotSet(): void
    {
        $this->assertSame('', Url::userAgent());
    }

    // =========================================================================
    // 12. server()
    // =========================================================================

    public function testServerReturnsEntireArrayWhenNoKey(): void
    {
        $_SERVER['FOO'] = 'bar';
        $result = Url::server();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('FOO', $result);
    }

    public function testServerReturnsSpecificKey(): void
    {
        $_SERVER['SERVER_PORT'] = '80';
        $this->assertSame('80', Url::server('SERVER_PORT'));
    }

    public function testServerReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('default', Url::server('MISSING_KEY', 'default'));
    }

    public function testServerReturnsNullByDefaultForMissingKey(): void
    {
        $this->assertNull(Url::server('NO_SUCH_KEY'));
    }

    // =========================================================================
    // 13. input() — GET method
    // =========================================================================
    //
    // همه تست‌های input() از subprocess ایزوله استفاده می‌کنند.
    // دلیل: متغیر static داخل Url::input() پس از اولین فراخوانی
    // کش می‌شود و در کل process PHPUnit ثابت می‌ماند. تنها راه
    // تست واقعی هر حالت، اجرا در یک process PHP مجزا است.
    // =========================================================================

    public function testInputGetReturnsSingleQueryParam(): void
    {
        $result = $this->runInputFreshGet(['search' => 'php', 'page' => '3'], 'search');
        $this->assertSame('php', $result);
    }

    public function testInputGetReturnsAnotherParam(): void
    {
        $result = $this->runInputFreshGet(['search' => 'php', 'page' => '3'], 'page');
        $this->assertSame('3', $result);
    }

    public function testInputGetReturnsNullForMissingKey(): void
    {
        $result = $this->runInputFreshGet([], 'missing');
        $this->assertNull($result);
    }

    public function testInputGetReturnsProvidedDefault(): void
    {
        $result = $this->runInputFreshGetWithDefault([], 'missing', 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function testInputGetReturnsAllWhenKeyIsNull(): void
    {
        $result = $this->runInputFreshGet(['a' => '1', 'b' => '2'], null);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('b', $result);
    }

    public function testInputGetEmptyQueryReturnsEmptyArray(): void
    {
        $result = $this->runInputFreshGet([], null);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // 14. input() — POST (form-urlencoded)
    // =========================================================================

    public function testInputPostReadsNameFromPost(): void
    {
        $result = $this->runInputFreshPost(['username' => 'ali', 'password' => 'secret'], 'username');
        $this->assertSame('ali', $result);
    }

    public function testInputPostReadsPasswordFromPost(): void
    {
        $result = $this->runInputFreshPost(['username' => 'ali', 'password' => 'secret'], 'password');
        $this->assertSame('secret', $result);
    }

    public function testInputPostReturnsAllFields(): void
    {
        $result = $this->runInputFreshPost(['first' => 'John', 'last' => 'Doe'], null);
        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('last',  $result);
    }

    public function testInputPostReturnsNullForMissingField(): void
    {
        $result = $this->runInputFreshPost(['name' => 'test'], 'age');
        $this->assertNull($result);
    }

    public function testInputPostReturnsIntegerDefault(): void
    {
        $result = $this->runInputFreshPostWithDefault(['name' => 'test'], 'age', 0);
        $this->assertSame(0, $result);
    }

    // =========================================================================
    // 15. input() — POST JSON  [subprocess ایزوله برای کش‌زدایی]
    // =========================================================================

    public function testInputPostJsonParsesSimpleBody(): void
    {
        $payload = ['title' => 'Hello', 'published' => true];
        $result  = $this->runInputFresh('POST', 'application/json', json_encode($payload));
        $this->assertSame('Hello', $result['title']);
        $this->assertTrue($result['published']);
    }

    public function testInputPostJsonParsesUnicodeContent(): void
    {
        $payload = ['title' => 'سلام دنیا', 'lang' => 'fa'];
        $result  = $this->runInputFresh('POST', 'application/json', json_encode($payload));
        $this->assertSame('سلام دنیا', $result['title']);
        $this->assertSame('fa', $result['lang']);
    }

    public function testInputPostJsonParsesNestedObject(): void
    {
        $payload = ['user' => ['name' => 'رضا', 'age' => 30]];
        $result  = $this->runInputFresh('POST', 'application/json', json_encode($payload));
        $this->assertIsArray($result['user']);
        $this->assertSame('رضا', $result['user']['name']);
        $this->assertSame(30, $result['user']['age']);
    }

    public function testInputPostJsonParsesArray(): void
    {
        $payload = ['tags' => ['php', 'laravel', 'webrium']];
        $result  = $this->runInputFresh('POST', 'application/json', json_encode($payload));
        $this->assertCount(3, $result['tags']);
        $this->assertContains('webrium', $result['tags']);
    }

    public function testInputPostJsonHandlesNumericTypes(): void
    {
        $payload = ['count' => 42, 'ratio' => 3.14, 'active' => true, 'name' => null];
        $result  = $this->runInputFresh('POST', 'application/json', json_encode($payload));
        $this->assertSame(42,   $result['count']);
        $this->assertSame(3.14, $result['ratio']);
        $this->assertTrue($result['active']);
        $this->assertNull($result['name']);
    }

    public function testInputPostJsonHandlesContentTypeWithCharset(): void
    {
        // سرورها گاهی charset اضافه می‌کنند: application/json; charset=utf-8
        $payload = ['key' => 'value'];
        $result  = $this->runInputFresh('POST', 'application/json; charset=utf-8', json_encode($payload));
        $this->assertSame('value', $result['key']);
    }

    public function testInputPostJsonReturnsEmptyArrayForEmptyObject(): void
    {
        $result = $this->runInputFresh('POST', 'application/json', '{}');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // 16. input() — PUT form-urlencoded
    // =========================================================================

    public function testInputPutParsesSimpleUrlencodedBody(): void
    {
        $body   = 'name=Ahmad&age=25';
        $result = $this->runInputFresh('PUT', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('Ahmad', $result['name']);
        $this->assertSame('25',   $result['age']);
    }

    public function testInputPutParsesEmailWithUrlEncoding(): void
    {
        $body   = 'email=ahmad%40example.com';
        $result = $this->runInputFresh('PUT', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('ahmad@example.com', $result['email']);
    }

    public function testInputPutEmptyBodyReturnsEmptyArray(): void
    {
        $result = $this->runInputFresh('PUT', 'application/x-www-form-urlencoded', '');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testInputPutReturnsDefaultForMissingKey(): void
    {
        $result = $this->runInputFreshKeyed('PUT', 'application/x-www-form-urlencoded', 'foo=bar', 'missing', 'N/A');
        $this->assertSame('N/A', $result);
    }

    // =========================================================================
    // 17. input() — PUT JSON
    // =========================================================================

    public function testInputPutJsonParsesBody(): void
    {
        $payload = ['status' => 'active', 'role' => 'admin'];
        $result  = $this->runInputFresh('PUT', 'application/json', json_encode($payload));
        $this->assertSame('active', $result['status']);
        $this->assertSame('admin',  $result['role']);
    }

    public function testInputPutJsonParsesNestedData(): void
    {
        $payload = ['address' => ['city' => 'Tehran', 'zip' => '1234567']];
        $result  = $this->runInputFresh('PUT', 'application/json', json_encode($payload));
        $this->assertSame('Tehran',  $result['address']['city']);
        $this->assertSame('1234567', $result['address']['zip']);
    }

    public function testInputPutJsonHandlesBooleanFalse(): void
    {
        $payload = ['is_verified' => false];
        $result  = $this->runInputFresh('PUT', 'application/json', json_encode($payload));
        $this->assertFalse($result['is_verified']);
    }

    // =========================================================================
    // 18. input() — PATCH form-urlencoded
    // =========================================================================

    public function testInputPatchParsesUrlencodedBody(): void
    {
        $body   = 'bio=developer&city=Tehran';
        $result = $this->runInputFresh('PATCH', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('developer', $result['bio']);
        $this->assertSame('Tehran',    $result['city']);
    }

    public function testInputPatchPartialUpdateHasOnlyPresentFields(): void
    {
        // PATCH معمولاً فقط فیلدهای تغییریافته را ارسال می‌کند
        $body   = 'email=new%40example.com';
        $result = $this->runInputFresh('PATCH', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('new@example.com', $result['email']);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function testInputPatchEmptyBodyReturnsEmptyArray(): void
    {
        $result = $this->runInputFresh('PATCH', 'application/x-www-form-urlencoded', '');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testInputPatchReturnsDefaultForMissingKey(): void
    {
        $result = $this->runInputFreshKeyed('PATCH', 'application/x-www-form-urlencoded', 'city=Tehran', 'name', 'unknown');
        $this->assertSame('unknown', $result);
    }

    // =========================================================================
    // 19. input() — PATCH JSON
    // =========================================================================

    public function testInputPatchJsonParsesBody(): void
    {
        $payload = ['price' => 99.99, 'in_stock' => false];
        $result  = $this->runInputFresh('PATCH', 'application/json', json_encode($payload));
        $this->assertSame(99.99, $result['price']);
        $this->assertFalse($result['in_stock']);
    }

    public function testInputPatchJsonSupportsFlatUpdate(): void
    {
        $payload = ['title' => 'New Title'];
        $result  = $this->runInputFresh('PATCH', 'application/json', json_encode($payload));
        $this->assertSame('New Title', $result['title']);
        $this->assertArrayNotHasKey('body', $result);
    }

    // =========================================================================
    // 20. input() — DELETE form-urlencoded
    // =========================================================================

    public function testInputDeleteParsesUrlencodedBody(): void
    {
        $body   = 'reason=obsolete&confirmed=1';
        $result = $this->runInputFresh('DELETE', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('obsolete', $result['reason']);
        $this->assertSame('1',         $result['confirmed']);
    }

    public function testInputDeleteEmptyBodyReturnsEmptyArray(): void
    {
        $result = $this->runInputFresh('DELETE', 'application/x-www-form-urlencoded', '');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testInputDeleteReturnsDefaultForMissingKey(): void
    {
        $result = $this->runInputFreshKeyed('DELETE', 'application/json', '{}', 'missing_key', 'default_val');
        $this->assertSame('default_val', $result);
    }

    // =========================================================================
    // 21. input() — DELETE JSON
    // =========================================================================

    public function testInputDeleteJsonParsesBody(): void
    {
        $payload = ['cascade' => true, 'archive' => false];
        $result  = $this->runInputFresh('DELETE', 'application/json', json_encode($payload));
        $this->assertTrue($result['cascade']);
        $this->assertFalse($result['archive']);
    }

    public function testInputDeleteJsonWithIdField(): void
    {
        $payload = ['id' => 42, 'soft_delete' => true];
        $result  = $this->runInputFresh('DELETE', 'application/json', json_encode($payload));
        $this->assertSame(42, $result['id']);
        $this->assertTrue($result['soft_delete']);
    }

    // =========================================================================
    // 22. input() — Edge cases & special scenarios
    // =========================================================================

    public function testInputGetReturnsNullForUnknownKey(): void
    {
        $result = $this->runInputFreshGet(['foo' => 'bar'], 'baz');
        $this->assertNull($result);
    }

    public function testInputPutUrlencodedWithSpecialCharsAndUnicode(): void
    {
        $body   = 'message=Hello+World%21&lang=%D9%81%D8%A7%D8%B1%D8%B3%DB%8C';
        $result = $this->runInputFresh('PUT', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('Hello World!', $result['message']);
        $this->assertSame('فارسی',         $result['lang']);
    }

    public function testInputPutUrlencodedWithArrayNotation(): void
    {
        // tags[]=php&tags[]=laravel  →  باید آرایه برگردد
        $body   = 'tags%5B%5D=php&tags%5B%5D=laravel';
        $result = $this->runInputFresh('PUT', 'application/x-www-form-urlencoded', $body);
        $this->assertIsArray($result['tags']);
        $this->assertContains('php',     $result['tags']);
        $this->assertContains('laravel', $result['tags']);
    }

    public function testInputPatchUrlencodedWithNestedKeys(): void
    {
        // user[name]=Ali&user[age]=28
        $body   = 'user%5Bname%5D=Ali&user%5Bage%5D=28';
        $result = $this->runInputFresh('PATCH', 'application/x-www-form-urlencoded', $body);
        $this->assertSame('Ali', $result['user']['name']);
        $this->assertSame('28',  $result['user']['age']);
    }

    public function testInputDeleteJsonWithListOfIds(): void
    {
        $payload = ['ids' => [1, 2, 3, 4]];
        $result  = $this->runInputFresh('DELETE', 'application/json', json_encode($payload));
        $this->assertCount(4, $result['ids']);
        $this->assertContains(3, $result['ids']);
    }

    public function testInputPostJsonWithDeeplyNestedStructure(): void
    {
        $payload = ['order' => ['items' => [['id' => 1, 'qty' => 2], ['id' => 5, 'qty' => 1]]]];
        $result  = $this->runInputFresh('POST', 'application/json', json_encode($payload));
        $this->assertCount(2, $result['order']['items']);
        $this->assertSame(1, $result['order']['items'][0]['id']);
        $this->assertSame(1, $result['order']['items'][1]['qty']);
    }

    public function testInputPutJsonWithNullableFields(): void
    {
        $payload = ['description' => null, 'title' => 'Test'];
        $result  = $this->runInputFresh('PUT', 'application/json', json_encode($payload));
        $this->assertNull($result['description']);
        $this->assertSame('Test', $result['title']);
    }

    // =========================================================================
    // Test Infrastructure (private helpers)
    // =========================================================================

    /**
     * اجرای Url::input() در subprocess ایزوله برای متد GET.
     */
    private function runInputFreshGet(array $getParams, ?string $key): mixed
    {
        $debugPath  = addslashes(realpath(__DIR__ . '/../src/Debug.php'));
        $urlPath    = addslashes(realpath(__DIR__ . '/../src/Url.php'));
        $paramsJson = json_encode($getParams, JSON_UNESCAPED_UNICODE);
        $callExpr   = $key !== null ? 'Url::input(' . var_export($key, true) . ')' : 'Url::input()';

        $script = '<?php' . "\n"
            . 'require_once "' . $debugPath . '";' . "\n"
            . 'require_once "' . $urlPath . '";' . "\n"
            . 'use Webrium\Url;' . "\n"
            . '$_SERVER["REQUEST_METHOD"] = "GET";' . "\n"
            . '$_GET = json_decode(' . var_export($paramsJson, true) . ', true);' . "\n"
            . '$result = ' . $callExpr . ';' . "\n"
            . 'echo json_encode(["v"=>$result], JSON_UNESCAPED_UNICODE);' . "
";

        $scriptFile = tempnam(sys_get_temp_dir(), 'phpgettest_') . '.php';
        file_put_contents($scriptFile, $script);
        $output = (string) shell_exec('php ' . escapeshellarg($scriptFile) . ' 2>&1');
        @unlink($scriptFile);

        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded, "runInputFreshGet: subprocess error.\nOutput: $output");
        $this->assertArrayHasKey('v', $decoded, "runInputFreshGet: missing key 'v'.\nOutput: $output");
        return $decoded['v'];
    }

    /**
     * مثل runInputFreshGet اما با مقدار پیش‌فرض.
     */
    private function runInputFreshGetWithDefault(array $getParams, string $key, mixed $default): mixed
    {
        $debugPath   = addslashes(realpath(__DIR__ . '/../src/Debug.php'));
        $urlPath     = addslashes(realpath(__DIR__ . '/../src/Url.php'));
        $paramsJson  = json_encode($getParams, JSON_UNESCAPED_UNICODE);
        $defaultExpr = var_export($default, true);

        $script = '<?php' . "\n"
            . 'require_once "' . $debugPath . '";' . "\n"
            . 'require_once "' . $urlPath . '";' . "\n"
            . 'use Webrium\Url;' . "\n"
            . '$_SERVER["REQUEST_METHOD"] = "GET";' . "\n"
            . '$_GET = json_decode(' . var_export($paramsJson, true) . ', true);' . "\n"
            . '$result = Url::input(' . var_export($key, true) . ', ' . $defaultExpr . ');' . "\n"
            . 'echo json_encode(["v"=>$result], JSON_UNESCAPED_UNICODE);' . "
";

        $scriptFile = tempnam(sys_get_temp_dir(), 'phpgettest_') . '.php';
        file_put_contents($scriptFile, $script);
        $output = (string) shell_exec('php ' . escapeshellarg($scriptFile) . ' 2>&1');
        @unlink($scriptFile);

        $decoded = json_decode($output, true);
        return $decoded['v'] ?? null;
    }

    /**
     * اجرای Url::input() در subprocess ایزوله برای متد POST form-urlencoded.
     */
    private function runInputFreshPost(array $postParams, ?string $key): mixed
    {
        $debugPath  = addslashes(realpath(__DIR__ . '/../src/Debug.php'));
        $urlPath    = addslashes(realpath(__DIR__ . '/../src/Url.php'));
        $paramsJson = json_encode($postParams, JSON_UNESCAPED_UNICODE);
        $callExpr   = $key !== null ? 'Url::input(' . var_export($key, true) . ')' : 'Url::input()';

        $script = '<?php' . "\n"
            . 'require_once "' . $debugPath . '";' . "\n"
            . 'require_once "' . $urlPath . '";' . "\n"
            . 'use Webrium\Url;' . "\n"
            . '$_SERVER["REQUEST_METHOD"] = "POST";' . "\n"
            . '$_SERVER["CONTENT_TYPE"]   = "application/x-www-form-urlencoded";' . "\n"
            . '$_POST = json_decode(' . var_export($paramsJson, true) . ', true);' . "\n"
            . '$result = ' . $callExpr . ';' . "\n"
            . 'echo json_encode(["v"=>$result], JSON_UNESCAPED_UNICODE);' . "
";

        $scriptFile = tempnam(sys_get_temp_dir(), 'phpposttest_') . '.php';
        file_put_contents($scriptFile, $script);
        $output = (string) shell_exec('php ' . escapeshellarg($scriptFile) . ' 2>&1');
        @unlink($scriptFile);

        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded, "runInputFreshPost: subprocess error.\nOutput: $output");
        $this->assertArrayHasKey('v', $decoded, "runInputFreshPost: missing key 'v'.\nOutput: $output");
        return $decoded['v'];
    }

    /**
     * مثل runInputFreshPost اما با مقدار پیش‌فرض.
     */
    private function runInputFreshPostWithDefault(array $postParams, string $key, mixed $default): mixed
    {
        $debugPath   = addslashes(realpath(__DIR__ . '/../src/Debug.php'));
        $urlPath     = addslashes(realpath(__DIR__ . '/../src/Url.php'));
        $paramsJson  = json_encode($postParams, JSON_UNESCAPED_UNICODE);
        $defaultExpr = var_export($default, true);

        $script = '<?php' . "\n"
            . 'require_once "' . $debugPath . '";' . "\n"
            . 'require_once "' . $urlPath . '";' . "\n"
            . 'use Webrium\Url;' . "\n"
            . '$_SERVER["REQUEST_METHOD"] = "POST";' . "\n"
            . '$_SERVER["CONTENT_TYPE"]   = "application/x-www-form-urlencoded";' . "\n"
            . '$_POST = json_decode(' . var_export($paramsJson, true) . ', true);' . "\n"
            . '$result = Url::input(' . var_export($key, true) . ', ' . $defaultExpr . ');' . "\n"
            . 'echo json_encode(["v"=>$result], JSON_UNESCAPED_UNICODE);' . "
";

        $scriptFile = tempnam(sys_get_temp_dir(), 'phpposttest_') . '.php';
        file_put_contents($scriptFile, $script);
        $output = (string) shell_exec('php ' . escapeshellarg($scriptFile) . ' 2>&1');
        @unlink($scriptFile);

        $decoded = json_decode($output, true);
        return $decoded['v'] ?? null;
    }

    /**
     * اجرای Url::input() در یک subprocess PHP کاملاً ایزوله.
     *
     * چون متغیر static داخل input() کش می‌شود و از طریق Reflection
     * قابل ریست نیست، هر تست JSON/urlencoded را در یک فرایند مجزا اجرا می‌کنیم.
     * بدنه درخواست در یک فایل موقت ذخیره می‌شود تا مشکل escape کاراکترهای
     * خاص (فارسی، کاراکترهای JSON) از بین برود.
     *
     * @param  string $method      HTTP method (POST, PUT, PATCH, DELETE)
     * @param  string $contentType Content-Type header value
     * @param  string $body        Raw request body
     * @return array               Parsed input array
     */
    private function runInputFresh(string $method, string $contentType, string $body): array
    {
        $output  = $this->execInputSubprocess($method, $contentType, $body, null, null);
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded, "runInputFresh: subprocess error.\nOutput: $output");
        $this->assertArrayHasKey('v', $decoded, "runInputFresh: missing 'v' key.\nOutput: $output");
        return $decoded['v'];
    }

    /**
     * مثل runInputFresh اما برای یک کلید خاص با مقدار پیش‌فرض.
     */
    private function runInputFreshKeyed(
        string $method,
        string $contentType,
        string $body,
        string $key,
        mixed  $default
    ): mixed {
        $output  = $this->execInputSubprocess($method, $contentType, $body, $key, $default);
        $decoded = json_decode($output, true);
        return $decoded['v'] ?? null;
    }

    /**
     * ساخت و اجرای یک اسکریپت PHP موقت در subprocess ایزوله.
     * از فایل‌های موقت برای بدنه و اسکریپت استفاده می‌کند تا
     * مشکل escape کاراکترهای خاص حل شود.
     */
    private function execInputSubprocess(
        string  $method,
        string  $contentType,
        string  $body,
        ?string $key,
        mixed   $default
    ): string {
        // نوشتن بدنه در فایل موقت (بدون هیچ escape‌ای)
        $bodyFile = tempnam(sys_get_temp_dir(), 'phpbody_');
        file_put_contents($bodyFile, $body);

        $debugPath = addslashes(realpath(__DIR__ . '/../src/Debug.php'));
        $urlPath   = addslashes(realpath(__DIR__ . '/../src/Url.php'));

        // تعیین عبارت فراخوانی input()
        if ($key !== null) {
            $defaultExpr = var_export($default, true);
            $callExpr    = "Url::input(" . var_export($key, true) . ", $defaultExpr)";
        } else {
            $callExpr = 'Url::input()';
        }

        // اسکریپت PHP با جایگزینی مستقیم متغیرها (بدون heredoc تا مشکل escape نداشته باشیم)
        $script = '<?php' . "\n"
            . 'require_once "' . $debugPath . '";' . "\n"
            . 'require_once "' . $urlPath . '";' . "\n"
            . 'use Webrium\Url;' . "\n"
            // Stream wrapper برای override کردن php://input
            . 'class PhpInputWrapper {' . "\n"
            . '    private static $content = "";' . "\n"
            . '    private $pos = 0;' . "\n"
            . '    public $context;' . "\n"
            . '    public static function setContent($c) { self::$content = $c; }' . "\n"
            . '    public function stream_open($path, $mode, $opts, &$opened) { $this->pos = 0; return $path === "php://input"; }' . "\n"
            . '    public function stream_read($count) { $chunk = substr(self::$content, $this->pos, $count); $this->pos += strlen($chunk); return $chunk; }' . "\n"
            . '    public function stream_eof() { return $this->pos >= strlen(self::$content); }' . "\n"
            . '    public function stream_stat() { return []; }' . "\n"
            . '    public function stream_close() {}' . "\n"
            . '}' . "\n"
            . 'stream_wrapper_unregister("php");' . "\n"
            . 'stream_wrapper_register("php", "PhpInputWrapper");' . "\n"
            // بارگذاری بدنه از فایل موقت
            . 'PhpInputWrapper::setContent(file_get_contents(' . var_export($bodyFile, true) . '));' . "\n"
            . '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';' . "\n"
            . '$_SERVER["CONTENT_TYPE"]   = ' . var_export($contentType, true) . ';' . "\n"
            . '$result = ' . $callExpr . ';' . "\n"
            . 'stream_wrapper_restore("php");' . "\n"
            . 'echo json_encode(["v"=>$result], JSON_UNESCAPED_UNICODE);' . "
";

        $scriptFile = tempnam(sys_get_temp_dir(), 'phptest_') . '.php';
        file_put_contents($scriptFile, $script);

        $output = shell_exec('php ' . escapeshellarg($scriptFile) . ' 2>&1');

        // پاکسازی فایل‌های موقت
        @unlink($scriptFile);
        @unlink($bodyFile);

        return (string) $output;
    }
}