<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackagingMigrationTest extends TestCase
{
    public function test_initial_migrations_create_and_rollback_packaging_schema(): void
    {
        config(['database.connections.packaging_migration' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('packaging_migration');

        try {
            $paths = glob(database_path('migrations/*.php'));
            sort($paths);
            foreach ($paths as $path) {
                (require $path)->up();
            }

            $this->assertTrue(Schema::hasColumn('detail_purchase_requests', 'base_unit_id'));
            foreach (['detail_supplier_quotations', 'detail_purchase_orders'] as $table) {
                $this->assertTrue(Schema::hasColumns($table, [
                    'detail_purchase_request_id', 'unit_id', 'base_unit_id',
                    'quantity', 'conversion_qty', 'base_quantity',
                ]));
            }
            $this->assertTrue(Schema::hasColumn('purchase_orders', 'quantity_difference_accepted'));
            $this->assertTrue(Schema::hasColumns('purchase_orders', ['shipping_date', 'expected_delivery_date']));

            foreach (array_reverse($paths) as $path) {
                (require $path)->down();
            }

            foreach (['detail_purchase_requests', 'detail_supplier_quotations', 'detail_purchase_orders', 'purchase_orders'] as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
        } finally {
            DB::purge('packaging_migration');
            DB::setDefaultConnection($original);
        }
    }
}
