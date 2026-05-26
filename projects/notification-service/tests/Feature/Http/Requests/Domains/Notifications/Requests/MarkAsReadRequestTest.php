<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\MarkAsReadRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class MarkAsReadRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتشغيل الـ Validator بناءً على قواعد الـ Request
   */
  private function validate(array $data): \Illuminate\Contracts\Validation\Validator
  {
    $request = new MarkAsReadRequest();

    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_all_required_fields()
  {
    $data = [
      'recipient_type' => 'user',
      'recipient_id' => 'user-123',
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails(), 'Validation should have passed with required fields.');
  }

  #[Test]
  public function it_passes_validation_with_optional_fields()
  {
    $data = [
      'recipient_type' => 'team',
      'recipient_id' => 'team-789',
      'project_id' => 'project-omega', // الحقل الاختياري متوفر
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails());
  }

  #[Test]
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = $this->validate([]);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('recipient_type', $errors);
    $this->assertArrayHasKey('recipient_id', $errors);
  }
}
