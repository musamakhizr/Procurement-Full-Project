<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->unsignedInteger('moq');
            $table->unsignedInteger('lead_time_min_days');
            $table->unsignedInteger('lead_time_max_days');
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_customizable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
