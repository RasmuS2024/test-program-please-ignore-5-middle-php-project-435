# Бекенд для интернет-магазина (PHP)

[![hexlet-check](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435/actions)

Реализуйте бекенд интернет-магазина комплектующих: регистрацию и авторизацию, каталог с
фильтрами и пагинацией, промо-блоки главной, оформление заказа и личный кабинет с историей.
Фреймворк выбираете сами, данные храните в PostgreSQL. Фронтенд предоставляет Хекслет —
готовое приложение, которое подключается к вашему API и работает только тогда, когда API
отвечает по описанному контракту.

Учебный проект Хекслета: https://ru.hexlet.io/programs/test-program-please-ignore-5-middle-php
Как это должно работать: https://files.hexlet.app/a/qf7bsq

## Стек

- PHP 8.3
- Slim 4
- PostgreSQL 16
- Docker

## Установка

```bash
git clone https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435.git
cd test-program-please-ignore-5-middle-php-project-435
```

### База данных

Проект использует PostgreSQL 16. Для локальной разработки база запускается в отдельном Docker-контейнере на порту `5433`:

```bash
[ -f .env ] || cp .env.example .env
# задайте DB_PASSWORD в .env
./scripts/db.sh start
./scripts/db.sh status
```

Подключение к базе:

```bash
./scripts/db.sh psql
```

Остановка:

```bash
./scripts/db.sh stop
```

Порт `5432` намеренно не используется: он может быть занят другим PostgreSQL.

<!-- Установка зависимостей и запуск приложения появятся после выбора фреймворка. -->

## Использование

<!-- Добавьте примеры запуска и запись asciinema — именно это смотрит работодатель -->

---

<details>
<summary>Автоматические тесты Хекслета</summary>

Тесты запускаются на каждый коммит. За запуск отвечает файл `.github/workflows/hexlet-check.yml` — не удаляйте и не переименовывайте ни его, ни репозиторий.

</details>

## О Хекслете

[Хекслет](https://ru.hexlet.io/) — школа программирования: авторские программы обучения с практикой, поддержкой наставников и реальными проектами, которые остаются в резюме. Этот репозиторий — один из таких проектов.
