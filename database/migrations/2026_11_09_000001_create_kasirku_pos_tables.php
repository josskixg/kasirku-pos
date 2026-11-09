<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 32)->unique();
            $table->string('name', 120);
            $table->string('category', 48)->index();
            $table->unsignedBigInteger('price')->comment('Selling price in IDR');
            $table->unsignedBigInteger('hpp')->comment('Harga Pokok Penjualan / COGS in IDR');
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shift_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('shift_code', 40)->unique();
            $table->string('cashier_name', 80);
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_items_sold')->default(0);
            $table->unsignedBigInteger('gross_revenue')->default(0);
            $table->unsignedBigInteger('total_discount')->default(0);
            $table->unsignedBigInteger('total_tax')->default(0);
            $table->unsignedBigInteger('net_sales')->default(0);
            $table->unsignedBigInteger('total_hpp')->default(0);
            $table->bigInteger('net_profit')->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->json('payment_breakdown')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id', 40)->unique();
            $table->foreignId('shift_report_id')->nullable()->constrained('shift_reports')->nullOnDelete();
            $table->string('payment_method', 24)->default('QRIS')->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('hpp_total');
            $table->bigInteger('net_profit');
            $table->string('qris_payload', 512)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('sku', 32)->index();
            $table->string('product_name', 120);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_hpp');
            $table->unsignedBigInteger('line_total');
            $table->bigInteger('line_profit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shift_reports');
        Schema::dropIfExists('products');
    }
};
