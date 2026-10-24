# Бекенд для интернет-магазина (PHP)

[![hexlet-check](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435/actions)

[![main](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435/actions/workflows/main.yml/badge.svg)](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435/actions/workflows/main.yml)

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
- PostgreSQL 18
- Docker
- Node.js / TypeSpec / OpenAPI 3.0 для описания контракта API

## Установка

```bash
git clone https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-435.git
cd test-program-please-ignore-5-middle-php-project-435
make install
```

`make install` ставит и PHP-, и npm-зависимости, поэтому для генерации контракта нужны Node.js и npm.

### Фронтенд

Фронтенд магазина приходит готовым npm-пакетом `@hexlet/php-online-store-frontend`. Собирать его не нужно: команда `make build` копирует готовый `dist` в `public/`:

```bash
make build
```

После обновления пакета фронтенда выполните `make build` повторно. Сгенерированные файлы в `public/assets`, `public/images` и `public/index.html` не коммитятся.

Скопируйте шаблон окружения и задайте пароль PostgreSQL:

```bash
[ -f .env ] || cp .env.example .env
# измените DB_PASSWORD и DATABASE_URL
```

### База данных

Проект использует PostgreSQL 18. Для локальной разработки можно запустить отдельный контейнер на порту `5433`:

```bash
docker run -d \
  --name hexlet-store-postgres \
  -e POSTGRES_DB=store \
  -e POSTGRES_USER=store \
  -e POSTGRES_PASSWORD=change-me \
  -p 127.0.0.1:5433:5432 \
  -v hexlet-store-pgdata:/var/lib/postgresql/data \
  postgres:18-alpine
```

Пароль должен совпадать с `DB_PASSWORD` и `DATABASE_URL` в `.env`. Порт `5432` не используется, чтобы не конфликтовать с другим PostgreSQL. Если контейнер уже создан на PostgreSQL 16, пересоздайте его и том `hexlet-store-pgdata`: данных на этом шаге ещё нет. Если контейнер уже создан на 18, запустите его командой `docker start hexlet-store-postgres`.

## Запуск

```bash
make build
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

Статика и API работают на одном порту: API — по путям `/api/...`, статика — из каталога `public/`. Любой не-API адрес без существующего файла отдаёт `public/index.html`, поэтому прямые ссылки вроде `/cart`, `/account` и `/products/1` открываются после перезагрузки. Неизвестный путь внутри `/api/` отвечает JSON-ошибкой в формате Problem Details, а не HTML.

На этом шаге каталог и промо-блоки отвечают временными заглушками. База данных, авторизация и заказы появятся на следующих шагах.

Проверка статики, SPA-fallback и JSON-ошибки:

```bash
curl -sI http://localhost:8080/assets/$(ls public/assets | head -1) | grep -i content-type
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/cart
# 200
curl -s http://localhost:8080/api/nothing-here
# {"type":"https://hexlet.io/problems/not-found","title":"Resource not found","status":404}
```

### Запуск в Docker

Образ собирается из корня репозитория:

```bash
docker build -t online-store .
docker run --rm \
  -e PORT=8080 \
  -e DATABASE_URL='postgres://postgres:password@localhost:5432/appdb' \
  -p 8080:8080 \
  online-store
```

Образ содержит `pdo_pgsql`, PHP-зависимости без dev-пакетов и собранный фронтенд. Порт берётся из `PORT`; `EXPOSE` не нужен. Точка входа `docker-entrypoint.sh` запускает сервер, а на шаге с базой данных сюда же будут добавлены миграции и заливка данных.

## Проверки

API-тесты не требуют браузера и запущенного сервера:

```bash
make test-api
```

Линтер и API-тесты:

```bash
make lint
make check
```

Перед API-тестами SPA-fallback выполните `make build`, иначе соответствующий тест будет пропущен.

### Браузерные тесты

Playwright ставится вместе с npm-зависимостями через `make install`, браузер Chromium устанавливается отдельно один раз:

```bash
npx playwright install --with-deps chromium
```

Тесты не поднимают приложение. Запустите сервер в одном терминале:

```bash
make start
```

и прогоните тесты в другом:

```bash
APP_URL=http://localhost:8080 make test
```

Адрес приложения Playwright берёт из `APP_URL`, по умолчанию используется `http://localhost:8080`. Тесты выполняются в один поток, потому что меняют состояние базы. Для регистрации используйте уникальный email на каждый прогон.

Скриншоты, видео, трейсы и HTML-отчёт попадают в `tmp/artifacts`. Каталог исключён из Git. В репозитории пока есть только временный smoke-тест; полный набор браузерных сценариев Хекслета подключается отдельно. Пока API авторизации и заказов не готов, соответствующие браузерные сценарии ожидаемо красные, поэтому в CI этот шаг временно не блокирует workflow.

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

Свой workflow `.github/workflows/main.yml` запускается на каждый push и pull request: он проверяет линтер, API-тесты и браузерные тесты. Браузерный шаг временно не блокирует сборку, пока API авторизации и заказов не готов.

</details>

## О Хекслете

[Хекслет](https://ru.hexlet.io/) — школа программирования: авторские программы обучения с практикой, поддержкой наставников и реальными проектами, которые остаются в резюме. Этот репозиторий — один из таких проектов.
