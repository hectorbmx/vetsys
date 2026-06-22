<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_ROLE = 'admin';

    private const CANONICAL_ROLE = 'client-admin';

    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $rolesTable = $tables['roles'] ?? 'roles';
        $modelRolesTable = $tables['model_has_roles'] ?? 'model_has_roles';
        $rolePermissionsTable = $tables['role_has_permissions'] ?? 'role_has_permissions';
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';

        if (! Schema::hasTable($rolesTable) || ! Schema::hasTable($modelRolesTable)) {
            return;
        }

        DB::transaction(function () use ($rolesTable, $modelRolesTable, $rolePermissionsTable, $roleKey) {
            $legacyRole = DB::table($rolesTable)
                ->where('name', self::LEGACY_ROLE)
                ->where('guard_name', 'web')
                ->first();

            $canonicalRole = DB::table($rolesTable)
                ->where('name', self::CANONICAL_ROLE)
                ->where('guard_name', 'web')
                ->first();

            if (! $canonicalRole) {
                $canonicalRoleId = DB::table($rolesTable)->insertGetId([
                    'name' => self::CANONICAL_ROLE,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $canonicalRole = DB::table($rolesTable)->find($canonicalRoleId);
            }

            if (! $legacyRole || ! $canonicalRole) {
                return;
            }

            DB::table($modelRolesTable)
                ->where($roleKey, $legacyRole->id)
                ->get()
                ->each(function ($assignment) use ($modelRolesTable, $roleKey, $canonicalRole) {
                    $values = (array) $assignment;
                    $values[$roleKey] = $canonicalRole->id;
                    DB::table($modelRolesTable)->insertOrIgnore($values);
                });

            if (Schema::hasTable($rolePermissionsTable)) {
                DB::table($rolePermissionsTable)
                    ->where($roleKey, $legacyRole->id)
                    ->get()
                    ->each(function ($assignment) use ($rolePermissionsTable, $roleKey, $canonicalRole) {
                        $values = (array) $assignment;
                        $values[$roleKey] = $canonicalRole->id;
                        DB::table($rolePermissionsTable)->insertOrIgnore($values);
                    });
            }
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // The legacy role and its assignments are intentionally retained by up().
    }
};
