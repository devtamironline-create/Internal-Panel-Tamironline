<?php

namespace Tests\Feature\SeoArm;

/** دسترسی مسیرهای بازوی سئو. */
class SeoArmAccessTest extends ArmTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/seo/arm')->assertRedirect();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->actingAs($this->plainUser())
            ->get('/admin/seo/arm')
            ->assertForbidden();
    }

    public function test_admin_with_permission_sees_dashboard(): void
    {
        $this->seedStageOne();

        $this->actingAs($this->adminUser())
            ->get('/admin/seo/arm')
            ->assertOk()
            ->assertSee('مرکز فرمان سئو');
    }

    public function test_all_arm_screens_render_for_admin(): void
    {
        $this->seedStageOne();
        $admin = $this->adminUser();

        foreach ([
            '/admin/seo/arm/roadmap',
            '/admin/seo/arm/calendar',
            '/admin/seo/arm/calendar?view=list',
            '/admin/seo/arm/calendar?view=kanban',
            '/admin/seo/arm/pages',
            '/admin/seo/arm/opportunities',
            '/admin/seo/arm/issues',
            '/admin/seo/arm/links',
            '/admin/seo/arm/reports',
            '/admin/seo/arm/reports/executive',
            '/admin/seo/arm/settings',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_write_route_requires_manage_permission(): void
    {
        $this->seedStageOne();

        // کاربر فقط-مشاهده: manage-seo دارد ولی seo-arm-manage ندارد.
        $viewer = $this->plainUser();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage-seo', 'guard_name' => 'web']);
        $viewer->givePermissionTo('manage-seo');

        $item = \Modules\Seo\Models\SeoRoadmapItem::query()->first();

        $this->actingAs($viewer)
            ->put('/admin/seo/arm/roadmap/'.$item->id.'/status', ['status' => 'ready'])
            ->assertForbidden();
    }
}
