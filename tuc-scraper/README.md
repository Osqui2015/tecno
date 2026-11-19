# TucScraper — Extractor Local de Productos TusTecnologíasTuc

Extractor autónomo basado en **Node.js** y **Playwright (Chromium real)** para obtener productos de `tustecnologiastuc.com` eludiendo de forma legítima los bloqueos de Cloudflare gracias al uso de IP residencial y navegador real.

---

## 🚀 Requisitos

- Node.js v18+
- Chromium (instalado automáticamente con Playwright)

---

## 📦 Instalación

Dentro de esta carpeta `tuc-scraper/`:

```bash
npm install
```

*(Si no tenés Playwright instalado globalmente o los navegadores descargados: `npx playwright install chromium`)*

---

## 🧪 Modo Prueba (Extrae 5 productos)

Para verificar rápidamente la conexión, el bypass de Cloudflare y el formato del JSON sin esperar el catálogo completo:

```bash
node scraper.js --test
```

Genera el archivo: `productos_test.json`.

---

## 🚀 Modo Completo (Todo el catálogo)

Para extraer los ~2226 productos completos:

```bash
node scraper.js
```

Genera el archivo: `productos.json` (~2200 productos en ~32 páginas).

---

## 📤 Cómo importar a tu tienda Laravel (Hostinger)

1. Subí el archivo generado `productos.json` a tu servidor en:
   ```
   storage/app/productos.json
   ```
2. En la consola SSH de Hostinger ejecutá:
   ```bash
   php artisan tuc:import
   ```
   *(O si querés simular primero sin guardar en la base de datos: `php artisan tuc:import --dry-run`)*.
