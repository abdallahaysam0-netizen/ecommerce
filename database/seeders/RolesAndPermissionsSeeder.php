<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Define Permissions
        |--------------------------------------------------------------------------
        */
        $permissions = [
            // Categories
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',

            // Products
            'view products',
            'create products',
            'edit products',
            'delete products',

            // Orders
            'view orders',
            'create orders',
            'update orders',
            'cancel orders',
            'delete orders',

            // Users
            'view users',
            'create users',
            'edit users',
            'delete users',
        ];

        // create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        // Admin Role
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'sanctum',
        ]);

        // Customer Role
        $customerRole = Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'sanctum',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assign Permissions
        |--------------------------------------------------------------------------
        */

        // admin gets everything
        $adminRole->syncPermissions(Permission::where('guard_name', 'sanctum')->get());

        // customer permissions (limited)
        $customerRole->syncPermissions([
            'view categories',
            'view products',
            'create orders',
            'view orders',
            'cancel orders',
        ]);
    }
}
