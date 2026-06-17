<?php

declare(strict_types=1);

namespace Webrium\Helpers;

/**
 * Central, hand-curated mapping of file extensions to their allowed MIME
 * (media) types, plus a blacklist of extensions that are dangerous to serve
 * from a web-accessible directory.
 *
 * This is the single source of truth used by both {@see \Webrium\Upload} and
 * {@see \Webrium\UploadHelper}. Keeping the data here prevents the two classes
 * from drifting apart and avoids pulling in an external dependency such as
 * symfony/mime.
 *
 * The lists are intentionally conservative: only well-understood, commonly
 * uploaded types are included. Callers who need something more exotic can add
 * it explicitly through the Upload API.
 *
 * @package Webrium\Helpers
 */
final class MimeMap
{
    /**
     * Map of lower-cased, dot-less extension => list of acceptable MIME types.
     *
     * Multiple MIME types per extension are listed because the value reported
     * by finfo can legitimately vary between systems and libmagic versions
     * (e.g. some report "audio/x-wav" while others report "audio/wav").
     *
     * @var array<string, list<string>>
     */
    private const MAP = [
        // --- Images ---
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        'tiff' => ['image/tiff'],
        'tif'  => ['image/tiff'],
        'ico'  => ['image/vnd.microsoft.icon', 'image/x-icon'],
        'avif' => ['image/avif'],
        'heic' => ['image/heic'],
        'heif' => ['image/heif'],

        // --- Video ---
        'mp4'  => ['video/mp4'],
        'm4v'  => ['video/mp4', 'video/x-m4v'],
        'mov'  => ['video/quicktime'],
        'webm' => ['video/webm'],
        'avi'  => ['video/x-msvideo', 'video/avi'],
        'mkv'  => ['video/x-matroska'],
        'mpeg' => ['video/mpeg'],
        'mpg'  => ['video/mpeg'],
        '3gp'  => ['video/3gpp'],
        'flv'  => ['video/x-flv'],

        // --- Audio ---
        'mp3'  => ['audio/mpeg'],
        'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'ogg'  => ['audio/ogg', 'application/ogg'],
        'oga'  => ['audio/ogg'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a'],
        'aac'  => ['audio/aac', 'audio/x-aac'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'weba' => ['audio/webm'],

        // --- Documents ---
        'pdf'  => ['application/pdf', 'application/x-pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp'  => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'rtf'  => ['application/rtf', 'text/rtf'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],

        // --- Archives ---
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'rar'  => ['application/vnd.rar', 'application/x-rar-compressed', 'application/x-rar'],
        '7z'   => ['application/x-7z-compressed'],
        'tar'  => ['application/x-tar'],
        'gz'   => ['application/gzip', 'application/x-gzip'],
    ];

    /**
     * Extensions that must never be accepted by default because they can be
     * executed or interpreted by a web server (leading to RCE / stored XSS).
     *
     * SVG is included deliberately: it can carry inline JavaScript and lead to
     * stored XSS when served inline. Callers who genuinely need these must opt
     * in explicitly via Upload::allowDangerousExtensions().
     *
     * @var list<string>
     */
    private const DANGEROUS = [
        // PHP family
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
        // Other server-side / scriptable
        'asp', 'aspx', 'jsp', 'jspx', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
        // Windows executables / scripts
        'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'scr', 'vbs', 'vbe', 'ws', 'wsf', 'ps1',
        // Web / markup that can execute in a browser context
        'svg', 'svgz', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'js', 'mjs', 'htaccess',
        // Misc
        'jar', 'class',
    ];

    /**
     * Return the list of acceptable MIME types for a given extension.
     *
     * @param string $extension Lower-cased, dot-less extension.
     * @return list<string>     Empty array if the extension is unknown.
     */
    public static function mimeTypesFor(string $extension): array
    {
        $extension = ltrim(strtolower(trim($extension)), '.');
        return self::MAP[$extension] ?? [];
    }

    /**
     * Whether an extension has a known MIME mapping.
     */
    public static function isKnown(string $extension): bool
    {
        $extension = ltrim(strtolower(trim($extension)), '.');
        return isset(self::MAP[$extension]);
    }

    /**
     * Whether the real MIME type is consistent with the claimed extension.
     *
     * If the extension is unknown to the map, this returns null to signal
     * "cannot decide" so the caller can choose its own policy.
     *
     * @return bool|null true = match, false = mismatch, null = unknown extension
     */
    public static function matches(string $extension, string $mime): ?bool
    {
        $allowed = self::mimeTypesFor($extension);
        if ($allowed === []) {
            return null;
        }
        return in_array(strtolower(trim($mime)), $allowed, true);
    }

    /**
     * Whether an extension is on the dangerous blacklist.
     */
    public static function isDangerous(string $extension): bool
    {
        $extension = ltrim(strtolower(trim($extension)), '.');
        return in_array($extension, self::DANGEROUS, true);
    }

    /**
     * All extensions belonging to a logical category. Used by UploadHelper.
     *
     * @return list<string>
     */
    public static function extensionsForCategory(string $category): array
    {
        return match ($category) {
            'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'avif', 'heic', 'heif'],
            'video'    => ['mp4', 'm4v', 'mov', 'webm', 'avi', 'mkv', 'mpeg', 'mpg', '3gp'],
            'audio'    => ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'weba'],
            'pdf'      => ['pdf'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv'],
            'archive'  => ['zip', 'rar', '7z', 'tar', 'gz'],
            default    => [],
        };
    }
}