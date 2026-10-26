<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Phinx\Migration\AbstractMigration;

final class DecoupleOrderItemsFromProducts extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_product_id_fkey'
        );
        $this->execute(
            'ALTER TABLE order_items ALTER COLUMN product_id SET NOT NULL'
        );
    }

    public function down(): void
    {
        $this->execute(
            'ALTER TABLE order_items ALTER COLUMN product_id DROP NOT NULL'
        );
        $this->execute(
            'UPDATE order_items oi SET product_id = NULL'
            . ' WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.id = oi.product_id)'
        );
        $this->execute(
            'ALTER TABLE order_items'
            . ' ADD CONSTRAINT order_items_product_id_fkey'
            . ' FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL'
        );
    }
}
