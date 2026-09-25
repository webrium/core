<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Webrium\App;
use Webrium\Url;
use Webrium\Vite;

/**
 * Unit Tests for Webrium\Vite
 *
 * Coverage:
 *  - collectCss(): direct css, transitive css via imports, dedup across
 *    branches, cycle safety, unknown manifest key
 *  - assets() / renderProductionTags(): end-to-end tag generation from a
 *    manifest.json fixture, including the case where an entry owns no
 *    direct "css" key but pulls it in through an imported chunk (the
 *    scenario Rollup produces when two entries import the same CSS module
 *    and one of them collapses into a shared chunk of the other)
 */
class ViteTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * A Vite instance built via reflection, bypassing the protected
     * constructor (host/port detection, dev-server probing). Used for
     * exercising collectCss() in isolation, without any App/Url/network
     * dependency.
     */
    private function bareInstance(): Vite
    {
        return (new ReflectionClass(Vite::class))->newInstanceWithoutConstructor();
    }

    private function collectCss(Vite $vite, array $manifest, string $entryPoint): array
    {
        $method = new ReflectionMethod(Vite::class, 'collectCss');
        $method->setAccessible(true);

        $visited = [];
        return $method->invokeArgs($vite, [$manifest, $entryPoint, &$visited]);
    }

    // =========================================================================
    // 1. collectCss()
    // =========================================================================

    public function testCollectsDirectCss(): void
    {
        $manifest = [
            'app.js' => ['file' => 'app.js', 'css' => ['app.css']],
        ];

        $this->assertSame(
            ['app.css'],
            $this->collectCss($this->bareInstance(), $manifest, 'app.js')
        );
    }

    /**
     * Reproduces the real-world bug: an entry with no direct "css" key,
     * whose only styles come from a sibling chunk it imports.
     */
    public function testCollectsCssTransitivelyThroughImports(): void
    {
        $manifest = [
            'admin.js' => ['file' => 'admin.js', 'imports' => ['app.js']],
            'app.js'   => ['file' => 'app.js', 'css' => ['app.css']],
        ];

        $this->assertSame(
            ['app.css'],
            $this->collectCss($this->bareInstance(), $manifest, 'admin.js')
        );
    }

    public function testDedupesCssSharedAcrossMultipleImportBranches(): void
    {
        $manifest = [
            'admin.js' => ['file' => 'admin.js', 'imports' => ['vendor-a.js', 'vendor-b.js']],
            'vendor-a.js' => ['css' => ['shared.css']],
            'vendor-b.js' => ['css' => ['shared.css']],
        ];

        $this->assertSame(
            ['shared.css'],
            $this->collectCss($this->bareInstance(), $manifest, 'admin.js')
        );
    }

    public function testCombinesOwnCssWithImportedCss(): void
    {
        $manifest = [
            'admin.js' => ['file' => 'admin.js', 'css' => ['admin.css'], 'imports' => ['app.js']],
            'app.js'   => ['css' => ['app.css']],
        ];

        $this->assertSame(
            ['admin.css', 'app.css'],
            $this->collectCss($this->bareInstance(), $manifest, 'admin.js')
        );
    }

    public function testCyclicImportsDoNotCauseInfiniteRecursion(): void
    {
        $manifest = [
            'a.js' => ['css' => ['a.css'], 'imports' => ['b.js']],
            'b.js' => ['css' => ['b.css'], 'imports' => ['a.js']],
        ];

        $this->assertSame(
            ['a.css', 'b.css'],
            $this->collectCss($this->bareInstance(), $manifest, 'a.js')
        );
    }

    public function testUnknownManifestKeyReturnsEmptyArray(): void
    {
        $manifest = ['app.js' => ['css' => ['app.css']]];

        $this->assertSame(
            [],
            $this->collectCss($this->bareInstance(), $manifest, 'missing.js')
        );
    }

    public function testEntryWithNeitherCssNorImportsReturnsEmptyArray(): void
    {
        $manifest = ['app.js' => ['file' => 'app.js']];

        $this->assertSame(
            [],
            $this->collectCss($this->bareInstance(), $manifest, 'app.js')
        );
    }

    // =========================================================================
    // 2. assets() / renderProductionTags() end-to-end
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAssetsIncludesTransitiveCssForImportOnlyEntry(): void
    {
        $vite = $this->bootProductionVite([
            'resources/js/admin.js' => [
                'file' => 'assets/admin-hash.js',
                'name' => 'admin',
                'src' => 'resources/js/admin.js',
                'isEntry' => true,
                'imports' => ['resources/js/app.js'],
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app-hash.js',
                'name' => 'app',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
                'css' => ['assets/app-hash.css'],
            ],
        ]);

        $html = $vite->assets('resources/js/admin.js');

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/build/assets/app-hash.css">',
            $html
        );
        $this->assertStringContainsString(
            '<script type="module" src="/build/assets/admin-hash.js"></script>',
            $html
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAssetsStillWorksForEntryWithOwnDirectCss(): void
    {
        $vite = $this->bootProductionVite([
            'resources/js/app.js' => [
                'file' => 'assets/app-hash.js',
                'isEntry' => true,
                'css' => ['assets/app-hash.css'],
            ],
        ]);

        $html = $vite->assets('resources/js/app.js');

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/build/assets/app-hash.css">',
            $html
        );
        $this->assertStringContainsString(
            '<script type="module" src="/build/assets/app-hash.js"></script>',
            $html
        );
    }

    /**
     * Boots a fresh Vite singleton against a temp project root containing
     * the given manifest, forced into production mode (isDev = false) so
     * the test is deterministic regardless of whether a real Vite dev
     * server happens to be listening on the default port.
     */
    private function bootProductionVite(array $manifest): Vite
    {
        $root = sys_get_temp_dir() . '/webrium_vite_test_' . uniqid();
        mkdir($root . '/public/build/.vite', 0755, true);
        file_put_contents(
            $root . '/public/build/.vite/manifest.json',
            json_encode($manifest)
        );

        App::setRootPath($root);
        $_SERVER['DOCUMENT_ROOT'] = $root;
        Url::reset();

        $vite = Vite::getInstance();
        $vite->setBasePath($root);

        $isDev = new ReflectionProperty(Vite::class, 'isDev');
        $isDev->setAccessible(true);
        $isDev->setValue($vite, false);

        return $vite;
    }
}
