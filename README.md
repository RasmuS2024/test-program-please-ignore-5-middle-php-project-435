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
- Node.js / TypeSpec / OpenAPI 3.0 для описания контракта API

## Установка

```bash
git clone https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435.git
cd test-program-please-ignore-5-middle-php-project-435
make install
```

`make install` ставит и PHP-, и npm-зависимости, поэтому для генерации контракта нужны Node.js и npm.

Скопируйте шаблон окружения и задайте пароль PostgreSQL:

```bash
[ -f .env ] || cp .env.example .env
# измените DB_PASSWORD и DATABASE_URL
```

### База данных

Проект использует PostgreSQL 16. Для локальной разработки можно запустить отдельный контейнер на порту `5433`:

```bash
docker run -d \
  --name hexlet-store-postgres \
  -e POSTGRES_DB=store \
  -e POSTGRES_USER=store \
  -e POSTGRES_PASSWORD=change-me \
  -p 127.0.0.1:5433:5432 \
  -v hexlet-store-pgdata:/var/lib/postgresql/data \
  postgres:16-alpine
```

Пароль должен совпадать с `DB_PASSWORD` и `DATABASE_URL` в `.env`. Порт `5432` не используется, чтобы не конфликтовать с другим PostgreSQL. Если контейнер уже создан, запустите его командой `docker start hexlet-store-postgres`.

## Запуск

```bash
make start
```

Приложение слушает `0.0.0.0:8080` или порт из переменной `PORT`:

```bash
PORT=9000 make start
```

Проверка API:

```bash
curl http://localhost:8080/api/health
# {"status":"ok"}
```

Статика и API работают на одном порту: API — по путям `/api/...`, статика — из каталога `public/`.

## Проверки

```bash
make test
make lint
make check
```

## Контракт API

Источник истины — `contract/main.tsp`. Спецификация OpenAPI генерируется из него:

```bash
make contract
```

Результат — `contract/openapi.yaml`. Этот файл коммитится вместе с описанием контракта и не редактируется вручную.

Проверить, что сгенерированная спецификация соответствует исходнику:

```bash
make contract-check
```

---

<details>
<summary>Автоматические тесты Хекслета</summary>

Тесты запускаются на каждый коммит. За запуск отвечает файл `.github/workflows/hexlet-check.yml` — не удаляйте и не переименовывайте ни его, ни репозиторий.

</details>

## О Хекслете

[Хекслет](https://ru.hexlet.io/) — школа программирования: авторские программы обучения с практикой, поддержкой наставников и реальными проектами, которые остаются в резюме. Этот репозиторий — один из таких проектов.
