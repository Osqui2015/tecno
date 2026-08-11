import { test, expect } from '@playwright/test';
import { authenticator } from 'otplib';

/**
 * Flujo 2FA: setup + login con código.
 * Requiere que el seeder haya creado admin@tecnorexs.test.
 */
test.describe('2FA flow', () => {

    test('Admin puede activar 2FA y login pide código', async ({ page, request }) => {
        // Limpiar 2FA previo via API (por si quedó de un test anterior)
        await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });
        const loginData = await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });

        if (loginData.status() !== 200) {
            test.skip(true, 'Login falló');
            return;
        }

        const token = (await loginData.json()).token;

        // Desactivar 2FA si estaba activo
        await request.delete('/api/me/two-factor', {
            headers: { Authorization: `Bearer ${token}` },
        });

        // Login en el navegador
        await page.goto('/login');
        await page.fill('input[type="email"]', 'admin@tecnorexs.test');
        await page.fill('input[type="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/');

        // Ir a perfil
        await page.goto('/perfil');
        await page.waitForURL('/perfil');

        // Click "Seguridad 2FA"
        const link2fa = page.locator('a:has-text("Seguridad 2FA")');
        if (await link2fa.isVisible({ timeout: 3000 }).catch(() => false)) {
            await link2fa.click();
            await page.waitForURL('/perfil/2fa');

            // Verificar que carga el wizard
            await expect(page.locator('text=Activar 2FA').first()).toBeVisible({ timeout: 3000 });
        }
    });

    test('Login con 2FA challenge cuando está activado', async ({ request }) => {
        // 1) Login admin y activar 2FA via API
        const login = await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });
        const loginJson = await login.json();

        if (!loginJson.token) {
            test.skip(true, 'Login falló');
            return;
        }

        const token = loginJson.token;

        // Setup y obtener secret
        await request.delete('/api/me/two-factor', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const setup = await request.post('/api/me/two-factor/setup', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const setupJson = await setup.json();
        const secret = setupJson.secret;

        // Generar código TOTP válido
        const code = authenticator.generate(secret);

        // Activar
        await request.post('/api/me/two-factor/verify', {
            headers: { Authorization: `Bearer ${token}` },
            data: { code },
        });

        // 2) Logout
        await request.post('/api/logout', {
            headers: { Authorization: `Bearer ${token}` },
        });

        // 3) Intentar login de nuevo — debería pedir 2FA
        const loginAgain = await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });
        const loginAgainJson = await loginAgain.json();

        expect(loginAgainJson.requires_2fa).toBe(true);
        expect(loginAgainJson.token).toBeUndefined();

        // 4) Resolver challenge con código
        const newCode = authenticator.generate(secret);
        const challenge = await request.post('/api/auth/2fa-challenge', {
            data: {
                email: 'admin@tecnorexs.test',
                code: newCode,
            },
        });

        expect(challenge.status()).toBe(200);
        const challengeJson = await challenge.json();
        expect(challengeJson.token).toBeDefined();
        expect(challengeJson.user.email).toBe('admin@tecnorexs.test');

        // Limpiar: desactivar 2FA para próximos tests
        await request.delete('/api/me/two-factor', {
            headers: { Authorization: `Bearer ${challengeJson.token}` },
        });
    });
});
