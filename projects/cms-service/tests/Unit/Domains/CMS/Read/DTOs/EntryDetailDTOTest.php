<?php

use App\Domains\CMS\Read\DTOs\EntryDetailDTO;

test('it initializes properties correctly with provided values', function () {
  $values = ['title' => 'Hello World', 'content' => 'Sample content'];
  $seo = ['meta_title' => 'SEO Title', 'description' => 'SEO Description'];

  $dto = new EntryDetailDTO(
    id: 10,
    slug: 'hello-world',
    status: 'published',
    values: $values,
    seo: $seo
  );

  expect($dto->id)->toBe(10)
    ->and($dto->slug)->toBe('hello-world')
    ->and($dto->status)->toBe('published')
    ->and($dto->values)->toBe($values)
    ->and($dto->seo)->toBe($seo);
});
