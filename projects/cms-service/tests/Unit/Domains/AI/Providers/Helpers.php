<?php

namespace Tests\Unit\Domains\AI\Providers;

function makeGeminiClientMock(string $returnText = 'Gemini Response'): object
{
  $resultMock = mock(\stdClass::class);
  $resultMock->shouldReceive('text')->andReturn($returnText);

  $modelMock = mock(\stdClass::class);
  $modelMock->shouldReceive('generateContent')->andReturn($resultMock);

  $clientMock = mock(\stdClass::class);
  $clientMock->shouldReceive('generativeModel')->andReturn($modelMock);

  return $clientMock;
}
