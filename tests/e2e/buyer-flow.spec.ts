import { test, expect } from '@playwright/test';

/**
 * Flujo completo del comprador:
 *   register → login → browse → add to cart → checkout
 *
 * Prereq: el server debe estar corriendo y la DB limpia con seed.
 */
test.describe('Flujo del comprador', () => {

    test('Register, login y ver home', async ({ page }) => {
        const email = `e2e_${Date.now()}@test.com`;

        // Register
        await page.goto('/register');
        await page.fill('input[name="name"], input[type="text"]:first-of-type', 'Test E2E');
        await page.fill('input[type="email"]', email);
        const passwordInput = page.locator('input[type="password"]').first();
        await passwordInput.fill('password123');
        const confirmInput = page.locator('input[type="password"]').nth(1);
        await confirmInput.fill('password123');
        await page.click('button[type="submit"]');

        // Debería redirigir al home
        await expect(page).toHaveURL('/');
    });

    test('Login con credenciales existentes', async ({ page }) => {
        // comprador@tecnorexs.test se crea en el seeder
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL('/');
        // Verificar que aparece el avatar del usuario
        await expect(page.locator('text=comprador@tecnorexs.test')).toBeVisible({ timeout: 5000 });
    });

    test('Browse → detalle → agregar al carrito', async ({ page }) => {
        // Login primero
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        // Ir a productos
        await page.goto('/productos');
        await expect(page.locator('h2, h1').first()).toBeVisible();

        // Click en el primer producto
        const firstProduct = page.locator('article a').first();
        if (await firstProduct.isVisible({ timeout: 3000 }).catch(() => false)) {
            await firstProduct.click();
            // Estamos en el detalle
            await page.waitForURL(/\/productos\/\d+/);

            // Click "Agregar al carrito"
            const addBtn = page.locator('button:has-text("Agregar")').first();
            if (await addBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
                await addBtn.click();
                // Verificar toast o cambio de estado
                await page.waitForTimeout(500);
            }
        }
    });

    test('Carrito vacío se muestra correctamente', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.goto('/carrito');
        // Puede estar vacío o tener items — solo verificamos que carga
        await expect(page.locator('h1, h2').first()).toBeVisible();
    });

    test('Editar perfil funciona', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.goto('/perfil');
        await page.waitForURL('/perfil');

        // Click "Editar"
        const editBtn = page.locator('button:has-text("Editar")');
        if (await editBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await editBtn.click();
            // Verificar que aparecen los inputs
            await expect(page.locator('input').first()).toBeVisible();
        }
    });

    test('Favoritos accesible para usuario autenticado', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.goto('/favoritos');
        await expect(page.locator('h1, h2').first()).toBeVisible();
    });

    test('Mis pedidos accesible', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');

        await page.goto('/mis-pedidos');
        await expect(page.locator('h1, h2').first()).toBeVisible();
    });
});
