<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Все разрешения приложения.
 *
 * Формат имени: `<resource>.<action>[.scope]`. Действия с собственным ресурсом
 * проверяются политикой владения и не требуют разрешения; суффикс `.any`
 * обозначает доступ к чужим ресурсам для привилегированных ролей.
 */
enum PermissionEnum: string
{
    // ── Cross-account oversight over spaces ──
    case SpacesViewAny = 'spaces.view.any';
    case SpacesUpdateAny = 'spaces.update.any';
    case SpacesDeleteAny = 'spaces.delete.any';

    // ── Cross-account oversight over testimonials ──
    case TestimonialsModerateAny = 'testimonials.moderate.any';

    // ── Platform administration ──
    case UsersManage = 'users.manage';

    // ── Content ──
    case TranslationsManage = 'translations.manage';

    public function label(): string
    {
        return match ($this) {
            self::SpacesViewAny => 'View every space',
            self::SpacesUpdateAny => 'Edit any space',
            self::SpacesDeleteAny => 'Delete any space',
            self::TestimonialsModerateAny => 'Moderate testimonials anywhere',
            self::UsersManage => 'Manage user accounts',
            self::TranslationsManage => 'Manage translations',
        };
    }

    /**
     * Возвращает разрешения, не затрагивающие данные клиентов.
     *
     * Используется для узких ролей вроде `translator`, чтобы исключить их
     * доступ к администрированию учётных записей.
     *
     * @return array<int, self>
     */
    public static function contentOnly(): array
    {
        return [self::TranslationsManage];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Возвращает разрешения на ресурсы других учётных записей.
     *
     * @return array<int, string>
     */
    public static function oversight(): array
    {
        return [
            self::SpacesViewAny->value,
            self::SpacesUpdateAny->value,
            self::SpacesDeleteAny->value,
            self::TestimonialsModerateAny->value,
        ];
    }
}
