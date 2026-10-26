import { test, expect } from '@playwright/test';

type Product = {
  id: string;
  name: string;
  price: { amount: number; currency: string };
};

function normalized(value: string | null): string {
  return (value ?? '').replace(/\s/g, '');
}

test('user can place a delivery order and see it in the account', async ({ page, request }) => {
  const email = `orders-${Date.now()}-${Math.random().toString(16).slice(2)}@example.com`;
  const productsResponse = await request.get('/api/products?available=true&perPage=48');
  expect(productsResponse.ok()).toBeTruthy();

  const catalog = (await productsResponse.json()) as { items: Product[] };
  const products = catalog.items.slice(0, 2);
  expect(products).toHaveLength(2);

  const expectedTotal = products.reduce((sum, product) => sum + product.price.amount, 0);

  await page.goto('/signup');
  await page.getByTestId('auth-email').fill(email);
  await page.getByTestId('auth-password').fill('secret1');
  await page.getByTestId('auth-submit').click();

  await expect(page).toHaveURL(/\/catalog$/);
  await expect(page.getByTestId('nav-account')).toBeVisible();

  for (const product of products) {
    await page.goto(`/products/${product.id}`);
    await page.getByTestId('product-add-to-cart').click();
    await expect(page.getByText('Товар в корзине.')).toBeVisible();
  }

  await page.goto('/cart');
  await expect(page.getByTestId('cart-item')).toHaveCount(2);
  await expect(page.getByTestId('cart-total')).not.toBeEmpty();
  await page.getByTestId('cart-checkout').click();

  await expect(page).toHaveURL(/\/checkout$/);
  await expect(page.getByTestId('checkout-form')).toBeVisible();
  await page.getByTestId('checkout-name').fill('Иван Петров');
  await page.getByTestId('checkout-phone').fill('+7 999 000-11-22');
  await page.getByTestId('checkout-address').fill('ул. Ленина, 1');
  await page.getByTestId('checkout-submit').click();

  await expect(page).toHaveURL(/\/checkout\/success$/);
  await expect(page.getByTestId('order-success')).toBeVisible();
  await expect(page.getByText('Оплачен')).toBeVisible();
  expect(normalized(await page.getByTestId('order-total').textContent())).toContain(
    String(expectedTotal),
  );

  for (const product of products) {
    await expect(page.getByText(product.name, { exact: false })).toBeVisible();
  }

  await page.goto('/cart');
  await expect(page.getByTestId('cart-empty')).toBeVisible();
  await expect(page.getByTestId('cart-item')).toHaveCount(0);

  await page.goto('/account');
  await expect(page.getByTestId('account-orders')).toBeVisible();
  await expect(page.getByTestId('account-order-item')).toHaveCount(1);

  const order = page.getByTestId('account-order-item').first();
  const status = order.getByTestId('order-status');

  await expect(status).toHaveText('Оплачен');
  await expect(status).toHaveAttribute('data-status', 'paid');
  expect(normalized(await order.getByTestId('order-total').textContent())).toContain(
    String(expectedTotal),
  );

  for (const product of products) {
    await expect(order.getByText(product.name, { exact: false })).toBeVisible();
  }
});
