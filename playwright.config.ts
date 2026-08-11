import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E config.
 *
 * Prerrequisito: tener corriendo `php artisan serve` en otro proceso
 * (o Laragon) y la DB con `php artisan migrate:fresh --seed --force`.
 *
 * Para correr:
 *   npx playwright test
 *   npx playwright test --headed      # con navegador visible
 *   npx playwright test tests/e2e/buyer.spec.ts  # solo flujo comprador
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 5_000 },

    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,

    reporter: process.env.CI ? 'github' : 'list',

    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://ecomers.test',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
