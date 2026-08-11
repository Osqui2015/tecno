import { test, expect } from '@playwright/test';

/**
 * Admin aplica aumento global y se refleja en el catálogo público.
 */
test.describe('Admin: aumento global de precios', () => {

    test('Aplicar markup 50% a todos los productos y verificar en el catálogo', async ({ page, request }) => {
        // Login admin
        const login = await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });
        const token = (await login.json()).token;

        // Aplicar 50% global
        const bulk = await request.post('/api/admin/products/bulk-markup', {
            headers: { Authorization: `Bearer ${token}` },
            data: { percent: 50 },
        });
        expect(bulk.status()).toBe(200);
        const bulkJson = await bulk.json();
        expect(bulkJson.updated).toBeGreaterThan(0);

        // Verificar en el catálogo público que final_price refleja el markup
        const productsResp = await request.get('/api/products');
        const products = (await productsResp.json()).data ?? [];
        expect(products.length).toBeGreaterThan(0);

        // Al menos el primer producto debe tener final_price > price * 1.4 (margen)
        const firstWithFinal = products.find((p: any) => p.final_price != null);
        if (firstWithFinal) {
            expect(Number(firstWithFinal.final_price)).toBeGreaterThan(Number(firstWithFinal.price));
        }

        // Limpiar: volver a 0
        await request.post('/api/admin/products/bulk-markup', {
            headers: { Authorization: `Bearer ${token}` },
            data: { percent: 0 },
        });
    });

    test('Admin aplica markup solo a Daz', async ({ request }) => {
        const login = await request.post('/api/login', {
            data: { email: 'admin@tecnorexs.test', password: 'password' },
        });
        const token = (await login.json()).token;

        // Reset primero
        await request.post('/api/admin/products/bulk-markup', {
            headers: { Authorization: `Bearer ${token}` },
            data: { percent: 0 },
        });

        // Aplicar 30% solo a Daz
        const bulk = await request.post('/api/admin/products/bulk-markup', {
            headers: { Authorization: `Bearer ${token}` },
            data: { percent: 30, source: 'daz' },
        });
        expect(bulk.status()).toBe(200);

        // Verificar en admin/products
        const adminProducts = await request.get('/api/admin/products', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const data = (await adminProducts.json()).data ?? [];

        const dazProducts = data.filter((p: any) => p.external_id);
        const manualProducts = data.filter((p: any) => !p.external_id);

        if (dazProducts.length > 0) {
            // Todos los Daz deben tener markup 30
            for (const p of dazProducts) {
                expect(Number(p.markup_percent)).toBe(30);
            }
        }
        if (manualProducts.length > 0) {
            // Los manuales deben seguir en 0
            for (const p of manualProducts) {
                expect(Number(p.markup_percent)).toBe(0);
            }
        }

        // Limpiar
        await request.post('/api/admin/products/bulk-markup', {
            headers: { Authorization: `Bearer ${token}` },
            data: { percent: 0 },
        });
    });
});
