<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleEnum: string
{
    /** Администратор платформы с доступом к чужим учётным записям. */
    case Admin = 'admin';

    /** Редактор каталога переводов без доступа к данным клиентов. */
    case Translator = 'translator';

    /** Обычный пользователь, роль назначается автоматически при регистрации. */
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
     * Возвращает исходный набор разрешений каждой роли.
     *
     * Роль `admin` намеренно не указана: она получает все разрешения, поэтому
     * список пришлось бы менять при каждом добавлении разрешения.
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
