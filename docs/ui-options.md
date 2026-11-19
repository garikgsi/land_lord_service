# Согласованная UI-основа

Решение пользователя от 19 ноября 2026 года: Tabler + Vue + Symfony API для
основного приложения и Tabler + Twig для части серверной функциональности.
Один и тот же Vue-клиент будет упаковываться в Android через Capacitor.
Конкретные бизнес-разделы на Twig ещё не определены.

## Компоненты

| Потребность | Решение |
| --- | --- |
| Общий внешний вид | Tabler, единые CSS для Vue и Twig |
| Основные рабочие экраны | Vue + TypeScript, Symfony API |
| Отдельные серверные страницы | Symfony + Twig |
| Select и автокомплит | Tom Select; при использовании Symfony Forms возможен Symfony UX Autocomplete |
| Календарь сроков и событий | FullCalendar Standard, официальный Vue 3 коннектор |
| Иконки | Tabler Icons |
| Android | Тот же Vue build в Capacitor, webDir: dist |

Tabler и коннектор FullCalendar имеют бесплатные MIT-лицензии, Tom Select
использует Apache-2.0. Дорожки ресурсов и resource timeline относятся к
FullCalendar Premium и не включены в выбранный бесплатный набор.

## Реализованный начальный каркас

- Светлая тема по умолчанию, переключение на тёмную и сохранение выбора.
- Адаптивная навигация; на телефоне меню открывается отдельно.
- Обзор и каркас разделов из описания пользователя, без тестовых записей и сумм.
- Tom Select используется для поиска и открытия разделов приложения.
- Календарь с переключением месяца и списком, пока без событий.
- Явная пометка о предварительном интерфейсе и неподключённых рабочих данных.
- Техническая Twig-страница состояния показывает работу гибридного стека.

JS и CSS собранного клиента отдаёт Nginx. HTML-оболочка `/` и `/index.html`
проходит через Symfony, чтобы в dev появилась штатная нижняя панель отладки.
Запросы `/api/*` и `/server/*` также передаются Symfony. Vue использует
hash-маршрутизацию, общую для web и мобильной упаковки.
Twig загружает тот же CSS из Vite manifest, отдельной копии темы нет.

## Границы мобильного клиента

Twig выполняется на сервере и не включается в Android-сборку как исполняемый
PHP. Бизнес-возможности, необходимые в мобильном клиенте, должны иметь API
и представление на Vue. Расчёты и проверки прав остаются в сервисах Symfony
и используются обоими интерфейсами.

Перед созданием native Android-проекта нужно определить appId, HTTPS-адрес API,
авторизацию и CORS. Offline-режим не определён. Подмена локальной сборки
на открытие сайта через Capacitor server.url не используется.

## Официальные источники

- [Tabler: устройство UI-kit](https://docs.tabler.io/ui/getting-started)
- [Tabler: светлая и тёмная темы](https://tabler.io/guides/bootstrap-5-dark-mode-dashboard)
- [Tabler: формы в светлой теме](https://preview.tabler.io/form-elements.html?theme=light)
- [Tabler: лицензии](https://tabler.io/license)
- [Tom Select: примеры](https://tom-select.js.org/examples/)
- [Tom Select: загрузка с сервера](https://tom-select.js.org/examples/remote/)
- [Tom Select: лицензия](https://github.com/orchidjs/tom-select/blob/master/LICENSE)
- [Symfony UX Autocomplete](https://symfony.com/bundles/ux-autocomplete)
- [FullCalendar: Vue-компонент](https://fullcalendar.io/docs/vue)
- [FullCalendar: Standard и Premium](https://fullcalendar.io/license)
- [FullCalendar: дорожки ресурсов](https://fullcalendar.io/docs/timeline-view)
- [Capacitor: webDir и server.url](https://capacitorjs.com/docs/config)
