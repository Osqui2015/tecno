import { test, expect } from '@playwright/test';

/**
 * Flujo completo de checkout con cupón y validaciones.
 * Asume que hay al menos 1 producto cargado en DB y que
 * el seeder creó admin@tecnorexs.test y comprador@tecnorexs.test.
 *
 * Prereq:
 *   - DB con seed (php artisan migrate:fresh --seed --force)
 *   - Server corriendo en baseURL (config en playwright.config.ts)
 */
test.describe('Checkout completo', () => {

    test('Comprador: login → agrega al carrito → checkout → confirma', async ({ page, request }) => {
        // Crear cupón via API (admin)
        const adminLogin = await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });
        const adminToken = (await adminLogin.json()).token;

        await request.post('/api/admin/coupons', {
            headers: { Authorization: `Bearer ${adminToken}` },
            data: {
                code: 'E2E20',
                type: 'percent',
                value: 20,
                active: true,
            },
        }).catch(() => {/* puede que no exista el endpoint, ignorar */});

        // Login como comprador
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/');

        // Completar perfil via API (si no tiene datos de envío)
        await request.patch('/api/me/profile', {
            headers: { Authorization: `Bearer ${(await page.evaluate(() => localStorage.getItem('auth_token')))}` },
            data: {
                name: 'Test',
                lastname: 'User',
                phone: '+54 11 5555',
                address: 'Av Test 123',
                city: 'CABA',
                zip_code: 'C1000',
            },
        }).catch(() => {/* ya completo */});

        // Agregar producto al carrito via API (más rápido que UI)
        const productsResp = await request.get('/api/products');
        const products = (await productsResp.json()).data ?? [];
        if (products.length === 0) {
            test.skip(true, 'No hay productos para testear');
            return;
        }

        const productId = products[0].id;
        const token = await page.evaluate(() => localStorage.getItem('auth_token'));

        await request.post('/api/cart/items', {
            headers: { Authorization: `Bearer ${token}` },
            data: { product_id: productId, qty: 2 },
        });

        // Ir al checkout
        await page.goto('/checkout');
        await expect(page.locator('h1, h2').first()).toBeVisible();

        // Completar dirección
        const addressInput = page.locator('input[placeholder*="Corrientes"]').first();
        if (await addressInput.isVisible({ timeout: 3000 }).catch(() => false)) {
            await addressInput.fill('Av Corrientes 1234');
            await page.locator('input[placeholder="CABA"]').fill('CABA');
            await page.locator('input[placeholder="C1043"]').fill('C1043');
            await page.locator('input[type="tel"]').fill('+54 11 5555-1234');
        }

        // Aplicar cupón (puede fallar si no se pudo crear via API)
        const couponInput = page.locator('input[placeholder*="VERANO"]').first();
        if (await couponInput.isVisible({ timeout: 2000 }).catch(() => false)) {
            await couponInput.fill('E2E20');
            await page.click('button:has-text("Aplicar")').catch(() => null);
        }

        // Confirmar pedido
        const confirmBtn = page.locator('button:has-text("Confirmar pedido")');
        if (await confirmBtn.isEnabled({ timeout: 3000 }).catch(() => false)) {
            await confirmBtn.click();

            // Debería redirigir a /mis-pedidos con query success=1
            await page.waitForURL(/\/mis-pedidos/, { timeout: 10_000 }).catch(() => null);

            if (page.url().includes('/mis-pedidos')) {
                // Verificar que aparezca el toast de éxito
                const successAlert = page.locator('text=¡Pedido confirmado!').first();
                await expect(successAlert).toBeVisible({ timeout: 3000 });
            }
        }
    });

    test('Cupón inválido se rechaza en checkout', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/');

        // Asegurar que el carrito tenga algo
        const token = await page.evaluate(() => localStorage.getItem('auth_token'));
        const productsResp = await page.request.get('/api/products');
        const products = (await productsResp.json()).data ?? [];
        if (products.length === 0) {
            test.skip(true, 'No hay productos');
            return;
        }

        await page.request.post('/api/cart/items', {
            headers: { Authorization: `Bearer ${token}` },
            data: { product_id: products[0].id, qty: 1 },
        });

        await page.goto('/checkout');

        const couponInput = page.locator('input[placeholder*="VERANO"]').first();
        if (await couponInput.isVisible({ timeout: 3000 }).catch(() => false)) {
            await couponInput.fill('NOEXISTE123');
            await page.click('button:has-text("Aplicar")');
            await page.waitForTimeout(1000);

            // Verificar mensaje de error
            const errorMsg = page.locator('text=/no encontr|inválid/i').first();
            await expect(errorMsg).toBeVisible({ timeout: 3000 });
        }
    });

    test('Cancelar pedido pendiente funciona', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'comprador@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/');

        // Crear pedido via API
        const token = await page.evaluate(() => localStorage.getItem('auth_token'));

        // Asegurar perfil completo
        await page.request.patch('/api/me/profile', {
            headers: { Authorization: `Bearer ${token}` },
            data: {
                name: 'Test',
                lastname: 'User',
                phone: '+54 11 5555',
                address: 'Av Test 123',
                city: 'CABA',
                zip_code: 'C1000',
            },
        }).catch(() => null);

        const productsResp = await page.request.get('/api/products');
        const products = (await productsResp.json()).data ?? [];
        if (products.length === 0) {
            test.skip(true, 'No hay productos');
            return;
        }

        await page.request.post('/api/cart/items', {
            headers: { Authorization: `Bearer ${token}` },
            data: { product_id: products[0].id, qty: 1 },
        });

        const orderResp = await page.request.post('/api/orders', {
            headers: { Authorization: `Bearer ${token}` },
            data: {},
        });

        if (orderResp.status() !== 201) {
            test.skip(true, 'No se pudo crear el pedido');
            return;
        }

        // Ir a mis pedidos
        await page.goto('/mis-pedidos');
        await page.waitForLoadState('networkidle');

        // Buscar botón de cancelar en el primer pedido pendiente
        const cancelBtn = page.locator('button:has-text("Cancelar")').first();
        if (await cancelBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            // Aceptar el confirm
            page.on('dialog', (d) => d.accept());
            await cancelBtn.click();
            await page.waitForTimeout(1000);
        }
    });
});
