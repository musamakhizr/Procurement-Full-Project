<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_list_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->string('category_name')->nullable();
            $table->text('image_url')->nullable();
            $table->string('variant_sku_id')->nullable();
            $table->string('variant_label')->nullable();
            $table->json('variant_options')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unsignedInteger('moq')->nullable();
            $table->json('product_snapshot');
            $table->timestamps();

            $table->index(['quote_request_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_request_items');
    }
};
