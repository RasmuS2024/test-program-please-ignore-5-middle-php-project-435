<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Phinx\Migration\AbstractMigration;

final class CreateStoreSchema extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            <<<'SQL'
CREATE TABLE categories (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
)
SQL
        );

        $this->execute(
            <<<'SQL'
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    price_rub INTEGER NOT NULL,
    category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    image_url TEXT,
    available BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT products_price_nonnegative CHECK (price_rub >= 0),
    CONSTRAINT products_image_url_not_empty CHECK (image_url IS NULL OR btrim(image_url) <> '')
)
SQL
        );

        $this->execute(
            <<<'SQL'
CREATE TABLE promos (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    product_id BIGINT NOT NULL UNIQUE REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT promos_body_not_empty CHECK (btrim(body) <> '')
)
SQL
        );

        $this->execute(
            <<<'SQL'
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_email_normalized CHECK (email = lower(btrim(email)) AND email <> '')
)
SQL
        );

        $this->execute(
            <<<'SQL'
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status TEXT NOT NULL DEFAULT 'paid',
    shipping_method TEXT NOT NULL,
    recipient_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    address TEXT,
    total_rub INTEGER NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT orders_status_paid CHECK (status = 'paid'),
    CONSTRAINT orders_shipping_method_valid CHECK (shipping_method IN ('delivery', 'pickup')),
    CONSTRAINT orders_recipient_name_not_empty CHECK (btrim(recipient_name) <> ''),
    CONSTRAINT orders_phone_not_empty CHECK (btrim(phone) <> ''),
    CONSTRAINT orders_total_nonnegative CHECK (total_rub >= 0),
    CONSTRAINT orders_delivery_address_required CHECK (
        shipping_method <> 'delivery' OR (address IS NOT NULL AND btrim(address) <> '')
    )
)
SQL
        );

        $this->execute(
            <<<'SQL'
CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE SET NULL,
    product_name TEXT NOT NULL,
    price_rub INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    CONSTRAINT order_items_price_nonnegative CHECK (price_rub >= 0),
    CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)
)
SQL
        );

        $this->execute(
            'CREATE INDEX products_category_id_idx ON products (category_id)'
        );
        $this->execute(
            'CREATE INDEX products_price_rub_idx ON products (price_rub)'
        );
        $this->execute(
            'CREATE INDEX products_available_idx ON products (available)'
        );
        $this->execute(
            'CREATE INDEX orders_user_id_created_at_idx ON orders (user_id, created_at DESC)'
        );
        $this->execute(
            'CREATE INDEX order_items_order_id_idx ON order_items (order_id)'
        );
        $this->execute(
            'CREATE INDEX order_items_product_id_idx ON order_items (product_id)'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS order_items');
        $this->execute('DROP TABLE IF EXISTS orders');
        $this->execute('DROP TABLE IF EXISTS users');
        $this->execute('DROP TABLE IF EXISTS promos');
        $this->execute('DROP TABLE IF EXISTS products');
        $this->execute('DROP TABLE IF EXISTS categories');
    }
}
