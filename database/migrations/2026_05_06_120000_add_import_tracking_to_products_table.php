<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('import_status')->nullable()->after('source_category_label');
            $table->text('import_error')->nullable()->after('import_status');
            $table->json('source_payload')->nullable()->after('import_error');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'import_status',
                'import_error',
                'source_payload',
            ]);
        });
    }
};
