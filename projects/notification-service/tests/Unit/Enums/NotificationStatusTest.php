<?php
namespace Tests\Unit\Enums;

use App\Enums\NotificationStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationStatusTest extends TestCase
{
    #[Test]
    public function it_has_correct_enum_values()
    {
        // التحقق من القيم النصية المرتبطة بكل حالة
        $this->assertEquals('pending', NotificationStatus::PENDING->value);
        $this->assertEquals('sent', NotificationStatus::SENT->value);
        $this->assertEquals('failed', NotificationStatus::FAILED->value);
    }

    #[Test]
    public function it_returns_all_values_as_an_array()
    {
        // التحقق من أن الدالة الساكنة ترجع جميع القيم بشكل مصفوفة صحيحة
        $expected = ['pending', 'sent', 'failed'];
        
        $this->assertEquals($expected, NotificationStatus::values());
    }
}