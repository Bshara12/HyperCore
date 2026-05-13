<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\DTOs\EntryDetailDTO;
use App\Domains\CMS\Read\DTOs\GetEntryDetailDTO;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\CMS\Support\LanguageResolver;
use Illuminate\Support\Facades\Cache;



use App\Domains\Subscription\Services\AuthorizationEngineService;

use App\Domains\Subscription\Services\ContentAuthorizationService;

use App\Domains\Subscription\DTOs\Rule\AuthorizeEventDTO;

use App\Domains\Subscription\DTOs\Authorization\AuthorizeContentDTO;


class GetEntryDetailAction
{
  public function __construct(
    private EntryReadRepository $repository,
    private LanguageResolver $languageResolver,
    private AuthorizationEngineService $authorizationEngine,
    private ContentAuthorizationService $contentAuthorization
  ) {}

  // public function execute(int $entryId, ?string $requestedLang): ?EntryDetailDTO
  public function execute(
    GetEntryDetailDTO $dto
  ): ?EntryDetailDTO {
    // $language = $this->languageResolver->resolve($requestedLang);
    $language = $this->languageResolver
      ->resolve($dto->language);
    $fallback = $this->languageResolver->fallback();

    $data = Cache::remember(
      CacheKeys::entry($dto->entryId, $language),
      CacheKeys::TTL_MEDIUM,
      fn() => $this->repository->findPublishedWithValues(
        $dto->entryId,
        $language,
        $fallback
      )
    );

    if (!$data) {
      return null;
    }

    /*
|--------------------------------------------------------------------------
| Event Authorization
|--------------------------------------------------------------------------
*/

    $this->authorizationEngine
      ->authorize(

        new AuthorizeEventDTO(

          userId: $dto->userId,

          projectId: $data['project_id'],

          eventKey: sprintf(

            '%s.view',

            $data['data_type_slug']
          )
        )
      );

    /*
|--------------------------------------------------------------------------
| Content Authorization
|--------------------------------------------------------------------------
*/

    $this->contentAuthorization
      ->authorize(

        new AuthorizeContentDTO(

          userId: $dto->userId,

          projectId: $data['project_id'],

          contentType: $data['data_type_slug'],

          contentId: $data['id']
        )
      );
    return new EntryDetailDTO(
      id: $data['id'],
      slug: $data['slug'],
      status: $data['status'],
      values: $data['values'],
      seo: $data['seo']
    );
  }
}
