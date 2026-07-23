<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add department_id column
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('company_id');
        });

        // Add foreign key constraint separately
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });

        // Migrate existing department string values to department_id
        // Get all unique department values grouped by company
        $users = DB::table('users')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->select('company_id', 'department')
            ->distinct()
            ->get();

        foreach ($users as $userData) {
            if (!$userData->company_id) {
                continue;
            }

            // Find or create department for this company
            $department = DB::table('departments')
                ->where('company_id', $userData->company_id)
                ->where('name', $userData->department)
                ->first();

            if (!$department) {
                $departmentId = DB::table('departments')->insertGetId([
                    'name' => $userData->department,
                    'company_id' => $userData->company_id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $departmentId = $department->id;
            }

            // Update users with this department
            DB::table('users')
                ->where('company_id', $userData->company_id)
                ->where('department', $userData->department)
                ->update(['department_id' => $departmentId]);
        }

        // Drop the old department column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back department column
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('company_id');
        });

        // Populate department from department relationship
        $users = DB::table('users')
            ->whereNotNull('department_id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->select('users.id', 'departments.name as department_name')
            ->get();

        foreach ($users as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['department' => $user->department_name]);
        }

        // Drop department_id column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
