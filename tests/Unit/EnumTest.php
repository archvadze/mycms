<?php
namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserStatus;
use Tests\TestCase;

class EnumTest extends TestCase
{
    public function test_order_status_labels(): void
    {
        $this->assertEquals('Pending', OrderStatus::Pending->label());
        $this->assertEquals('Accepted', OrderStatus::Accepted->label());
        $this->assertEquals('Rejected', OrderStatus::Rejected->label());
    }

    public function test_order_status_colors(): void
    {
        $this->assertEquals('warning', OrderStatus::Pending->color());
        $this->assertEquals('success', OrderStatus::Accepted->color());
        $this->assertEquals('danger', OrderStatus::Rejected->color());
    }

    public function test_project_status_labels(): void
    {
        $this->assertEquals('In Progress', ProjectStatus::InProgress->label());
        $this->assertEquals('Completed', ProjectStatus::Completed->label());
    }

    public function test_user_status_labels(): void
    {
        $this->assertEquals('Active', UserStatus::Active->label());
        $this->assertEquals('Blocked', UserStatus::Blocked->label());
    }
}
