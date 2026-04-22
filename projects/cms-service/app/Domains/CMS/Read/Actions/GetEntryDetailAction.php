<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\DTOs\EntryDetailDTO;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\CMS\Support\LanguageResolver;
use Illuminate\Support\Facades\Cache;

class GetEntryDetailAction
{
  public function __construct(
    private EntryReadRepository $repository,
    private LanguageResolver $languageResolver
  ) {}

  public function execute(int $entryId, ?string $requestedLang): ?EntryDetailDTO
  {
    $language = $this->languageResolver->resolve($requestedLang);
    $fallback = $this->languageResolver->fallback();

    $data = Cache::remember(
      CacheKeys::entry($entryId, $language),
      CacheKeys::TTL_MEDIUM,
      fn() => $this->repository->findPublishedWithValues(
        $entryId,
        $language,
        $fallback
      )
    );

    if (!$data) {
      return null;
    }

    return new EntryDetailDTO(
      id: $data['id'],
      status: $data['status'],
      values: $data['values'],
      seo: $data['seo']
    );
  }
}
