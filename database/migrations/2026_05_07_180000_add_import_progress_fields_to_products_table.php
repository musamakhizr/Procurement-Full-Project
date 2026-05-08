<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('import_total_tasks')->default(0)->after('import_error');
            $table->unsignedInteger('import_completed_tasks')->default(0)->after('import_total_tasks');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'import_total_tasks',
                'import_completed_tasks',
            ]);
        });
    }
};
