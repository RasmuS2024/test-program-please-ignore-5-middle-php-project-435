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
- Phinx для миграций и seed
- PostgreSQL 18
- Встроенные файловые сессии PHP
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

Пароль должен совпадать с `DB_PASSWORD` и `DATABASE_URL` в `.env`. Порт `5432` не используется, чтобы не конфликтовать с другим PostgreSQL. Если контейнер уже создан на PostgreSQL 16, пересоздайте его и том `hexlet-store-pgdata`; схема и каталог затем создаются командами ниже. Если контейнер уже создан на 18, запустите его командой `docker start hexlet-store-postgres`.

Схема создаётся миграциями Phinx, а каталог заполняется идемпотентным seed:

```bash
make db-setup
```

`make start` и `make test-api` вызывают `make db-setup` сами. Повторный запуск не ломает схему и не задваивает товары: миграции учитываются в `phinxlog`, а seed использует уникальные слаги и `ON CONFLICT`.

## Запуск

```bash
make build
make db-setup
make start
```

`make start` сам применяет миграции и seed, поэтому отдельный `make db-setup` нужен только если вы хотите подготовить базу заранее.

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

На этом шаге каталог, категории и промо-блоки читаются из PostgreSQL. В базе есть категории, товары, промо, пользователи и заказы, а захардкоженные списки каталога удалены. Регистрация, вход, выход, `GET /api/me`, оформление заказов и история покупок уже реализованы. Заказ создаётся из корзины фронтенда, но итоговую сумму и снимки цен считает сервер.

### Регистрация и авторизация

Пользовательские пароли хранятся только как хеши, созданные `password_hash()` и проверяемые через `password_verify()`. Email нормализуется: сервер обрезает внешние пробелы и приводит адрес к нижнему регистру.

Сессии — встроенные файловые сессии PHP. Cookie называется `PHPSESSID`, помечена `HttpOnly` и `SameSite=Lax`; флаг `Secure` добавляется при HTTPS. Выход удаляет сессию на сервере, поэтому старый cookie перестаёт работать.

```bash
curl -c cookies.txt -X POST http://localhost:8080/api/users \
  -H "Content-Type: application/json" \
  -d '{"email": "ivan@example.com", "password": "secret1"}'
# 201 {"id":"1","email":"ivan@example.com"}

curl -b cookies.txt http://localhost:8080/api/me
# 200 {"id":"1","email":"ivan@example.com"}

curl -b cookies.txt -X DELETE http://localhost:8080/api/session
# 204

curl -b cookies.txt http://localhost:8080/api/me
# 401 {"type":"https://hexlet.io/problems/unauthorized",…}
```

Ошибки различаются по смыслу: `400 validation-error` — неверная форма тела (например, короткий пароль), `409 email-taken` — email уже занят, `401 invalid-credentials` — неверный email или пароль при входе. Для несуществующего email и неверного пароля возвращается одинаковый ответ, чтобы нельзя было перебрать зарегистрированные адреса.

Файловые сессии подходят для учебного проекта. При перезапуске контейнера или на хостинге с эфемерной файловой системой пользователи разлогинятся; для рабочего окружения сессии стоит перенести в PostgreSQL или Redis.

### Каталог

`GET /api/products` читает каталог из PostgreSQL и выполняет фильтрацию, поиск, подсчёт `total` и пагинацию на сервере. Ответ содержит `items` и `meta`:

```json
{
  "items": [],
  "meta": {
    "page": 1,
    "perPage": 6,
    "total": 0,
    "totalPages": 0
  }
}
```

Поддерживаются необязательные фильтры, которые комбинируются между собой:

- `category` — слаг категории;
- `q` — подстрока названия без учёта регистра;
- `priceMin` и `priceMax` — включительные границы цены;
- `available=true` — только доступные товары.

Сервер принимает как `available=true`, так и `available=1`: фронтенд присылает числовой вариант. `page` по умолчанию равен `1`, `perPage` — `6`, максимум `48`. Фронтенд `perPage` не отправляет и полагается на значение по умолчанию. Пустое значение фильтра означает отсутствие фильтра. Товары с `available: false` видны в каталоге, если фильтр по наличию не запрошен.

```bash
curl -s "http://localhost:8080/api/products?category=videokarty&available=true"
curl -s "http://localhost:8080/api/products?q=RTX&priceMin=39990&priceMax=59990"
curl -s "http://localhost:8080/api/products?page=2"
curl -s "http://localhost:8080/api/products/1"
# {"id":"1","slug":"nvidia-geforce-rtx-4090",…}
```

Пустая выдача — это `200` с `items: []` и `total: 0`, а не ошибка. Неверный тип параметра возвращает `400 validation-error`, неизвестный идентификатор товара — `404 not-found`. Карточка товара (`GET /api/products/{id}`) отдаёт товар целиком, включая недоступные позиции; поле `imageUrl` опускается, если картинки нет.

Проверка статики, SPA-fallback и JSON-ошибки:

```bash
curl -sI http://localhost:8080/assets/$(ls public/assets | head -1) | grep -i content-type
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/cart
# 200
curl -s http://localhost:8080/api/categories
# [{"id":"1","slug":"videokarty","name":"Видеокарты"}, …]
curl -s http://localhost:8080/api/products
# {"items":[…],"meta":{"page":1,"perPage":6,"total":12,"totalPages":2}}
curl -s http://localhost:8080/api/promos
# [{"id":"1","title":"Флагман нового поколения",…}]
curl -s http://localhost:8080/api/nothing-here
# {"type":"https://hexlet.io/problems/not-found","title":"Resource not found","status":404}
```

### Главная и промо

На корневом адресе открывается главная страница с промо-блоками. Каждый блок содержит заголовок, текст и товар, на карточку которого он ведёт.

`GET /api/promos` читает блоки из PostgreSQL и отдаёт массив:

```json
[
  {
    "id": "1",
    "title": "Флагман нового поколения",
    "text": "RTX 4090 для 4K-гейминга и нейросетей.",
    "product": {
      "id": "1",
      "slug": "nvidia-geforce-rtx-4090",
      "name": "NVIDIA GeForce RTX 4090",
      "description": "Флагманская видеокарта для 4K-гейминга и работы с нейросетями.",
      "price": {
        "amount": 199990,
        "currency": "RUB"
      },
      "categoryId": "1",
      "available": true,
      "imageUrl": "/images/nvidia-geforce-rtx-4090.svg"
    }
  }
]
```

Товар приходит внутри блока целиком и в том же виде, что в каталоге. Сервер собирает ответ одним запросом с `JOIN`, поэтому главная не делает отдельный запрос на каждый блок. В seed заливаются три блока на доступные товары; уникальный индекс по `product_id` не даёт одному товару попасть в два блока. Недоступные товары в промо-выдачу не попадают. Если блоков нет, ответ — `200` и пустой массив `[]`.

```bash
curl -s http://localhost:8080/api/promos
# [{"id":"1","title":"Флагман нового поколения",…}]
```

Клик по блоку на главной открывает страницу его товара. Это проверяется браузерным тестом `__tests__/promos.spec.ts`.

### Заказы и личный кабинет

Все операции с заказами требуют активной сессии. Без cookie `PHPSESSID` сервер отвечает `401 unauthorized`.

`POST /api/orders` принимает состав корзины и данные получателя:

```json
{
  "items": [
    {"productId": "1", "quantity": 2},
    {"productId": "2", "quantity": 1}
  ],
  "shipping": {
    "method": "delivery",
    "recipientName": "Иван Петров",
    "phone": "+7 999 000-11-22",
    "address": "ул. Ленина, 1"
  }
}
```

`items` — непустой массив объектов с `productId` и положительным целым `quantity`. Если один товар встречается несколько раз, сервер объединяет строки и суммирует количество. `method` равен `delivery` или `pickup`. Для доставки `address` обязателен: после обрезки пробелов пустая строка возвращает `400 address-required`. Для самовывоза адрес не нужен; если он передан, сервер его игнорирует и в ответе не возвращает.

Цены и итог клиент не присылает: сервер берёт актуальные цены из `products`, сам считает `total` и сохраняет в заказе снимки `productName` и `priceAtPurchase`. Поэтому последующее изменение цены или названия товара не меняет уже оформленный заказ, а `productId` сохраняется даже после удаления товара из каталога. Заказ и его строки записываются в одной транзакции со статусом `paid`.

Если хотя бы один товар отсутствует в каталоге или недоступен, заказ не создаётся, а ответ содержит все проблемные идентификаторы:

```json
{
  "type": "https://hexlet.io/problems/unavailable-items",
  "title": "Some products are unavailable",
  "status": 400,
  "unavailableProductIds": ["2", "999999"]
}
```

Успешный ответ — `201`:

```json
{
  "id": "1",
  "status": "paid",
  "items": [
    {
      "productId": "1",
      "productName": "NVIDIA GeForce RTX 4090",
      "priceAtPurchase": {"amount": 199990, "currency": "RUB"},
      "quantity": 2
    }
  ],
  "shipping": {
    "method": "delivery",
    "recipientName": "Иван Петров",
    "phone": "+7 999 000-11-22",
    "address": "ул. Ленина, 1"
  },
  "total": {"amount": 399980, "currency": "RUB"},
  "createdAt": "2026-10-26T12:00:00Z"
}
```

`GET /api/orders` возвращает заказы только текущего пользователя, сначала новые. `GET /api/orders/{id}` отдаёт заказ только его владельцу; чужой или несуществующий идентификатор даёт `404 not-found`. Пустая история — это `200` и `[]`.

Полный сценарий через API:

```bash
curl -c cookies.txt -X POST http://localhost:8080/api/users \
  -H "Content-Type: application/json" \
  -d '{"email": "orders@example.com", "password": "secret1"}'

curl -b cookies.txt -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{"productId": "1", "quantity": 2}],
    "shipping": {
      "method": "delivery",
      "recipientName": "Иван Петров",
      "phone": "+7 999 000-11-22",
      "address": "ул. Ленина, 1"
    }
  }'
# 201 {"id":"1","status":"paid",…}

curl -b cookies.txt http://localhost:8080/api/orders
# 200 [{…}]

curl -b cookies.txt http://localhost:8080/api/orders/1
# 200 {…}
```

После оформления фронтенд очищает корзину и показывает заказ на странице `/checkout/success`, а история доступна в `/account`. Этот путь проверяется браузерным тестом `__tests__/orders.spec.ts`.

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

Образ содержит `pdo_pgsql`, PHP-зависимости без dev-пакетов и собранный фронтенд. Порт берётся из `PORT`; `EXPOSE` не нужен. Точка входа `docker-entrypoint.sh` применяет миграции, выполняет идемпотентный seed и запускает сервер. Поэтому при каждом перезапуске контейнера схема и каталог остаются в согласованном состоянии.

## Проверки

API-тесты не требуют браузера и запущенного HTTP-сервера, но используют PostgreSQL. Перед тестами применяются миграции и seed:

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

Скриншоты, видео, трейсы и HTML-отчёт попадают в `tmp/artifacts`. Каталог исключён из Git. В репозитории есть smoke-тест, проверки регистрации, сохранения сессии, выхода и повторного входа, редиректа анонима с `/account` на `/signin`, фильтров и пагинации каталога, промо-блоков главной с переходом на карточку своего товара, а также сценарий оформления доставки: регистрация, добавление товаров в корзину, оформление заказа, очистка корзины и проверка заказа в личном кабинете. Полный набор браузерных сценариев Хекслета подключается отдельно. В CI шаг браузерных тестов блокирующий.

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

Свой workflow `.github/workflows/main.yml` запускается на каждый push и pull request: он проверяет линтер, API-тесты и браузерные тесты, а также повторно запускает seed, чтобы поймать дубли. Браузерные тесты блокируют сборку.

</details>

## О Хекслете

[Хекслет](https://ru.hexlet.io/) — школа программирования: авторские программы обучения с практикой, поддержкой наставников и реальными проектами, которые остаются в резюме. Этот репозиторий — один из таких проектов.
