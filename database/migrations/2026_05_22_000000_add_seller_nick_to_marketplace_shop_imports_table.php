<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_shop_imports', function (Blueprint $table) {
            $table->string('seller_nick')->nullable()->after('seller_id');
            $table->index('seller_nick');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_shop_imports', function (Blueprint $table) {
            $table->dropIndex(['seller_nick']);
            $table->dropColumn('seller_nick');
        });
    }
};
