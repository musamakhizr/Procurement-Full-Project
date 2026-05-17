<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_shop_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('seed_url');
            $table->string('seed_platform')->nullable();
            $table->string('seed_num_iid')->nullable();
            $table->string('seller_id')->nullable();
            $table->string('shop_id')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_product_links')->default(0);
            $table->unsignedInteger('imported_product_links')->default(0);
            $table->longText('error')->nullable();
            $table->longText('product_links')->nullable();
            $table->longText('raw_seed_payload')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['seller_id', 'shop_id']);
            $table->index('seed_num_iid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shop_imports');
    }
};
