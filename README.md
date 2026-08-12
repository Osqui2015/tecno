# Tecno-Rexs — Plataforma E-commerce & Distribuidor

Este es un sistema e-commerce premium diseñado para la venta y distribución de productos tecnológicos. Cuenta con un panel administrativo avanzado que automatiza la sincronización de catálogos mediante scraping de múltiples importadores y distribuidores de tecnología de Argentina.

---

## 🔍 ¿De qué se trata el proyecto?

El sitio web funciona como un catálogo interactivo de cara al comprador y un gestor de inventario automatizado de cara al administrador:

1. **Catálogo para Clientes (E-commerce)**:
   - Navegación interactiva de categorías, marcas y productos en stock.
   - Carrito de compras, cupones de descuento, wishlist y reseñas de productos.
   - Proceso de checkout simplificado con confirmación de pedido por WhatsApp.
   - Autenticación segura de dos factores (2FA).

2. **Panel Administrativo (Control de Proveedores)**:
   - **Scrapers Automáticos**: Importación y actualización en tiempo real desde proveedores externos (**Dazimportadora** y **TusTecnología**) usando comandos de consola (`php artisan daz:scrape` y `php artisan tuc:scrape`).
   - **Aumento Global (Markup)**: Herramienta masiva para actualizar el porcentaje de ganancia sobre el precio base de los proveedores por origen o categoría.
   - **Gestión de Pedidos & WhatsApp**: Sistema inteligente de confirmación de stock con generación de plantillas de mensajes personalizadas listas para enviar por WhatsApp al cliente.

---

## 🛠️ Tecnologías Utilizadas

La aplicación está construida sobre un stack moderno y escalable:

### Backend (API)
- **Framework**: [Laravel 11](https://laravel.com) (PHP 8.2+)
- **Buscador**: [Laravel Scout](https://laravel.com/docs/scout) para búsquedas de alta performance.
- **Autenticación**: [Laravel Sanctum](https://laravel.com/docs/sanctum) para control de sesiones seguro mediante tokens.
- **Scraping**: Integración nativa mediante cargadores y parseadores HTML dinámicos.

### Frontend (Single Page Application)
- **Framework**: [Vue 3](https://vuejs.org) (Composition API con TypeScript).
- **Compilador/Bundler**: [Vite](https://vite.dev) para desarrollo rápido y optimización del bundle de producción.
- **Manejador de Estado**: [Pinia](https://pinia.vuejs.org) para el manejo de sesiones de usuario, carritos, productos y pedidos.
- **Estilos (CSS)**: Tailwind CSS y estilos Vanilla curados con soporte responsivo y micro-animaciones premium.

### Testing & Calidad
- **Pruebas Backend**: [PHPUnit](https://phpunit.de) para pruebas unitarias y de integración de endpoints, controladores y scrapers.
- **Pruebas Frontend (E2E)**: [Playwright](https://playwright.dev) para pruebas automatizadas completas de flujos de usuario (compra, checkout, 2fa, markup de admin).

---

## 🚀 Instalación y Desarrollo Local

### Requisitos Previos
- PHP >= 8.2
- Composer
- Node.js >= 18
- MySQL o SQLite

### Pasos
1. **Clonar el Repositorio**:
   ```bash
   git clone https://github.com/Osqui2015/tecno.git
   cd tecno
   ```
2. **Instalar Dependencias**:
   ```bash
   composer install
   npm install
   ```
3. **Configurar el Entorno**:
   - Crear una copia del archivo `.env`:
     ```bash
     cp .env.example .env
     ```
   - Configurar la base de datos y otras variables de entorno en el nuevo archivo `.env`.
   - Generar la clave de la aplicación:
     ```bash
     php artisan key:generate
     ```
4. **Migrar la Base de Datos**:
   ```bash
   php artisan migrate --seed
   ```
5. **Iniciar el Servidor de Desarrollo**:
   - Para el Backend:
     ```bash
     php artisan serve
     ```
   - Para el Frontend (Vite):
     ```bash
     npm run dev
     ```

---

## 🧪 Ejecución de Pruebas

- **Backend**:
  ```bash
  php artisan test
  ```
- **E2E Frontend**:
  ```bash
  npx playwright test
  ```
