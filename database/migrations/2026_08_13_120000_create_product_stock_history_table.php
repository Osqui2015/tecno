<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('stock_before')->nullable();
            $table->integer('stock_after');
            $table->string('source', 32); // 'scraper', 'admin', 'order', 'cancellation'
            $table->string('reference', 64)->nullable(); // ej: 'daz:scrape', 'order:123', 'admin:user:5'
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_history');
    }
};
