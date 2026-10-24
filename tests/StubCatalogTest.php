<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalog\StubCatalog;
use PHPUnit\Framework\TestCase;

final class StubCatalogTest extends TestCase
{
    public function testSearchIsCaseInsensitive(): void
    {
        $page = StubCatalog::search(['q' => 'RTX']);

        self::assertSame(3, $page['meta']['total']);
        foreach ($page['items'] as $product) {
            self::assertStringContainsString('RTX', $product['name']);
        }
    }

    public function testPriceRangeIsApplied(): void
    {
        $page = StubCatalog::search(['priceMin' => '30000', 'priceMax' => '50000']);

        foreach ($page['items'] as $product) {
            self::assertGreaterThanOrEqual(30000, $product['price']['amount']);
            self::assertLessThanOrEqual(50000, $product['price']['amount']);
        }
    }

    public function testUnknownCategoryReturnsEmptyPage(): void
    {
        $page = StubCatalog::search(['category' => 'unknown']);

        self::assertSame([], $page['items']);
        self::assertSame(0, $page['meta']['total']);
        self::assertSame(0, $page['meta']['totalPages']);
    }

    public function testUnavailableProductIsExcludedFromAvailableFilter(): void
    {
        $page = StubCatalog::search(['available' => 'true', 'perPage' => '48']);

        foreach ($page['items'] as $product) {
            self::assertTrue($product['available']);
        }

        $ids = array_column($page['items'], 'id');
        self::assertNotContains('10', $ids);
    }

    public function testProductLookupReturnsFullProduct(): void
    {
        $product = StubCatalog::product('9');

        self::assertNotNull($product);
        self::assertSame('ASUS Prime B650M-A', $product['name']);
    }
}
