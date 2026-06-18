<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Webrium\Upload;

/**
 * Unit Tests for Webrium\Upload
 *
 * Coverage:
 *  - Construction via fromInput (single / multiple / missing inputs)
 *  - Fluent configuration API (maxKB / maxMB / asName / useRandomName …)
 *  - Metadata accessors (getOriginalName / getExtension / getSize / getMimeType)
 *  - Validation: upload error codes, empty-file guard, size limit
 *  - Validation: extension allow-list (string + array forms, case, leading dot)
 *  - Validation: real MIME type detection (finfo, not browser-reported type)
 *  - Security: dangerous-extension blacklist (php, svg, exe …) and opt-out
 *  - Security: extension/MIME consistency — anti-spoofing core defence
 *  - Security: filename sanitization (path traversal, null byte, control chars,
 *    double extension, leading/trailing dots)
 *  - Helpers: ensureNameLength, generateUniqueName
 *
 * Test double
 * ───────────
 * PHP's native is_uploaded_file() only returns true inside a real multipart
 * HTTP request, so it can never be true in CLI tests. Upload exposes a single
 * protected seam, isUploadedFile(), for exactly this reason. UploadStub
 * overrides only that one method; every other line of Upload runs unchanged,
 * so these tests exercise the real validation, sanitization and naming logic.
 */
class UploadTest extends TestCase
{
    private static string $pngFile;
    private static string $jpgFile;
    private static string $phpFile;
    private static string $spoofedJpgFile;
    private static string $emptyFile;

    public static function setUpBeforeClass(): void
    {
        $tmp = sys_get_temp_dir();

        self::$pngFile = $tmp . '/webrium_upload_test.png';
        file_put_contents(self::$pngFile, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC' .
            '0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        self::$jpgFile = $tmp . '/webrium_upload_test.jpg';
        file_put_contents(self::$jpgFile, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKD' .
            'BQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hy' .
            'c5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIy' .
            'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARC' .
            'AABAAEDASIAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAAAAAHCP/EA' .
            'BYRAQEBAAAAAAAAAAAAAAAAAAABEf/EABQBAQAAAAAAAAAAAAAAAAAAAAD' .
            '/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwABmX/9k='
        ));

        self::$phpFile = $tmp . '/webrium_upload_test_shell.php';
        file_put_contents(self::$phpFile, "<?php system(\$_GET['cmd']); ?>");

        self::$spoofedJpgFile = $tmp . '/webrium_upload_test_evil.jpg';
        file_put_contents(self::$spoofedJpgFile, "<?php system(\$_GET['cmd']); ?>");

        self::$emptyFile = $tmp . '/webrium_upload_test_empty.bin';
        file_put_contents(self::$emptyFile, '');
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([
            self::$pngFile, self::$jpgFile, self::$phpFile,
            self::$spoofedJpgFile, self::$emptyFile,
        ] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Fixtures / helpers
    // -------------------------------------------------------------------------

    private function upload(array $override = []): Upload
    {
        return UploadStub::fromArray(array_merge([
            'name'     => 'photo.png',
            'tmp_name' => self::$pngFile,
            'type'     => 'image/png',
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize(self::$pngFile),
        ], $override));
    }

    private function callProtected(Upload $obj, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(Upload::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($obj, ...$args);
    }

    private function readProperty(Upload $obj, string $name): mixed
    {
        $ref = new ReflectionProperty(Upload::class, $name);
        $ref->setAccessible(true);
        return $ref->getValue($obj);
    }

    private function writeProperty(Upload $obj, string $name, mixed $value): void
    {
        $ref = new ReflectionProperty(Upload::class, $name);
        $ref->setAccessible(true);
        $ref->setValue($obj, $value);
    }

    // =========================================================================
    // 1. Construction via fromInput
    // =========================================================================

    public function testFromInputReturnsNullWhenInputMissing(): void
    {
        unset($_FILES['missing_field']);
        $this->assertNull(Upload::fromInput('missing_field'));
    }

    public function testFromInputReturnsNullForNoFileUpload(): void
    {
        $_FILES['empty_field'] = [
            'name' => '', 'tmp_name' => '', 'type' => '',
            'error' => UPLOAD_ERR_NO_FILE, 'size' => 0,
        ];
        $this->assertNull(Upload::fromInput('empty_field'));
        unset($_FILES['empty_field']);
    }

    public function testFromInputReturnsSingleInstance(): void
    {
        $_FILES['avatar'] = [
            'name' => 'a.png', 'tmp_name' => self::$pngFile,
            'type' => 'image/png', 'error' => UPLOAD_ERR_OK, 'size' => 100,
        ];
        $this->assertInstanceOf(Upload::class, Upload::fromInput('avatar'));
        unset($_FILES['avatar']);
    }

    public function testFromInputReturnsArrayForMultipleFiles(): void
    {
        $_FILES['gallery'] = [
            'name'     => ['a.png', 'b.png'],
            'tmp_name' => [self::$pngFile, self::$pngFile],
            'type'     => ['image/png', 'image/png'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [100, 100],
        ];
        $result = Upload::fromInput('gallery');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Upload::class, $result);
        unset($_FILES['gallery']);
    }

    public function testFromInputSkipsEmptySlotsInMultipleUpload(): void
    {
        $_FILES['gallery'] = [
            'name'     => ['a.png', '', 'c.png'],
            'tmp_name' => [self::$pngFile, '', self::$pngFile],
            'type'     => ['image/png', '', 'image/png'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size'     => [100, 0, 100],
        ];
        $result = Upload::fromInput('gallery');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        unset($_FILES['gallery']);
    }

    // =========================================================================
    // 2. Metadata accessors
    // =========================================================================

    public function testGetOriginalNameReturnsSubmittedName(): void
    {
        $this->assertSame('photo.png', $this->upload()->getOriginalName());
    }

    public function testGetExtensionIsLowercased(): void
    {
        $this->assertSame('png', $this->upload(['name' => 'Photo.PNG'])->getExtension());
    }

    public function testGetSizeReturnsBytes(): void
    {
        $this->assertSame(12345, $this->upload(['size' => 12345])->getSize());
    }

    public function testGetMimeTypeReadsRealContent(): void
    {
        // tmp_name is a real PNG; finfo must report image/png.
        $this->assertSame('image/png', $this->upload()->getMimeType());
    }

    // =========================================================================
    // 3. Fluent configuration API
    // =========================================================================

    public function testFluentMethodsReturnSelf(): void
    {
        $u = $this->upload();
        $this->assertSame($u, $u->maxKB(100));
        $this->assertSame($u, $u->maxMB(1));
        $this->assertSame($u, $u->allowExtension(['png']));
        $this->assertSame($u, $u->allowMimeType(['image/png']));
        $this->assertSame($u, $u->useRandomName());
    }

    public function testAsNamePreservesRealExtension(): void
    {
        // Caller passes a .jpg target name, but the file is really a .png;
        // the real extension must be preserved to avoid a misleading name.
        $u = $this->upload(['name' => 'photo.png'])->asName('avatar.jpg');
        $name = $this->readProperty($u, 'targetFileName');
        $this->assertStringEndsWith('.png', $name);
        $this->assertStringNotContainsString('.jpg', $name);
    }

    public function testUseRandomNameProducesHexNameWithExtension(): void
    {
        $u = $this->upload()->useRandomName();
        $name = $this->readProperty($u, 'targetFileName');
        $this->assertMatchesRegularExpression('/^[0-9a-f]+\.png$/', $name);
    }

    public function testUseRandomNameIsUniquePerCall(): void
    {
        $a = $this->readProperty($this->upload()->useRandomName(), 'targetFileName');
        $b = $this->readProperty($this->upload()->useRandomName(), 'targetFileName');
        $this->assertNotSame($a, $b);
    }

    // =========================================================================
    // 4. Validation — upload error codes
    // =========================================================================

    /** @dataProvider uploadErrorProvider */
    public function testValidateFailsOnUploadError(int $code): void
    {
        $u = $this->upload(['error' => $code, 'size' => 0]);
        $this->assertFalse($u->validate());
        $this->assertNotEmpty($u->getErrors());
    }

    public static function uploadErrorProvider(): array
    {
        return [
            'ini size'   => [UPLOAD_ERR_INI_SIZE],
            'form size'  => [UPLOAD_ERR_FORM_SIZE],
            'partial'    => [UPLOAD_ERR_PARTIAL],
            'no file'    => [UPLOAD_ERR_NO_FILE],
            'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR],
            'cant write' => [UPLOAD_ERR_CANT_WRITE],
            'extension'  => [UPLOAD_ERR_EXTENSION],
        ];
    }

    public function testValidateRejectsNonUploadedFile(): void
    {
        // The real Upload (not the stub) must reject a path that did not arrive
        // via an HTTP upload — this guards the core is_uploaded_file() defence.
        $u = new RealUpload([
            'name' => 'photo.png', 'tmp_name' => self::$pngFile,
            'type' => 'image/png', 'error' => UPLOAD_ERR_OK,
            'size' => filesize(self::$pngFile),
        ]);
        $this->assertFalse($u->validate());
        $this->assertStringContainsStringIgnoringCase('uploaded', $u->getFirstError());
    }

    // =========================================================================
    // 5. Validation — empty-file guard
    // =========================================================================

    public function testEmptyFileIsRejectedByDefault(): void
    {
        $u = $this->upload([
            'name' => 'empty.png', 'tmp_name' => self::$emptyFile,
            'type' => 'image/png', 'size' => 0,
        ])->allowExtension(['png']);
        $this->assertFalse($u->validate());
        $this->assertStringContainsStringIgnoringCase('empty', $u->getFirstError());
    }

    public function testEmptyFileAllowedWhenGuardDisabled(): void
    {
        // No allow-list => no MIME consistency check; only the empty guard
        // could block this, and it is turned off.
        $u = $this->upload([
            'name' => 'empty.bin', 'tmp_name' => self::$emptyFile,
            'type' => 'application/octet-stream', 'size' => 0,
        ])->disallowEmpty(false);
        $this->assertTrue($u->validate());
    }

    // =========================================================================
    // 6. Validation — size limit
    // =========================================================================

    public function testSizeUnderLimitPasses(): void
    {
        $u = $this->upload()->allowExtension(['png'])->maxKB(100);
        $this->assertTrue($u->validate());
    }

    public function testSizeOverLimitFails(): void
    {
        $u = $this->upload(['size' => 99 * 1024 * 1024])
                  ->allowExtension(['png'])
                  ->maxMB(10);
        $this->assertFalse($u->validate());
        $this->assertStringContainsStringIgnoringCase('exceed', $u->getFirstError());
    }

    // =========================================================================
    // 7. Validation — extension allow-list
    // =========================================================================

    public function testAllowedExtensionPasses(): void
    {
        $this->assertTrue($this->upload()->allowExtension(['png', 'jpg'])->validate());
    }

    public function testDisallowedExtensionFails(): void
    {
        $this->assertFalse($this->upload()->allowExtension(['jpg', 'webp'])->validate());
    }

    public function testAllowExtensionAcceptsCommaSeparatedString(): void
    {
        $this->assertTrue($this->upload()->allowExtension('png, jpg, webp')->validate());
    }

    public function testAllowExtensionNormalisesLeadingDot(): void
    {
        $this->assertTrue($this->upload()->allowExtension(['.png'])->validate());
    }

    public function testAllowExtensionIsCaseInsensitive(): void
    {
        $u = $this->upload(['name' => 'photo.PNG'])->allowExtension(['png']);
        $this->assertTrue($u->validate());
    }

    // =========================================================================
    // 8. Validation — MIME type allow-list (real content, not browser type)
    // =========================================================================

    public function testAllowMimeTypePassesForCorrectContent(): void
    {
        $this->assertTrue($this->upload()->allowMimeType(['image/png'])->validate());
    }

    public function testAllowMimeTypeFailsForWrongContent(): void
    {
        $this->assertFalse($this->upload()->allowMimeType(['image/jpeg'])->validate());
    }

    public function testAllowMimeTypeIgnoresBrowserReportedType(): void
    {
        // Browser claims jpeg, but the bytes are a PNG; finfo must win.
        $u = $this->upload(['type' => 'image/jpeg'])->allowMimeType(['image/jpeg']);
        $this->assertFalse($u->validate());
    }

    // =========================================================================
    // 9. SECURITY — dangerous-extension blacklist
    // =========================================================================

    /** @dataProvider dangerousExtensionProvider */
    public function testDangerousExtensionIsBlockedWithoutAllowList(string $ext): void
    {
        $u = $this->upload([
            'name' => "file.$ext", 'tmp_name' => self::$phpFile,
            'type' => 'text/plain', 'size' => filesize(self::$phpFile),
        ]);
        $this->assertFalse($u->validate(), ".$ext must be blocked by the blacklist");
    }

    /** @dataProvider dangerousExtensionProvider */
    public function testBlacklistOverridesAllowList(string $ext): void
    {
        // Even if a developer mistakenly allows a dangerous extension, the
        // blacklist must still reject it.
        $u = $this->upload([
            'name' => "file.$ext", 'tmp_name' => self::$phpFile,
            'type' => 'text/plain', 'size' => filesize(self::$phpFile),
        ])->allowExtension([$ext]);
        $this->assertFalse($u->validate(), ".$ext must stay blocked despite the allow-list");
    }

    public static function dangerousExtensionProvider(): array
    {
        return [
            'php'      => ['php'],
            'phtml'    => ['phtml'],
            'phar'     => ['phar'],
            'php5'     => ['php5'],
            'asp'      => ['asp'],
            'aspx'     => ['aspx'],
            'jsp'      => ['jsp'],
            'exe'      => ['exe'],
            'sh'       => ['sh'],
            'bat'      => ['bat'],
            'svg'      => ['svg'],
            'html'     => ['html'],
            'js'       => ['js'],
            'htaccess' => ['htaccess'],
        ];
    }

    public function testDangerousExtensionAllowedAfterExplicitOptOut(): void
    {
        $u = $this->upload([
            'name' => 'script.php', 'tmp_name' => self::$phpFile,
            'type' => 'text/plain', 'size' => filesize(self::$phpFile),
        ])->allowExtension(['php'])
          ->allowDangerousExtensions()
          ->enforceMimeConsistency(false);
        $this->assertTrue($u->validate());
    }

    // =========================================================================
    // 10. SECURITY — extension / MIME consistency (anti-spoofing)
    // =========================================================================

    public function testPhpPayloadDisguisedAsJpgIsRejected(): void
    {
        // Classic attack: a PHP web-shell renamed to evil.jpg.
        $u = $this->upload([
            'name' => 'evil.jpg', 'tmp_name' => self::$spoofedJpgFile,
            'type' => 'image/jpeg', 'size' => filesize(self::$spoofedJpgFile),
        ])->allowExtension(['jpg', 'png']);
        $this->assertFalse($u->validate());
        $this->assertStringContainsStringIgnoringCase('match', $u->getFirstError());
    }

    public function testPngContentWithJpgExtensionIsRejected(): void
    {
        // Real PNG bytes but the name claims .jpg — mismatched pair.
        $u = $this->upload(['name' => 'photo.jpg', 'type' => 'image/jpeg'])
                  ->allowExtension(['jpg']);
        $this->assertFalse($u->validate());
    }

    public function testConsistentExtensionAndContentPasses(): void
    {
        $this->assertTrue($this->upload()->allowExtension(['png'])->validate());
    }

    public function testConsistencyNotEnforcedWithoutAllowList(): void
    {
        // Nothing to cross-check against, so the consistency gate is skipped.
        $this->assertTrue($this->upload()->validate());
    }

    public function testSpoofedFilePassesWhenConsistencyDisabled(): void
    {
        // Regression guard: disabling enforcement must reopen the path for
        // callers who knowingly accept the risk.
        $u = $this->upload([
            'name' => 'evil.jpg', 'tmp_name' => self::$spoofedJpgFile,
            'type' => 'image/jpeg', 'size' => filesize(self::$spoofedJpgFile),
        ])->allowExtension(['jpg'])->enforceMimeConsistency(false);
        $this->assertTrue($u->validate());
    }

    // =========================================================================
    // 11. SECURITY — filename sanitization
    // =========================================================================

    public function testSanitizeStripsPathTraversal(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', '../../etc/passwd');
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('..', $name);
    }

    public function testSanitizeStripsAbsolutePath(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', '/var/www/html/shell.php');
        $this->assertStringNotContainsString('/', $name);
    }

    public function testSanitizeRemovesNullByte(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', "shell.php\0.jpg");
        $this->assertStringNotContainsString("\0", $name);
    }

    public function testSanitizeRemovesControlCharacters(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', "evil\x01\x1f.png");
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F]/', $name);
    }

    public function testSanitizeRemovesLeadingDot(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', '.htaccess');
        $this->assertNotSame('.', $name[0] ?? '');
    }

    public function testSanitizeRemovesTrailingDot(): void
    {
        // Windows strips trailing dots, which could re-expose an inner extension.
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', 'evil.php.');
        $this->assertNotSame('.', substr($name, -1));
    }

    /** @dataProvider doubleExtensionProvider */
    public function testSanitizeCollapsesDoubleExtension(string $input, string $expected): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', $input);
        $this->assertSame($expected, $name);
    }

    public static function doubleExtensionProvider(): array
    {
        return [
            'php.jpg'   => ['file.php.jpg',   'file-php.jpg'],
            'php5.png'  => ['file.php5.png',  'file-php5.png'],
            'phtml.gif' => ['file.phtml.gif', 'file-phtml.gif'],
            'asp.jpeg'  => ['doc.asp.jpeg',   'doc-asp.jpeg'],
        ];
    }

    public function testSanitizeKeepsSafeCharacters(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', 'my-file_v2.png');
        $this->assertSame('my-file_v2.png', $name);
    }

    public function testSanitizeStripsUnsafeCharacters(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', 'héllo wörld!@#.png');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9.\-_]+$/', $name);
    }

    public function testSanitizeFallsBackToRandomWhenNothingRemains(): void
    {
        $name = $this->callProtected($this->upload(), 'sanitizeFileName', '!!!###');
        $this->assertNotEmpty($name);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $name);
    }

    // =========================================================================
    // 12. Helpers — ensureNameLength / generateUniqueName
    // =========================================================================

    public function testEnsureNameLengthLeavesShortNamesUntouched(): void
    {
        $this->assertSame(
            'photo.png',
            $this->callProtected($this->upload(), 'ensureNameLength', 'photo.png')
        );
    }

    public function testEnsureNameLengthTruncatesButKeepsExtension(): void
    {
        $long = str_repeat('a', 300) . '.png';
        $name = $this->callProtected($this->upload(), 'ensureNameLength', $long);
        $this->assertLessThanOrEqual(255, strlen($name));
        $this->assertStringEndsWith('.png', $name);
    }

    public function testGenerateUniqueNameAddsSuffixOnCollision(): void
    {
        $dir = sys_get_temp_dir() . '/webrium_uniq_' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/photo.png", '');

        $u = $this->upload();
        $this->writeProperty($u, 'destinationPath', $dir);
        $name = $this->callProtected($u, 'generateUniqueName', 'photo.png');

        $this->assertSame('photo-1.png', $name);

        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }

    public function testGenerateUniqueNameIncrementsUntilFree(): void
    {
        $dir = sys_get_temp_dir() . '/webrium_uniq_' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (['photo.png', 'photo-1.png', 'photo-2.png'] as $f) {
            file_put_contents("$dir/$f", '');
        }

        $u = $this->upload();
        $this->writeProperty($u, 'destinationPath', $dir);
        $name = $this->callProtected($u, 'generateUniqueName', 'photo.png');

        $this->assertSame('photo-3.png', $name);

        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }
}

/**
 * Test double overriding only the HTTP-upload check so validate() can run in
 * CLI. All other Upload behaviour is inherited unchanged.
 */
class UploadStub extends Upload
{
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    protected function isUploadedFile(string $path): bool
    {
        return is_file($path);
    }
}

/**
 * Exposes the protected constructor without altering any behaviour, so the
 * genuine is_uploaded_file() defence can be asserted.
 */
class RealUpload extends Upload
{
    public function __construct(array $data)
    {
        parent::__construct($data);
    }
}