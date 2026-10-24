import { test, expect } from '@playwright/test';

// Temporary infrastructure smoke test until the Hexlet browser suite is added.
test('frontend loads and catalog is rendered from API', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle(/Интернет-магазин/);
  await expect(page.locator('#root')).not.toBeEmpty();
  await expect(page.getByTestId('home-promo-item').first()).toBeVisible();

  await page.goto('/catalog');

  await expect(page.getByTestId('catalog-list')).toBeVisible();
  await expect(page.getByTestId('catalog-item').first()).toBeVisible();
  await expect(page.getByTestId('catalog-item-name').first()).not.toBeEmpty();
});
