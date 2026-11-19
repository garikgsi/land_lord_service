# Техническая основа

Проверено по официальным источникам 19 ноября 2026 года.

| Компонент | Выбранная версия | Источник |
| --- | --- | --- |
| PHP-FPM | 8.5.12, Debian Bookworm | [PHP Downloads](https://www.php.net/downloads.php) |
| Symfony | 8.1.11, стабильная ветка 8.1 | [Symfony Releases](https://symfony.com/releases) |
| Nginx | 1.30.7, stable | [Nginx Downloads](https://nginx.org/en/download.html) |
| PostgreSQL | 18.7 | [PostgreSQL](https://www.postgresql.org/) |
| Composer | Стабильная ветка 2 | [Официальный Docker-образ](https://hub.docker.com/_/composer) |
| Node.js | 26.13.1 LTS | [Node.js Downloads](https://nodejs.org/en/download/) |
| Vue | 3.5.45 | [Vue Releases](https://github.com/vuejs/core/releases) |
| Vue Router | 5.5.1 | [Vue Router](https://router.vuejs.org/) |
| Tabler | 1.7.5 | [Tabler](https://github.com/tabler/tabler) |
| Tabler Icons | 3.52.0 | [Changelog](https://tabler.io/changelog) |
| Vite | 8.3.8 | [Vite Releases](https://vite.dev/releases) |
| TypeScript | 6.0.3, совместимая стабильная ветка | [TypeScript](https://github.com/microsoft/TypeScript/releases) |
| Tom Select | 2.7.0 | [Tom Select](https://tom-select.js.org/) |
| FullCalendar Vue 3 / Standard | 7.1.5 | [Vue connector](https://fullcalendar.io/docs/vue) |
| Capacitor | 8.6.2 | [Capacitor](https://capacitorjs.com/docs) |

Symfony 8.2 на момент проверки представлен предварительным релизом;
PostgreSQL 19 тоже предварительный. Их не используем.
Symfony 8.1 требует PHP ≥ 8.4, поэтому PHP 8.5 подходит. Patch-версии PHP-пакетов
фиксируются в `backend/composer.lock`, рецепты в `backend/symfony.lock`.

Symfony 8.1 имеет короткий срок поддержки. Для соответствия требованию
актуальных стабильных версий нужно обновиться на следующую стабильную ветку
после её выхода и проверки зависимостей. Альтернатива с длинной поддержкой:
Symfony 7.4 LTS; переход на неё отдельно согласовывается.

UI выбран: Tabler + Vue + Symfony API для основного приложения и Tabler + Twig
для части серверных экранов. Клиент собирается Vite: JS и CSS отдаёт Nginx,
HTML-оболочку web отдаёт Symfony с панелью отладки только в dev. Twig
использует тот же CSS через manifest. Npm-зависимости зафиксированы в
`frontend/package-lock.json`. Версии проверены также по npm registry.

TypeScript 7.0.2 является стабильным, но текущий vue-tsc 3.3.14 не поддерживает
его exports и падает при запуске. Поэтому выбран TypeScript 6.0.3, последняя
стабильная версия совместимой ветки. Не повышать её до поддержки vue-tsc.

Capacitor настроен на webDir: dist. Native Android-проект, авторизация,
доменная модель и расчёты пока не создаются.

Для соединения с БД установлен Doctrine DBAL без ORM. Для служебного API
`GET /api/healthz` выполняется `SELECT 1`; при ошибке подключения возвращается
HTTP 503 без реквизитов подключения. Проверка штатного ответа и сбоя
выполняется функциональными тестами Symfony/PHPUnit.

Окружение предназначено для локальной разработки. HTTP слушает только loopback,
БД не публикует порт на хост. Секреты находятся в игнорируемом `.env`.
Настройки production и развёртывание будут отдельной задачей.

Основной каталог: `/opt/homebrew/var/www/LandLordService`. Dockerfile и Compose
расположены в корне. PostgreSQL использует bind mount `./var/postgresql`,
резервные копии хранятся в `./var/backups`. Эти каталоги исключены из Git
и Docker build context.
