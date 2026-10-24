import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.APP_URL ?? 'http://localhost:8080';

export default defineConfig({
  testDir: './__tests__',
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  reporter: process.env.CI
    ? [
        ['github'],
        ['html', { outputFolder: 'tmp/artifacts/report', open: 'never' }],
      ]
    : [
        ['list'],
        ['html', { outputFolder: 'tmp/artifacts/report', open: 'never' }],
      ],
  outputDir: 'tmp/artifacts/test-results',
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
