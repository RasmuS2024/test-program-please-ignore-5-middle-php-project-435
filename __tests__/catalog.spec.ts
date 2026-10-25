import { test, expect, type Page } from '@playwright/test';

const CATALOG_PATH = '/catalog';

async function catalogNames(page: Page): Promise<string[]> {
  return page.getByTestId('catalog-item-name').allTextContents();
}

test('catalog uses the default page size and paginates without overlap', async ({ page }) => {
  const productsRequest = page.waitForRequest((request) => {
    return new URL(request.url()).pathname === '/api/products';
  });

  await page.goto(CATALOG_PATH);

  const requestUrl = new URL((await productsRequest).url());
  expect(requestUrl.searchParams.get('page')).toBe('1');
  expect(requestUrl.searchParams.has('perPage')).toBe(false);

  await expect(page.getByTestId('catalog-list')).toBeVisible();
  await expect(page.getByTestId('catalog-item')).toHaveCount(6);
  await expect(page.getByTestId('catalog-pagination')).toBeVisible();

  const firstPage = await catalogNames(page);
  expect(firstPage).toHaveLength(6);

  await page.getByTestId('catalog-page-next').click();
  await expect(page).toHaveURL(/[?&]page=2(?:&|$)/);
  await expect.poll(async () => (await catalogNames(page)).join('|')).not.toBe(firstPage.join('|'));
  await expect(page.getByTestId('catalog-item')).toHaveCount(6);

  const secondPage = await catalogNames(page);
  expect(secondPage).toHaveLength(6);
  expect(secondPage.filter((name) => firstPage.includes(name))).toEqual([]);
});

test('catalog filters products by category', async ({ page }) => {
  await page.goto(CATALOG_PATH);

  await page.getByTestId('filter-category').selectOption('videokarty');

  await expect(page).toHaveURL(/[?&]category=videokarty(?:&|$)/);
  await expect(page.getByTestId('catalog-item')).toHaveCount(4);

  const names = await catalogNames(page);
  expect(names).toEqual(
    expect.arrayContaining([
      'NVIDIA GeForce RTX 4090',
      'NVIDIA GeForce RTX 4070',
      'NVIDIA GeForce RTX 4060 Ti',
      'AMD Radeon RX 7800 XT',
    ]),
  );
});

test('catalog searches product names case-insensitively', async ({ page }) => {
  await page.goto(CATALOG_PATH);

  await page.getByTestId('filter-search').fill('RTX');

  await expect(page).toHaveURL(/[?&]q=RTX(?:&|$)/);
  await expect(page.getByTestId('catalog-item')).toHaveCount(3);

  const names = await catalogNames(page);
  expect(names).toHaveLength(3);
  for (const name of names) {
    expect(name.toLowerCase()).toContain('rtx');
  }
});

test('catalog applies inclusive price bounds', async ({ page }) => {
  await page.goto(CATALOG_PATH);

  await page.getByTestId('filter-price-min').fill('199990');
  await expect(page).toHaveURL(/[?&]priceMin=199990(?:&|$)/);
  await expect(page.getByTestId('catalog-item')).toHaveCount(1);
  await expect(page.getByTestId('catalog-item-name')).toHaveText('NVIDIA GeForce RTX 4090');

  await page.getByTestId('filter-price-max').fill('199990');
  await expect(page).toHaveURL(/[?&]priceMin=199990(?:&|$)/);
  await expect(page).toHaveURL(/[?&]priceMax=199990(?:&|$)/);
  await expect(page.getByTestId('catalog-item')).toHaveCount(1);

  const price = await page.getByTestId('catalog-item-price').textContent();
  expect(price?.replace(/\s/g, '')).toContain('199990');
});

test('catalog filters out unavailable products when requested', async ({ page }) => {
  await page.goto(CATALOG_PATH);

  await page.getByTestId('filter-category').selectOption('materinskie-platy');
  await expect(page.getByTestId('catalog-item')).toHaveCount(4);

  await page.getByRole('checkbox', { name: /Только в наличии/ }).check();

  await expect(page).toHaveURL(/[?&]available=1(?:&|$)/);
  await expect(page.getByTestId('catalog-item')).toHaveCount(3);

  const availability = await page
    .getByTestId('catalog-item-availability')
    .evaluateAll((nodes) => nodes.map((node) => node.getAttribute('data-available')));

  expect(availability).toEqual(['true', 'true', 'true']);
});

test('catalog shows an empty state and resets filters', async ({ page }) => {
  await page.goto(CATALOG_PATH);

  await page.getByTestId('filter-search').fill('no-such-product-xyz');

  await expect(page.getByTestId('catalog-empty')).toBeVisible();
  await expect(page.getByTestId('catalog-item')).toHaveCount(0);

  await page.getByTestId('filter-reset').click();

  await expect.poll(() => new URL(page.url()).search).toBe('');
  await expect(page.getByTestId('catalog-item')).toHaveCount(6);
  await expect(page.getByTestId('catalog-empty')).toHaveCount(0);
});

test('catalog opens a product card by direct link', async ({ page }) => {
  await page.goto(CATALOG_PATH);

  const firstProduct = page.getByTestId('catalog-item-name').first();
  const productName = (await firstProduct.textContent()) ?? '';
  expect(productName).not.toBe('');

  await firstProduct.click();

  await expect(page).toHaveURL(/\/products\/\d+$/);
  await expect(page.getByTestId('product-name')).toHaveText(productName);
  await expect(page.getByTestId('product-description')).not.toBeEmpty();
  await expect(page.getByTestId('product-price')).not.toBeEmpty();

  const productUrl = page.url();
  await page.reload();

  await expect(page).toHaveURL(productUrl);
  await expect(page.getByTestId('product-name')).toHaveText(productName);
});

test('unavailable product card opens by direct link', async ({ page, request }) => {
  const response = await request.get('/api/products?perPage=48');
  expect(response.ok()).toBeTruthy();

  const body = (await response.json()) as {
    items: Array<{ id: string; slug: string; name: string; available: boolean }>;
  };
  const product = body.items.find((item) => item.slug === 'msi-mag-b760-tomahawk');

  if (!product) {
    throw new Error('Unavailable product is missing from the catalog seed.');
  }

  expect(product.available).toBe(false);

  await page.goto(`/products/${product.id}`);

  await expect(page.getByTestId('product-name')).toHaveText(product.name);
  await expect(page.getByText('Нет в наличии').first()).toBeVisible();
});
