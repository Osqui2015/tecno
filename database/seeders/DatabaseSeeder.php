<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ====== ROLES (Spatie) ======
        Role::firstOrCreate(['name' => User::ROLE_COMPRADOR, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => User::ROLE_ADMIN,     'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin',         'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-pedidos',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-productos',     'guard_name' => 'web']);

        // ====== USUARIOS ======
        $admin = User::updateOrCreate(
            ['email' => 'admin@tecnorexs.test'],
            [
                'name'              => 'Admin Tecno-Rexs',
                'password'          => Hash::make('password'),
                'role'              => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['super-admin', User::ROLE_ADMIN]);

        $buyer = User::updateOrCreate(
            ['email' => 'comprador@tecnorexs.test'],
            [
                'name'              => 'Cliente Demo',
                'lastname'          => 'Tecno-Rexs',
                'phone'             => '+54 381 555-1234',
                'address'           => 'Av. Aconquija 1234',
                'city'              => 'San Miguel de Tucumán',
                'zip_code'          => 'T4000',
                'country'           => 'Argentina',
                'password'          => Hash::make('password'),
                'role'              => User::ROLE_COMPRADOR,
                'email_verified_at' => now(),
            ]
        );
        $buyer->syncRoles([User::ROLE_COMPRADOR]);

        // ====== CATEGORÍAS ======
        $categoriesData = [
            ['name' => 'Electrónica',  'description' => 'Smartphones, notebooks, audio y más'],
            ['name' => 'Indumentaria', 'description' => 'Ropa para todos los estilos'],
            ['name' => 'Hogar',        'description' => 'Decoración, cocina, jardín'],
            ['name' => 'Deportes',     'description' => 'Fútbol, running, fitness'],
            ['name' => 'Libros',       'description' => 'Best sellers y novedades literarias'],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['name']] = Category::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'slug' => Str::slug($cat['name']),
                    'description' => $cat['description'],
                ]
            );
        }

        // ====== PRODUCTOS ======
        $productsData = [
            // Electrónica
            ['Electrónica', 'Auriculares Bluetooth',       'Auriculares inalámbricos con cancelación de ruido',         45999,  25],
            ['Electrónica', 'Smartphone Pro 256GB',        'Último modelo con cámara de 108MP y 5G',                    899999, 12],
            ['Electrónica', 'Notebook 14" 16GB',           'Notebook ultraliviana, procesador de última generación',   1299999, 8],
            ['Electrónica', 'Smart TV 55" 4K',              'Televisor 4K UHD con HDR y Google TV',                     749999, 6],
            ['Electrónica', 'Smartwatch Deportivo',        'Reloj inteligente con GPS y sensor cardíaco',               159999, 30],

            // Indumentaria
            ['Indumentaria', 'Remera Algodón Premium',     'Remera 100% algodón, varios talles',                        12999,  100],
            ['Indumentaria', 'Jeans Slim Fit',             'Jeans clásicos corte slim',                                34999,  60],
            ['Indumentaria', 'Campera de Jean',            'Campera clásica de jean con forro interior',               59999,  40],
            ['Indumentaria', 'Zapatillas Urbanas',         'Zapatillas cómodas para el día a día',                      75999,  45],

            // Hogar
            ['Hogar', 'Set de Ollas Antiadherentes',      'Set de 5 ollas con recubrimiento antiadherente',            89999,  15],
            ['Hogar', 'Lámpara LED Inteligente',         'Lámpara RGB con control por app',                           24999,  50],
            ['Hogar', 'Set de Sábanas 100% Algodón',      'Sábanas queen size, 600 hilos',                             42999,  35],

            // Deportes
            ['Deportes', 'Pelota de Fútbol Profesional', 'Pelota tamaño 5 aprobada por FIFA',                         29999,  80],
            ['Deportes', 'Mochila Trekking 40L',          'Mochila resistente con múltiples compartimentos',           54999,  22],
            ['Deportes', 'Bicicleta Mountain Bike 29"',  'Bicicleta rodado 29 con 21 cambios',                       459999,  5],
            ['Deportes', 'Set de Pesas 20kg',             'Set completo de pesas regulables',                          89999,  18],

            // Libros
            ['Libros', 'Cien Años de Soledad',            'Obra maestra de Gabriel García Márquez',                    18999,  70],
            ['Libros', 'El Quijote de la Mancha',         'Clásico de Miguel de Cervantes',                            15999,  60],
            ['Libros', 'Sapiens',                         'De Yuval Noah Harari',                                       22999,  55],
        ];

        foreach ($productsData as [$catName, $name, $desc, $price, $stock]) {
            Product::updateOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name) . '-' . Str::random(4),
                    'description' => $desc,
                    'price' => $price,
                    'stock' => $stock,
                    'category_id' => $categories[$catName]->id,
                    'active' => true,
                ]
            );
        }
    }
}
