<?php

namespace App\Domains\CMS\DTOs\Data;

use App\Domains\CMS\Requests\DataEntryRequest;

class CreateDataEntryDto
{
  public function __construct(
    public array $values,
    public ?array $seo = null,
    public ?array $relations = null,
    public string $status = 'draft',
    public ?string $scheduled_at = null,
  ) {}

  // public static function fromRequest(DataEntryRequest $request): self
  // {
  //   return new self(
  //     values: $request->input('values', []),
  //     seo: $request->input('seo'),
  //     relations: $request->input('relations'),
  //     status: $request->input('status', 'draft'),
  //     scheduled_at: $request->input('scheduled_at'),
  //   );
  // }
  public static function fromRequest(DataEntryRequest $request): self
{
    $values = $request->input('values', []);

    foreach ($values as $key => $value) {
        // إذا القيمة مو array → لفّها بشكل موحد
        if (!is_array($value)) {
            $values[$key] = [
                null => $value
            ];
        }
    }

    return new self(
        values: $values,
        seo: $request->input('seo'),
        relations: $request->input('relations'),
        status: $request->input('status', 'draft'),
        scheduled_at: $request->input('scheduled_at'),
    );
}
}
