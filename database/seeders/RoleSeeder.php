<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manageCompanies = Permission::firstOrCreate(['name' => 'manage-companies']);
        $manageUsers = Permission::firstOrCreate(['name' => 'manage-users']);
        $impersonateUsers = Permission::firstOrCreate(['name' => 'impersonate-users']);
        $manageRoles = Permission::firstOrCreate(['name' => 'manage-roles']);

        Role::firstOrCreate(['name' => 'parent-admin'])
            ->syncPermissions([$manageCompanies, $manageUsers, $impersonateUsers, $manageRoles]);

        Role::firstOrCreate(['name' => 'child-admin'])
            ->syncPermissions([$manageUsers, $impersonateUsers]);

        Role::firstOrCreate(['name' => 'agent']);
    }
}
