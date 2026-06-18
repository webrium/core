<?php

declare(strict_types=1);

namespace Webrium\Helpers;

use Webrium\Upload;

/**
 * Convenience layer over {@see Upload} for the most common upload scenarios.
 *
 * Instead of manually listing the right extensions, MIME types and a sensible
 * size cap for, say, an image upload, callers can write:
 *
 *     $name = UploadHelper::image('avatar')
 *         ->to('/var/www/uploads/avatars')
 *         ->useRandomName()
 *         ->save();
 *
 * Every factory returns a fully configured {@see Upload} instance, so the
 * fluent API is still available and any default (including the size cap) can
 * be overridden afterwards:
 *
 *     UploadHelper::video('clip')->maxMB(500)->to('/clips')->save();
 *
 * Design notes:
 *  - This class contains NO security logic of its own. It only wires up the
 *    same Upload validation that any manual caller would use, so there is one
 *    place where the rules live (Upload + MimeMap).
 *  - Extension/MIME consistency enforcement and the dangerous-extension
 *    blacklist remain ON for everything produced here.
 *  - SVG is deliberately excluded from image(): it can execute JavaScript.
 *
 * @package Webrium
 */
final class UploadHelper
{
    /**
     * Sensible default size caps per category, in megabytes.
     */
    private const DEFAULT_MAX_MB = [
        'image'    => 5,
        'video'    => 200,
        'audio'    => 50,
        'pdf'      => 20,
        'document' => 25,
        'archive'  => 100,
    ];

    /**
     * Build a configured Upload (or array of Uploads) for a logical category.
     *
     * @param string $category One of: image, video, audio, pdf, document, archive.
     * @param string $inputName The file input field name.
     * @return Upload|array<int,Upload>|null
     */
    private static function make(string $category, string $inputName): Upload|array|null
    {
        $upload = Upload::fromInput($inputName);
        if ($upload === null) {
            return null;
        }

        $extensions = MimeMap::extensionsForCategory($category);
        $maxMb      = self::DEFAULT_MAX_MB[$category] ?? 10;

        $configure = static function (Upload $u) use ($extensions, $maxMb): Upload {
            return $u
                ->allowExtension($extensions)
                ->maxMB($maxMb)
                ->enforceMimeConsistency(true);
        };

        if (is_array($upload)) {
            return array_map($configure, $upload);
        }

        return $configure($upload);
    }

    public static function image(string $inputName): Upload|array|null
    {
        return self::make('image', $inputName);
    }

    public static function video(string $inputName): Upload|array|null
    {
        return self::make('video', $inputName);
    }

    public static function audio(string $inputName): Upload|array|null
    {
        return self::make('audio', $inputName);
    }

    public static function pdf(string $inputName): Upload|array|null
    {
        return self::make('pdf', $inputName);
    }

    public static function document(string $inputName): Upload|array|null
    {
        return self::make('document', $inputName);
    }

    public static function archive(string $inputName): Upload|array|null
    {
        return self::make('archive', $inputName);
    }
}