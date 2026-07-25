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
        $viewAllCustomers = Permission::firstOrCreate(['name' => 'view-all-customers']);
        $viewCompanyCustomers = Permission::firstOrCreate(['name' => 'view-company-customers']);
        $viewReports = Permission::firstOrCreate(['name' => 'view-reports']);
        $manageSettings = Permission::firstOrCreate(['name' => 'manage-settings']);
        $manageLeads = Permission::firstOrCreate(['name' => 'manage-leads']);
        $logActivities = Permission::firstOrCreate(['name' => 'log-activities']);
        $manageOwnCompany = Permission::firstOrCreate(['name' => 'manage-own-company']);
        $assignLeads = Permission::firstOrCreate(['name' => 'assign-leads']);
        $sendTestLeads = Permission::firstOrCreate(['name' => 'send-test-leads']);
        $releaseFtd = Permission::firstOrCreate(['name' => 'release-ftd']);
        $deleteLeads = Permission::firstOrCreate(['name' => 'delete-leads']);
        $deleteAffiliates = Permission::firstOrCreate(['name' => 'delete-affiliates']);
        $deleteAdvertisers = Permission::firstOrCreate(['name' => 'delete-advertisers']);
        $deleteRejectedLeads = Permission::firstOrCreate(['name' => 'delete-rejected-leads']);

        Role::firstOrCreate(['name' => 'parent-admin'])
            ->syncPermissions([
                $manageCompanies,
                $manageUsers,
                $impersonateUsers,
                $manageRoles,
                $viewAllCustomers,
                $viewReports,
                $manageSettings,
                $assignLeads,
                $sendTestLeads,
                $releaseFtd,
                $deleteLeads,
                $deleteAffiliates,
                $deleteAdvertisers,
                $deleteRejectedLeads,
            ]);

        Role::firstOrCreate(['name' => 'child-admin'])
            ->syncPermissions([$manageUsers, $impersonateUsers, $viewCompanyCustomers, $viewReports, $manageOwnCompany, $assignLeads, $sendTestLeads, $releaseFtd]);

        Role::firstOrCreate(['name' => 'sales-rep'])
            ->syncPermissions([$manageLeads, $logActivities]);
    }
}
