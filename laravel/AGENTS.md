## Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Соглашения проекта

### Язык
- Все описания классов и методов в PHPDoc должны быть написаны на русском языке.
- Коммиты и имена веток — на английском (Conventional Commits)
- Служебные PHPDoc-теги (`@param`, `@return`, `@var`, `@property`, `@throws`, `@template`, `@extends`, `@use`) сохраняются в стандартном формате; поясняющий текст к ним пишется на русском языке.
- Каждая Eloquent-модель должна содержать PHPDoc-аннотации `@property` для всех полей таблицы с типами, соответствующими миграциям и кастам модели.

### Безопасность

- Никогда не коммитить секреты и ключи доступа
- Никогда не доверять клиентским данным без серверной валидации
- Никогда не вставлять значения напрямую в SQL (использовать параметризованные запросы)
- Всегда валидировать и санировать пользовательский ввод
- Всегда экранировать вывод для предотвращения XSS
- Регулярно обновлять зависимости для устранения уязвимостей
