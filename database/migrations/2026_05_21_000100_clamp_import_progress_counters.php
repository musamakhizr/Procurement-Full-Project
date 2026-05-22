<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereColumn('import_completed_tasks', '>', 'import_total_tasks')
            ->update([
                'import_completed_tasks' => DB::raw('import_total_tasks'),
            ]);
    }

    public function down(): void
    {
        //
    }
};
