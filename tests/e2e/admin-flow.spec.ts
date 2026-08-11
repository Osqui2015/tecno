import { test, expect } from '@playwright/test';

/**
 * Flujo del administrador:
 *   login admin → ver dashboard → productos → aplicar markup
 */
test.describe('Flujo del administrador', () => {

    test.beforeEach(async ({ page }) => {
        // Login admin
        await page.goto('/login');
        await page.fill('input[type="email"]', 'admin@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // Esperar home y verificar que tiene el link "Admin" en navbar
        await page.waitForURL('/');
    });

    test('Admin ve el link Admin en navbar', async ({ page }) => {
        const adminLink = page.locator('a[href*="admin"]').first();
        await expect(adminLink).toBeVisible({ timeout: 5000 });
    });

    test('Dashboard admin muestra contadores', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).toHaveURL('/admin');

        // Verificar que cargan las cards de métricas
        await expect(page.locator('text=Revenue').first()).toBeVisible({ timeout: 5000 });
    });

    test('Lista de productos admin', async ({ page }) => {
        await page.goto('/admin/productos');
        await expect(page).toHaveURL('/admin/productos');

        // Verificar que la tabla carga
        await expect(page.locator('table')).toBeVisible({ timeout: 5000 });
    });

    test('Lista de pedidos admin', async ({ page }) => {
        await page.goto('/admin/pedidos');
        await expect(page).toHaveURL('/admin/pedidos');
        await expect(page.locator('table').or(page.locator('text=No hay pedidos')).first()).toBeVisible({ timeout: 5000 });
    });

    test('Modal de aumento global funciona', async ({ page }) => {
        await page.goto('/admin/productos');
        await page.waitForLoadState('networkidle');

        // Click en "Aumento global"
        const bulkBtn = page.locator('button:has-text("Aumento global")');
        await expect(bulkBtn).toBeVisible({ timeout: 5000 });
        await bulkBtn.click();

        // Verificar que aparece el modal con el input de porcentaje
        await expect(page.locator('input[type="number"]').first()).toBeVisible({ timeout: 3000 });
    });

    test('Buyer NO puede acceder a /admin (redirige a home)', async ({ browser }) => {
        // Crear contexto fresco (sin auth de admin)
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.waitForURL('/');
        await page.goto('/admin');

        // Debe redirigir al home (no es admin)
        await expect(page).toHaveURL('/');

        await context.close();
    });
});
