import { test, expect } from '@playwright/test';

test('user can register, keep a session, log out and sign in again', async ({ page }) => {
  const email = `auth-${Date.now()}-${Math.random().toString(16).slice(2)}@example.com`;

  await page.goto('/signup');
  await page.getByTestId('auth-email').fill(email);
  await page.getByTestId('auth-password').fill('secret1');
  await page.getByTestId('auth-submit').click();

  await expect(page).toHaveURL(/\/catalog$/);
  await expect(page.getByTestId('nav-account')).toBeVisible();

  await page.reload();
  await expect(page.getByTestId('nav-account')).toBeVisible();

  await page.getByTestId('nav-signout').click();
  await expect(page.getByTestId('nav-signin')).toBeVisible();
  await expect(page.getByTestId('nav-account')).toHaveCount(0);

  await page.goto('/account');
  await expect(page).toHaveURL(/\/signin$/);

  await page.getByTestId('auth-email').fill(email);
  await page.getByTestId('auth-password').fill('secret1');
  await page.getByTestId('auth-submit').click();

  await expect(page).toHaveURL(/\/catalog$/);
  await expect(page.getByTestId('nav-account')).toBeVisible();
});
