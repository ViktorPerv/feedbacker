<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Загрузка файлов в настроенное файловое хранилище.
 *
 * Приложение не задаёт диск напрямую: его определяет `FILESYSTEM_DISK`, поэтому
 * один код работает с MinIO, S3 и локальной файловой системой. Файлы получают
 * предсказуемую основу имени (`logo`, `avatar`) в каталоге владельца, что
 * упрощает замену предыдущей загрузки.
 */
final class Uploads
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.default'));
    }

    /**
     * Сохраняет файл, заменяя предыдущую загрузку с той же основой имени.
     *
     * @return string Путь к файлу на диске.
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
     * Удаляет из каталога все файлы с указанной основой имени.
     *
     * Удаление по основе имени гарантирует, что logo.png будет удалён при его
     * замене файлом logo.webp.
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
     * Определяет расширение сохраняемого файла.
     *
     * Расширение берётся из клиентского имени, но MIME-тип уже ограничен
     * валидацией, поэтому неподдерживаемый тип пройти не может.
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
     * Возвращает каталог файлов пространства.
     */
    public static function spaceDirectory(string $spaceId): string
    {
        return "spaces/{$spaceId}";
    }

    /**
     * Возвращает каталог файлов отзыва.
     */
    public static function testimonialDirectory(string $testimonialId): string
    {
        return "testimonials/{$testimonialId}";
    }
}
