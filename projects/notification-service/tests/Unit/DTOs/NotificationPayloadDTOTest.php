<?php
namespace Tests\Unit\DTOs;

use App\DTOs\NotificationPayloadDTO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationPayloadDTOTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated_with_all_parameters()
    {
        // اختبار إنشاء الكائن مع تمرير جميع الخصائص
        $dto = new NotificationPayloadDTO(
            userId: '123',
            channel: 'email',
            title: 'Welcome Notification',
            body: 'This is a test body message.',
            userEmail: 'user@example.com',
            data: ['action_id' => 456]
        );

        $this->assertEquals('123', $dto->userId);
        $this->assertEquals('email', $dto->channel);
        $this->assertEquals('Welcome Notification', $dto->title);
        $this->assertEquals('This is a test body message.', $dto->body);
        $this->assertEquals('user@example.com', $dto->userEmail);
        $this->assertEquals(['action_id' => 456], $dto->data);
    }

    #[Test]
    public function it_uses_default_values_for_optional_parameters()
    {
        // اختبار إنشاء الكائن مع تمرير الخصائص الإجبارية فقط
        $dto = new NotificationPayloadDTO(
            userId: '789',
            channel: 'in_app',
            title: 'Alert',
            body: 'Something happened.'
        );

        $this->assertEquals('789', $dto->userId);
        $this->assertEquals('in_app', $dto->channel);
        $this->assertEquals('Alert', $dto->title);
        $this->assertEquals('Something happened.', $dto->body);
        $this->assertNull($dto->userEmail);
        $this->assertEquals([], $dto->data);
    }
}