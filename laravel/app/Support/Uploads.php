<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads that work against whichever disk is configured.
 *
 * The application never names a disk: `FILESYSTEM_DISK` decides, so the same
 * code runs on MinIO, real S3 or the local filesystem. Uploaded files use a
 * deterministic stem (`logo`, `avatar`) inside a per-owner directory, which
 * makes "replace the previous one" a directory listing rather than URL
 * archaeology.
 */
final class Uploads
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.default'));
    }

    /**
     * Store a file, replacing any earlier upload with the same stem.
     *
     * @return string the path on the disk
     */
    public static function replace(UploadedFile $file, string $directory, string $stem): string
    {
        $directory = trim($directory, '/');

        self::forget($directory, $stem);

        $name = $stem.'.'.self::extension($file);

        self::disk()->putFileAs($directory, $file, $name, ['visibility' => 'public']);

        return $directory.'/'.$name;
    }

    /**
     * Remove every file in the directory whose stem matches.
     *
     * Deleting by stem rather than by stored URL means a logo uploaded as
     * .png is still cleaned up when it is replaced by a .webp.
     */
    public static function forget(string $directory, string $stem): void
    {
        $directory = trim($directory, '/');

        foreach (self::disk()->files($directory) as $path) {
            if (pathinfo($path, PATHINFO_FILENAME) === $stem) {
                self::disk()->delete($path);
            }
        }
    }

    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /**
     * The extension to store the file under.
     *
     * Taken from the client filename, but the request has already constrained
     * the MIME type, so this cannot smuggle an unexpected type past
     * validation.
     */
    public static function extension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === '') {
            $extension = strtolower($file->extension() ?: 'bin');
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * The directory a space's uploads live in.
     */
    public static function spaceDirectory(string $spaceId): string
    {
        return "spaces/{$spaceId}";
    }

    /**
     * The directory a testimonial's uploads live in.
     */
    public static function testimonialDirectory(string $testimonialId): string
    {
        return "testimonials/{$testimonialId}";
    }
}
