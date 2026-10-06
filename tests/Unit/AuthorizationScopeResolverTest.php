<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthorizationScopeResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationScopeResolverTest extends TestCase
{
    /**
     * Test System Admin gets full access
     */
    public function test_system_admin_has_full_scope()
    {
        // Create a System Admin user
        $user = User::factory()->create([
            'is_system_admin' => 'Y',
        ]);

        $scope = AuthorizationScopeResolver::resolve($user);

        // System Admin should have full access
        $this->assertTrue($scope['isSystemAdmin']);
        $this->assertNull($scope['manageableGroupIds']);
        $this->assertNull($scope['usablePageTypes']);
    }

    /**
     * Test Super Admin with single group
     */
    public function test_super_admin_with_single_group()
    {
        // Create a Super Admin user with no Is_System_Admin flag
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Give user a role in group 10
        // First, ensure role exists
        $role = DB::table('N_HRIS_Role_Functional')->where('id_grup_fitur', 10)->where('flag_aktif', 'Y')->first();

        if (!$role) {
            // Create test role
            $roleId = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'id_grup_fitur' => 10,
                'nama_role' => 'Test Role Group 10',
                'flag_aktif' => 'Y',
            ]);
        } else {
            $roleId = $role->id_role_functional;
        }

        // Assign role to user
        DB::table('N_HRIS_Role_User')->insert([
            'id_user' => $user->id_user,
            'id_role_functional' => $roleId,
            'flag_aktif' => 'Y',
        ]);

        $scope = AuthorizationScopeResolver::resolve($user);

        // Super Admin should have limited scope
        $this->assertFalse($scope['isSystemAdmin']);
        $this->assertIsArray($scope['manageableGroupIds']);
        $this->assertContains(10, $scope['manageableGroupIds']);
        $this->assertIsArray($scope['usablePageTypes']);
    }

    /**
     * Test Super Admin with multiple groups
     */
    public function test_super_admin_with_multiple_groups()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Create roles in groups 10 and 12
        foreach ([10, 12] as $groupId) {
            $role = DB::table('N_HRIS_Role_Functional')
                ->where('id_grup_fitur', $groupId)
                ->where('flag_aktif', 'Y')
                ->first();

            if ($role) {
                $roleId = $role->id_role_functional;
            } else {
                $roleId = DB::table('N_HRIS_Role_Functional')->insertGetId([
                    'id_grup_fitur' => $groupId,
                    'nama_role' => "Test Role Group {$groupId}",
                    'flag_aktif' => 'Y',
                ]);
            }

            DB::table('N_HRIS_Role_User')->insert([
                'id_user' => $user->id_user,
                'id_role_functional' => $roleId,
                'flag_aktif' => 'Y',
            ]);
        }

        $scope = AuthorizationScopeResolver::resolve($user);

        $this->assertFalse($scope['isSystemAdmin']);
        $this->assertCount(2, $scope['manageableGroupIds']);
        $this->assertContains(10, $scope['manageableGroupIds']);
        $this->assertContains(12, $scope['manageableGroupIds']);
    }

    /**
     * Test Super Admin with no groups
     */
    public function test_super_admin_with_no_groups()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Don't assign any roles

        $scope = AuthorizationScopeResolver::resolve($user);

        $this->assertFalse($scope['isSystemAdmin']);
        $this->assertEmpty($scope['manageableGroupIds']);
        $this->assertIsArray($scope['usablePageTypes']);
    }

    /**
     * Test inactive roles are not counted
     */
    public function test_inactive_roles_not_counted()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Create inactive role assignment
        $role = DB::table('N_HRIS_Role_Functional')->where('id_grup_fitur', 10)->where('flag_aktif', 'Y')->first();

        if (!$role) {
            $roleId = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'id_grup_fitur' => 10,
                'nama_role' => 'Test Role',
                'flag_aktif' => 'Y',
            ]);
        } else {
            $roleId = $role->id_role_functional;
        }

        // Assign role but mark as inactive
        DB::table('N_HRIS_Role_User')->insert([
            'id_user' => $user->id_user,
            'id_role_functional' => $roleId,
            'flag_aktif' => 'T', // Inactive!
        ]);

        $scope = AuthorizationScopeResolver::resolve($user);

        // Should have no managed groups since role is inactive
        $this->assertEmpty($scope['manageableGroupIds']);
    }

    /**
     * Test canDelegateRole validates ownership
     */
    public function test_can_delegate_role_validates_ownership()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Give user role in group 10
        $role10 = DB::table('N_HRIS_Role_Functional')->where('id_grup_fitur', 10)->where('flag_aktif', 'Y')->first();

        if (!$role10) {
            $role10Id = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'id_grup_fitur' => 10,
                'nama_role' => 'Role in Group 10',
                'flag_aktif' => 'Y',
            ]);
        } else {
            $role10Id = $role10->id_role_functional;
        }

        DB::table('N_HRIS_Role_User')->insert([
            'id_user' => $user->id_user,
            'id_role_functional' => $role10Id,
            'flag_aktif' => 'Y',
        ]);

        // Create role in group 12 (user doesn't own this)
        $role12 = DB::table('N_HRIS_Role_Functional')->where('id_grup_fitur', 12)->where('flag_aktif', 'Y')->first();

        if (!$role12) {
            $role12Id = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'id_grup_fitur' => 12,
                'nama_role' => 'Role in Group 12',
                'flag_aktif' => 'Y',
            ]);
        } else {
            $role12Id = $role12->id_role_functional;
        }

        // User should be able to delegate role in group 10
        $this->assertTrue(AuthorizationScopeResolver::canDelegateRole($user, $role10Id));

        // User should NOT be able to delegate role in group 12
        $this->assertFalse(AuthorizationScopeResolver::canDelegateRole($user, $role12Id));
    }

    /**
     * Test canDelegatePage validates ownership
     */
    public function test_can_delegate_page_validates_ownership()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Give user role in group 10
        $role = DB::table('N_HRIS_Role_Functional')->where('id_grup_fitur', 10)->where('flag_aktif', 'Y')->first();

        if (!$role) {
            $roleId = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'id_grup_fitur' => 10,
                'nama_role' => 'Test Role',
                'flag_aktif' => 'Y',
            ]);
        } else {
            $roleId = $role->id_role_functional;
        }

        DB::table('N_HRIS_Role_User')->insert([
            'id_user' => $user->id_user,
            'id_role_functional' => $roleId,
            'flag_aktif' => 'Y',
        ]);

        // Create pages in groups 10 and 12
        $page10Id = DB::table('N_HRIS_Grup_Fitur_Map')->insertGetId([
            'id_grup_fitur' => 10,
            'jenis_page' => 'TestPage10',
        ]);

        $page12Id = DB::table('N_HRIS_Grup_Fitur_Map')->insertGetId([
            'id_grup_fitur' => 12,
            'jenis_page' => 'TestPage12',
        ]);

        // User should be able to delegate page in group 10
        $this->assertTrue(AuthorizationScopeResolver::canDelegatePage($user, $page10Id));

        // User should NOT be able to delegate page in group 12
        $this->assertFalse(AuthorizationScopeResolver::canDelegatePage($user, $page12Id));
    }

    /**
     * Test system admin can delegate anything
     */
    public function test_system_admin_can_delegate_anything()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'Y',
        ]);

        // Create some roles
        $role = DB::table('N_HRIS_Role_Functional')->where('flag_aktif', 'Y')->first();

        if (!$role) {
            $roleId = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'id_grup_fitur' => 99,
                'nama_role' => 'Test Role',
                'flag_aktif' => 'Y',
            ]);
        } else {
            $roleId = $role->id_role_functional;
        }

        // System Admin can delegate any role
        $this->assertTrue(AuthorizationScopeResolver::canDelegateRole($user, $roleId));
    }

    /**
     * Test cleared cache is resolved fresh
     */
    public function test_cache_is_cleared_correctly()
    {
        $user = User::factory()->create([
            'is_system_admin' => 'T',
        ]);

        // Resolve once (cached)
        $scope1 = AuthorizationScopeResolver::resolve($user);

        // Clear cache
        AuthorizationScopeResolver::clearCache($user);

        // Resolve again (fresh)
        $scope2 = AuthorizationScopeResolver::resolve($user);

        // Should have same result
        $this->assertEquals($scope1, $scope2);
    }
}
