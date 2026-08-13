<?php
namespace Tests\Unit\Enums;

use App\Enums\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationChannelTest extends TestCase
{
    #[Test]
    public function it_has_correct_enum_values()
    {
        // التحقق من القيم النصية المرتبطة بكل حالة
        $this->assertEquals('email', NotificationChannel::EMAIL->value);
        $this->assertEquals('in_app', NotificationChannel::IN_APP->value);
        $this->assertEquals('real_time', NotificationChannel::REAL_TIME->value);
    }

    #[Test]
    public function it_returns_correct_labels()
    {
        // التحقق من دالة الـ label لكل حالة
        $this->assertEquals('Email Notification', NotificationChannel::EMAIL->label());
        $this->assertEquals('In-App Notification', NotificationChannel::IN_APP->label());
        $this->assertEquals('Real-Time Notification', NotificationChannel::REAL_TIME->label());
    }

    #[Test]
    public function it_returns_all_values_as_an_array()
    {
        // التحقق من أن الدالة الساكنة ترجع جميع القيم بشكل مصفوفة صحيحة (مفيدة للـ Validation)
        $expected = ['email', 'in_app', 'real_time'];
        
        $this->assertEquals($expected, NotificationChannel::values());
    }
}