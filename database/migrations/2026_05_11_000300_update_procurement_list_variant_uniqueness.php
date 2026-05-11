<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'procurement_list_items';
    private const OLD_UNIQUE = 'procurement_list_items_user_id_product_id_unique';
    private const NEW_UNIQUE = 'procurement_list_user_product_variant_unique';

    public function up(): void
    {
        if ($this->hasIndex(self::OLD_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        if (! $this->hasIndex(self::NEW_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['user_id', 'product_id', 'product_variant_id'], self::NEW_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex(self::NEW_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        if (! $this->hasIndex(self::OLD_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['user_id', 'product_id']);
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', self::TABLE)
                ->where('index_name', $indexName)
                ->exists();
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA index_list('.self::TABLE.')'))
                ->contains(fn ($index) => ($index->name ?? null) === $indexName);
        }

        return collect(Schema::getIndexes(self::TABLE))
            ->contains(fn ($index) => ($index['name'] ?? null) === $indexName);
    }
};
