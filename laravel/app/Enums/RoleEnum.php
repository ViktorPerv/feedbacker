<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleEnum: string
{
    /** Platform administrator — cross-account oversight. */
    case Admin = 'admin';

    /** Maintains the translation catalogue. No access to customer data. */
    case Translator = 'translator';

    /** Standard account holder. Assigned automatically on registration. */
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Translator => 'Translator',
            self::User => 'Member',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access, including every account’s data.',
            self::Translator => 'Can manage translations. No access to customer data.',
            self::User => 'Owns their own spaces and testimonials.',
        };
    }

    /**
     * The permissions each role starts with.
     *
     * `admin` is deliberately not listed: it holds every permission, so
     * listing them would mean editing this file every time one is added.
     *
     * @return array<int, PermissionEnum>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => PermissionEnum::cases(),
            self::Translator => [PermissionEnum::TranslationsManage],
            self::User => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
