<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_list_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_sku_id')->nullable()->after('product_variant_id');
            $table->string('variant_label')->nullable()->after('variant_sku_id');
            $table->string('variant_image_url')->nullable()->after('variant_label');
            $table->json('variant_options')->nullable()->after('variant_image_url');

            $table->index(['user_id', 'product_id', 'product_variant_id'], 'procurement_list_user_product_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_list_items', function (Blueprint $table) {
            $table->dropIndex('procurement_list_user_product_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn([
                'variant_sku_id',
                'variant_label',
                'variant_image_url',
                'variant_options',
            ]);
        });
    }
};
