<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'updated_at'], 'products_active_updated_at_index');
            $table->index(['is_active', 'category_id'], 'products_active_category_index');
            $table->index(['is_active', 'base_price'], 'products_active_base_price_index');
            $table->index(['is_active', 'lead_time_max_days'], 'products_active_lead_time_index');
            $table->index(['is_active', 'moq'], 'products_active_moq_index');
            $table->index(['is_active', 'is_verified', 'is_customizable'], 'products_active_flags_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_updated_at_index');
            $table->dropIndex('products_active_category_index');
            $table->dropIndex('products_active_base_price_index');
            $table->dropIndex('products_active_lead_time_index');
            $table->dropIndex('products_active_moq_index');
            $table->dropIndex('products_active_flags_index');
        });
    }
};
