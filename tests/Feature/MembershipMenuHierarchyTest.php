<?php

namespace Tests\Feature;

use App\Models\Menu;
use Database\Seeders\MasterMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipMenuHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_menus_are_not_children_of_finance_accounting(): void
    {
        $this->seed(MasterMenuSeeder::class);

        $membership = Menu::query()->where('slug', 'master-membership')->firstOrFail();
        $finance = Menu::query()->where('slug', 'master-finance-accounting')->firstOrFail();
        $members = Menu::query()->where('slug', 'master-customers')->firstOrFail();
        $dues = Menu::query()->where('slug', 'master-membership-dues')->firstOrFail();

        $this->assertSame('Membership & Dues', $membership->title);
        $this->assertSame($membership->id, $members->parent_id);
        $this->assertSame($membership->id, $dues->parent_id);
        $this->assertNotSame($finance->id, $members->parent_id);
        $this->assertNotSame($finance->id, $dues->parent_id);
    }
}
