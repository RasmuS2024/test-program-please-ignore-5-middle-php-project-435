import { test, expect } from '@playwright/test';

type Promo = {
  id: string;
  title: string;
  text: string;
  product: {
    id: string;
    name: string;
  };
};

test('home page promo blocks link to their own products', async ({ page, request }) => {
  const response = await request.get('/api/promos');
  expect(response.ok()).toBeTruthy();

  const promos = (await response.json()) as Promo[];
  expect(promos.length).toBeGreaterThanOrEqual(2);

  await page.goto('/');

  const cards = page.getByTestId('home-promo-item');
  await expect(cards).toHaveCount(promos.length);

  for (const promo of promos) {
    const card = cards.filter({ hasText: promo.title });

    await expect(card).toHaveCount(1);
    await expect(card).toContainText(promo.text);
    await expect(card).toHaveAttribute('href', `/products/${promo.product.id}`);
  }

  for (const promo of promos) {
    await page.goto('/');

    const card = page.getByTestId('home-promo-item').filter({ hasText: promo.title });
    await card.click();

    await expect(page).toHaveURL(new RegExp(`/products/${promo.product.id}$`));
    await expect(page.getByTestId('product-name')).toHaveText(promo.product.name);
  }
});
