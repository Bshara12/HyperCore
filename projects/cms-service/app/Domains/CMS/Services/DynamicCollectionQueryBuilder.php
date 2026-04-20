<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\Services\EntryHierarchy\EntryHierarchyBuilder;
use App\Domains\CMS\Services\ValueConditions\BetweenValueConditionStrategy;
use App\Domains\CMS\Services\ValueConditions\ComparisonValueConditionStrategy;
use App\Domains\CMS\Services\ValueConditions\ContainsValueConditionStrategy;
use App\Domains\CMS\Services\ValueConditions\InCollectionConditionStrategy;
use App\Domains\CMS\Services\ValueConditions\InValueConditionStrategy;
use App\Domains\CMS\Services\ValueConditions\NotContainsValueConditionStrategy;
use App\Domains\CMS\Services\ValueConditions\ValueConditionStrategy;
use App\Models\DataCollection;
use App\Models\DataEntry;

class DynamicCollectionQueryBuilder
{
  protected $projectId;
  protected $dataTypeId;
  protected array $operatorStrategies = [];

  public function __construct(
    protected DataEntryRepositoryInterface $entryRepository,
    protected DataEntryRelationRepository $relationRepository,
    DataEntryValueRepository $valueRepository,
    protected EntryHierarchyBuilder $entryHierarchyBuilder,
    protected FieldRepositoryInterface $fieldRepository
  ) {
    $this->operatorStrategies = [
      '=' => new ComparisonValueConditionStrategy('=', $valueRepository),
      '!=' => new ComparisonValueConditionStrategy('!=', $valueRepository),
      '>' => new ComparisonValueConditionStrategy('>', $valueRepository),
      '<' => new ComparisonValueConditionStrategy('<', $valueRepository),
      '>=' => new ComparisonValueConditionStrategy('>=', $valueRepository),
      '<=' => new ComparisonValueConditionStrategy('<=', $valueRepository),
      'contains' => new ContainsValueConditionStrategy($valueRepository),
      'not_contains' => new NotContainsValueConditionStrategy($valueRepository, $this->entryRepository),
      'in' => new InValueConditionStrategy($valueRepository),
      'incollection' => new InCollectionConditionStrategy($valueRepository),
      'between' => new BetweenValueConditionStrategy($valueRepository),
    ];
  }

  public function build(DataCollection $collection)
  {
    $this->projectId = $collection->project_id;
    $this->dataTypeId = $collection->data_type_id;

    $conditions = $collection->conditions ?? [];
    $logic = strtolower($collection->conditions_logic ?? 'and');

    if (isset($conditions['targeted_item'])) {
      $targetId = $conditions['targeted_item'];

      $entry = $this->entryRepository->find($targetId);

      return $entry ? collect([$entry]) : collect([]);
    }

    $allConditionResults = [];

    // 1) collect entry IDs for each condition
    foreach ($conditions as $condition) {
      $field = $condition['field'];
      $operator = strtolower($condition['operator']);
      $value = $condition['value'];

      $entryIds = $this->applyValueConditionReturnEntryIds($field, $operator, $value);

      // إذا الشرط لم يرجع شيء → النتيجة النهائية صفر
      if (empty($entryIds)) {
        return collect([]);
      }

      $allConditionResults[] = $entryIds;
    }

    if (empty($allConditionResults)) {
      return collect([]);
    }

    if ($logic === 'and') {
      // intersection
      $finalIds = array_shift($allConditionResults);
      foreach ($allConditionResults as $ids) {
        $finalIds = array_intersect($finalIds, $ids);
      }
    } else {
      // union
      $finalIds = [];
      foreach ($allConditionResults as $ids) {
        $finalIds = array_merge($finalIds, $ids);
      }
      $finalIds = array_unique($finalIds);
    }

    return DataEntry::whereIn('id', $finalIds)->get();
  }

  protected function applyValueConditionReturnEntryIds($field, $operator, $value)
  {
    $projectId = $this->projectId;
    $dataTypeId = $this->dataTypeId;

    if (!$operator === 'inCollection') {
      $fieldInfo = $this->fieldRepository->findByDataTypeAndName($dataTypeId, $field);

      if (!$fieldInfo) return [];

      // ============================
      // 1) Field is a relation
      // ============================
      if ($fieldInfo->type === 'relation') {

        $relatedDataTypeId = $fieldInfo->settings['related_data_type_id'] ?? null;
        if (!$relatedDataTypeId) return [];

        $relatedEntryIds = $this->entryRepository->pluckIdsByProjectTypeAndValues(
          $projectId,
          $relatedDataTypeId,
          (array)$value
        );

        if (!$relatedEntryIds) return [];

        $directEntryIds = $this->relationRepository->pluckEntryIdsByRelatedIds($relatedEntryIds);

        if (empty($directEntryIds)) {
          return [];
        }

        return $this->entryHierarchyBuilder->flattenIds($directEntryIds);
      }
    }

    // ============================
    // 2) Field is a normal value (Strategy)
    // ============================

    $strategy = $this->operatorStrategies[$operator] ?? null;

    if (!$strategy instanceof ValueConditionStrategy) {
      return [];
    }

    return $strategy->apply($field, $value, $projectId, $dataTypeId);
  }
}
