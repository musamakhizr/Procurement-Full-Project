<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('import_processing_started_at')->nullable()->after('import_completed_tasks');
            $table->timestamp('import_processing_completed_at')->nullable()->after('import_processing_started_at');
            $table->unsignedBigInteger('import_processing_duration_ms')->nullable()->after('import_processing_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'import_processing_started_at',
                'import_processing_completed_at',
                'import_processing_duration_ms',
            ]);
        });
    }
};
