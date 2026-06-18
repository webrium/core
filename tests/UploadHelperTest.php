<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Webrium\Helpers\MimeMap;
use Webrium\Helpers\UploadHelper;
use Webrium\Upload;

/**
 * Unit Tests for Webrium\Helpers\UploadHelper
 *
 * Coverage:
 *  - Factory return types (single instance / array / null for missing input)
 *  - Each category configures the correct extension allow-list (from MimeMap)
 *  - Each category applies its default size cap
 *  - MIME/extension consistency enforcement stays ON
 *  - Dangerous-extension blocking stays ON
 *  - SVG is excluded from the image category
 *  - Defaults (such as the size cap) remain overridable through the fluent API
 *
 * These tests inspect the configuration that each factory wires onto the
 * returned Upload instance (via reflection) rather than calling save(), so they
 * do not require a real HTTP upload. The validation behaviour itself is covered
 * by UploadTest.
 */
class UploadHelperTest extends TestCase
{
    /**
     * @var array<string,int> Expected default size caps (MB) per category.
     */
    private const EXPECTED_MAX_MB = [
        'image'    => 5,
        'video'    => 200,
        'audio'    => 50,
        'pdf'      => 20,
        'document' => 25,
        'archive'  => 100,
    ];

    protected function tearDown(): void
    {
        $_FILES = [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fakeUpload(string $field, string $name = 'file.dat'): void
    {
        $_FILES[$field] = [
            'name'     => $name,
            'tmp_name' => '/tmp/php_nonexistent',
            'type'     => 'application/octet-stream',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1024,
        ];
    }

    private function fakeMultiUpload(string $field, int $count = 2): void
    {
        $_FILES[$field] = [
            'name'     => array_fill(0, $count, 'file.dat'),
            'tmp_name' => array_fill(0, $count, '/tmp/php_nonexistent'),
            'type'     => array_fill(0, $count, 'application/octet-stream'),
            'error'    => array_fill(0, $count, UPLOAD_ERR_OK),
            'size'     => array_fill(0, $count, 1024),
        ];
    }

    private function readProperty(Upload $upload, string $name): mixed
    {
        $ref = new ReflectionProperty(Upload::class, $name);
        $ref->setAccessible(true);
        return $ref->getValue($upload);
    }

    // =========================================================================
    // 1. Return types
    // =========================================================================

    public function testFactoryReturnsNullWhenInputMissing(): void
    {
        unset($_FILES['avatar']);
        $this->assertNull(UploadHelper::image('avatar'));
    }

    public function testFactoryReturnsUploadInstanceForSingleFile(): void
    {
        $this->fakeUpload('avatar');
        $this->assertInstanceOf(Upload::class, UploadHelper::image('avatar'));
    }

    public function testFactoryReturnsArrayForMultipleFiles(): void
    {
        $this->fakeMultiUpload('gallery', 3);
        $result = UploadHelper::image('gallery');
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(Upload::class, $result);
    }

    // =========================================================================
    // 2. Extension allow-list per category
    // =========================================================================

    /** @dataProvider categoryProvider */
    public function testCategoryConfiguresExpectedExtensions(string $category): void
    {
        $this->fakeUpload($category);
        $upload = UploadHelper::$category($category);

        $this->assertSame(
            MimeMap::extensionsForCategory($category),
            $this->readProperty($upload, 'allowedExtensions'),
            "Category '$category' must use the MimeMap extension list"
        );
    }

    public static function categoryProvider(): array
    {
        return [
            'image'    => ['image'],
            'video'    => ['video'],
            'audio'    => ['audio'],
            'pdf'      => ['pdf'],
            'document' => ['document'],
            'archive'  => ['archive'],
        ];
    }

    public function testImageCategoryContainsCommonFormats(): void
    {
        $this->fakeUpload('image');
        $exts = $this->readProperty(UploadHelper::image('image'), 'allowedExtensions');

        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $this->assertContains($ext, $exts);
        }
    }

    // =========================================================================
    // 3. Default size caps
    // =========================================================================

    /** @dataProvider categoryProvider */
    public function testCategoryAppliesDefaultSizeCap(string $category): void
    {
        $this->fakeUpload($category);
        $upload = UploadHelper::$category($category);

        $expectedBytes = self::EXPECTED_MAX_MB[$category] * 1024 * 1024;
        $this->assertSame(
            $expectedBytes,
            $this->readProperty($upload, 'maxSize'),
            "Category '$category' must apply its default size cap"
        );
    }

    // =========================================================================
    // 4. Security flags stay enabled
    // =========================================================================

    /** @dataProvider categoryProvider */
    public function testMimeConsistencyEnforcementStaysOn(string $category): void
    {
        $this->fakeUpload($category);
        $upload = UploadHelper::$category($category);

        $this->assertTrue(
            $this->readProperty($upload, 'enforceMimeConsistency'),
            "Category '$category' must keep MIME consistency enforcement on"
        );
    }

    /** @dataProvider categoryProvider */
    public function testDangerousExtensionBlockingStaysOn(string $category): void
    {
        $this->fakeUpload($category);
        $upload = UploadHelper::$category($category);

        $this->assertTrue(
            $this->readProperty($upload, 'blockDangerousExtensions'),
            "Category '$category' must keep dangerous-extension blocking on"
        );
    }

    // =========================================================================
    // 5. SVG excluded from image
    // =========================================================================

    public function testImageCategoryExcludesSvg(): void
    {
        $this->fakeUpload('image');
        $exts = $this->readProperty(UploadHelper::image('image'), 'allowedExtensions');
        $this->assertNotContains('svg', $exts);
    }

    // =========================================================================
    // 6. Defaults remain overridable
    // =========================================================================

    public function testSizeCapCanBeOverridden(): void
    {
        $this->fakeUpload('image');
        $upload = UploadHelper::image('image')->maxMB(500);

        $this->assertSame(
            500 * 1024 * 1024,
            $this->readProperty($upload, 'maxSize')
        );
    }

    public function testExtensionListCanBeNarrowed(): void
    {
        $this->fakeUpload('image');
        $upload = UploadHelper::image('image')->allowExtension(['png']);

        $this->assertSame(['png'], $this->readProperty($upload, 'allowedExtensions'));
    }

    public function testFactoryReturnsFluentInstance(): void
    {
        $this->fakeUpload('image');
        $upload = UploadHelper::image('image');
        $this->assertSame($upload, $upload->maxMB(10));
    }

    // =========================================================================
    // 7. Multiple-file uploads are each configured
    // =========================================================================

    public function testEachInstanceInArrayIsConfigured(): void
    {
        $this->fakeMultiUpload('gallery', 2);
        $result = UploadHelper::image('gallery');

        foreach ($result as $upload) {
            $this->assertSame(
                MimeMap::extensionsForCategory('image'),
                $this->readProperty($upload, 'allowedExtensions')
            );
            $this->assertSame(
                self::EXPECTED_MAX_MB['image'] * 1024 * 1024,
                $this->readProperty($upload, 'maxSize')
            );
        }
    }
}