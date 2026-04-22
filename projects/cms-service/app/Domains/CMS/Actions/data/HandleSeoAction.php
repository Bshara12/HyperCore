<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use App\Domains\CMS\Services\SeoGeneratorService;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class HandleSeoAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataEntry.handleSeo';
  }

  public function __construct(
    private SeoEntryRepository $seo,
    private SeoGeneratorService $seoGenerator
  ) {}

  public function execute(int $entryId, ?array $seo, array $values): void
  {
    $this->run(function () use ($entryId, $seo, $values) {
      $this->seo->deleteForEntry($entryId);
      if ($seo) {
        $this->seo->insertForEntry($entryId, $seo);
      } else {
        $generated = $this->seoGenerator->generate($values);
        $this->seo->insertForEntry($entryId, $generated);
      }
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'handle_seo',
        userId: null,
        entityType: 'data',
        entityId: $entryId
      ));
    });
  }
}
