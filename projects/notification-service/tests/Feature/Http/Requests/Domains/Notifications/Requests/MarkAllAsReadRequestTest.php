<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\MarkAllAsReadRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class MarkAllAsReadRequestTest extends TestCase
{
  #[Test]
  public function it_has_no_validation_rules()
  {
    $request = new MarkAllAsReadRequest();

    // التحقق من أن الـ rules فارغة تماماً
    $this->assertIsArray($request->rules());
    $this->assertEmpty($request->rules());
  }

  #[Test]
  public function it_passes_validation_always_with_empty_data()
  {
    $request = new MarkAllAsReadRequest();

    // تشغيل الـ Validator للتأكد من أن السلوك العام ناجح دائماً ولا يعطل الـ Request
    $validator = Validator::make([], $request->rules());

    $this->assertFalse($validator->fails());
  }
}
