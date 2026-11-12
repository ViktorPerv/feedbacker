<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission in the application.
 *
 * The naming convention is `<resource>.<action>[.scope]`. Operations on a
 * resource the user *owns* are guarded by ownership in the policy and need no
 * permission; the `.any` suffix marks cross-account oversight powers that only
 * privileged roles hold.
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
     * Permissions that do not touch customer data.
     *
     * Used to decide what a narrowly-scoped role such as `translator` may
     * reach, and to keep such roles out of account administration.
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
     * Permissions that grant access to resources owned by other accounts.
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
