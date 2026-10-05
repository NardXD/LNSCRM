<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'lead_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('lead_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('contracts', 'client_id')) {
            return;
        }

        $hasClientForeign = collect(Schema::getForeignKeys('contracts'))
            ->contains(fn (array $fk) => ($fk['columns'] ?? []) === ['client_id']);
        if ($hasClientForeign) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['client_id']);
            });
        }

        foreach (Schema::getIndexes('contracts') as $index) {
            $name = $index['name'] ?? '';
            $columns = $index['columns'] ?? [];
            if ($name !== '' && in_array('client_id', $columns, true) && empty($index['primary'])) {
                Schema::table('contracts', function (Blueprint $table) use ($name) {
                    $table->dropIndex($name);
                });
            }
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
        });
    }
};
