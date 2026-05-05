<?php

namespace App\Domains\AI\Actions;

use App\Domains\AI\DTOs\ProvisionProjectFromSchemaDTO;
use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProvisionProjectFromSchemaAction
{
  // ─── Map بالـ ID الحقيقي من DB ───────────────────────────
  // dataTypeMap['Brand']    = 5   ← بالاسم
  // dataTypeMap['brands']   = 5   ← بالـ slug
  // dataTypeMap['brand']    = 5   ← بالـ slug المفرد
  private array $dataTypeMap = [];

  public function __construct(
    private CreateProjectAction  $createProject,
    private CreateDataTypeAction $createDataType,
    private CreateFieldAction    $createField,
  ) {}

  public function execute(ProvisionProjectFromSchemaDTO $dto): array
  {
    return DB::transaction(function () use ($dto) {

      // ─── Step 1: إنشاء المشروع ─────────────────────────────
      $project = $this->createProject->execute(
        new CreateProjectDTO(
          name: $dto->projectInfo['name'],
          ownerId: $dto->ownerId,
          supportedLanguages: $dto->projectInfo['languages'] ?? ['ar', 'en'],
          enabledModules: $dto->projectInfo['modules']   ?? ['cms'],
          // description: $dto->projectInfo['description'] ?? null,
        )
      );

      Log::info("[AI Provision] Project created: {$project->id} — {$project->name}");

      // ─── Step 2: إنشاء الـ Data Types ──────────────────────
      foreach ($dto->customDataTypes as $typeData) {

        $slug     = $typeData['slug'] ?? Str::slug($typeData['name']);
        $name     = $typeData['name'];

        $dataType = $this->createDataType->execute(
          new CreateDataTypeDTO(
            project_id: $project->id,
            name: $name,
            slug: $slug,
            description: $typeData['description'] ?? null,
            is_active: true,
            settings: [],
          )
        );

        // ✅ نسجّل كل الصيغ الممكنة للاسم في الـ map
        $this->registerDataType($name, $slug, $dataType->id);

        Log::info("[AI Provision] DataType created: {$dataType->id} — {$name}");
      }

      // ─── Step 3 + 4: إنشاء الـ Fields ──────────────────────
      $pendingRelationFields = [];

      foreach ($dto->customDataTypes as $typeData) {

        $dataTypeId = $this->resolveDataTypeId($typeData['name']);
        if (!$dataTypeId) continue;

        $sortOrder = 1;

        foreach ($typeData['fields'] as $fieldData) {

          if ($fieldData['type'] === 'relation') {
            $pendingRelationFields[] = [
              'data_type_id' => $dataTypeId,
              'field'        => $fieldData,
              'sort_order'   => $sortOrder,
            ];
            $sortOrder++;
            continue;
          }

          $this->createRegularField($dataTypeId, $fieldData, $sortOrder);
          $sortOrder++;
        }
      }

      // ─── Step 4: Relation Fields بعد ما كل الـ Types اتنشأت
      foreach ($pendingRelationFields as $pending) {
        $this->processRelationField(
          $pending['data_type_id'],
          $pending['field'],
          $pending['sort_order'],
        );
      }

      // ─── Step 5: Relations من مصفوفة relations ──────────────
      foreach ($dto->relations as $relation) {
        $this->processRelationFromArray($relation);
      }

      Log::info("[AI Provision] All done for project: {$project->id}");

      return $this->buildResponse($project, $dto);
    });
  }

  // =========================================================
  // ✅ الجديد — تسجيل الـ DataType بكل صيغ الاسم الممكنة
  // =========================================================
  private function registerDataType(string $name, string $slug, int $id): void
  {
    // بالاسم الأصلي: "Brand", "Category", "Product"
    $this->dataTypeMap[$name] = $id;

    // بالـ slug: "brands", "categories", "products"
    $this->dataTypeMap[$slug] = $id;

    // بالـ slug المفرد (لو الـ slug جمع): "brand", "category", "product"
    $singular = Str::singular($slug);
    $this->dataTypeMap[$singular] = $id;

    // بالاسم lowercase: "brand", "category"
    $this->dataTypeMap[strtolower($name)] = $id;

    // بالـ snake_case: "room_type", "staff_member"
    $this->dataTypeMap[Str::snake($name)] = $id;
  }

  // =========================================================
  // ✅ الجديد — حل الـ related_data_type_id بأي صيغة
  // =========================================================
  private function resolveDataTypeId(string $identifier): ?int
  {
    // ─── حالة 1: رقم صحيح أُرسل مباشرة ─────────────────
    if (is_numeric($identifier)) {
      return (int) $identifier;
    }

    // ─── حالة 2: ابحث في الـ map بالصيغة الأصلية ────────
    if (isset($this->dataTypeMap[$identifier])) {
      return $this->dataTypeMap[$identifier];
    }

    // ─── حالة 3: جرب بصيغ مختلفة ────────────────────────
    $variants = [
      $identifier,                      // "brands"
      Str::singular($identifier),       // "brand"
      Str::plural($identifier),         // "brands"
      strtolower($identifier),          // "brand"
      Str::studly($identifier),         // "Brand"
      Str::slug($identifier),           // "brand"
      Str::snake($identifier),          // "brand_type"
      Str::camel($identifier),          // "brandType"
    ];

    foreach ($variants as $variant) {
      if (isset($this->dataTypeMap[$variant])) {
        Log::info("[AI Provision] Resolved '{$identifier}' → '{$variant}' = {$this->dataTypeMap[$variant]}");
        return $this->dataTypeMap[$variant];
      }
    }

    Log::warning("[AI Provision] Could not resolve DataType: '{$identifier}'");
    Log::warning("[AI Provision] Available keys: " . implode(', ', array_keys($this->dataTypeMap)));

    return null;
  }

  // =========================================================
  // معالجة حقل الـ Relation
  // =========================================================
  private function processRelationField(
    int   $dataTypeId,
    array $fieldData,
    int   $sortOrder,
  ): void {
    $settings = $fieldData['settings'] ?? [];

    // ✅ حل الـ related_data_type_id بأي صيغة
    $rawRelatedId = $settings['related_data_type_id'] ?? null;

    if (!$rawRelatedId) {
      Log::warning("[AI Provision] Relation field '{$fieldData['name']}' missing related_data_type_id.");
      return;
    }

    // حل الـ ID الفعلي
    $resolvedId = $this->resolveDataTypeId((string) $rawRelatedId);

    if (!$resolvedId) {
      Log::warning("[AI Provision] Skipping relation field '{$fieldData['name']}' — could not resolve '{$rawRelatedId}'.");
      return;
    }

    // ✅ استبدال الـ string بالـ int الحقيقي
    $fieldData['settings']['related_data_type_id'] = $resolvedId;

    $this->createRegularField($dataTypeId, $fieldData, $sortOrder);
  }

  // =========================================================
  // معالجة الـ Relations من مصفوفة relations
  // =========================================================
  private function processRelationFromArray(array $relation): void
  {
    $sourceId = $this->resolveDataTypeId($relation['source']);
    $targetId = $this->resolveDataTypeId($relation['target']);

    if (!$sourceId || !$targetId) {
      Log::warning("[AI Provision] Skipping relation '{$relation['source']}' → '{$relation['target']}'.");
      return;
    }

    $fieldName = $relation['field_name'] ?? Str::snake($relation['target']) . '_id';

    // تحقق إذا الحقل موجود مسبقاً (أنشئ في Step 4)
    $fieldExists = \App\Models\DataTypeField::where('data_type_id', $sourceId)
      ->where('name', $fieldName)
      ->exists();

    if ($fieldExists) {
      Log::info("[AI Provision] Relation field '{$fieldName}' already exists — skipping.");
      return;
    }

    $this->createRegularField(
      dataTypeId: $sourceId,
      fieldData: [
        'name'             => $fieldName,
        'type'             => 'relation',
        'required'         => $relation['required'] ?? false,
        'translatable'     => false,
        'validation_rules' => [],
        'settings'         => [
          'relation_type'        => $relation['type'],
          'related_data_type_id' => $targetId,
          'multiple'             => in_array($relation['type'], ['has_many', 'many_to_many']),
        ],
      ],
      sortOrder: 99,
    );
  }

  // =========================================================
  // إنشاء حقل عادي
  // =========================================================
  private function createRegularField(
    int   $dataTypeId,
    array $fieldData,
    int   $sortOrder,
  ): void {
    try {
      // ✅ نظّف الـ validation rules من القيم غير المدعومة
      $fieldData['validation_rules'] = $this->sanitizeValidationRules(
        $fieldData['validation_rules'] ?? [],
        $fieldData['type']
      );

      $this->createField->execute(
        new CreateFieldDTO(
          data_type_id: $dataTypeId,
          name: $fieldData['name'],
          type: $fieldData['type'],
          required: $fieldData['required']         ?? false,
          translatable: $fieldData['translatable']     ?? false,
          validation_rules: $fieldData['validation_rules'] ?? [],
          settings: $fieldData['settings']         ?? [],
          sort_order: $sortOrder,
        )
      );
    } catch (\Throwable $e) {
      Log::warning("[AI Provision] Field '{$fieldData['name']}' skipped: " . $e->getMessage());
    }
  }

  // ─────────────────────────────────────────────────────────
  // فلترة الـ validation rules حسب نوع الحقل
  // ─────────────────────────────────────────────────────────
  private function sanitizeValidationRules(array $rules, string $type): array
  {
    $allowedPerType = [
      'text'    => [
        'string',
        'max',
        'min',
        'required',
        'nullable',
        'email',
        'url',
        'ip',
        'uuid',
        'alpha',
        'alpha_num',
        'regex',
        'unique',
        'exists',
        'confirmed',
        'different',
        'same',
        'starts_with',
        'ends_with',
        'in'
      ],
      'number'  => ['numeric', 'integer', 'min', 'max', 'required', 'nullable'],
      'boolean' => ['boolean', 'required'],
      'select'  => ['required', 'in'],
      'file'    => ['required', 'nullable', 'mimes', 'max', 'min'],
      'json'    => ['json', 'required', 'nullable'],
      'relation' => ['required', 'exists'],
    ];

    $allowed = $allowedPerType[$type] ?? [];

    return array_filter($rules, function ($rule) use ($allowed) {
      // استخرج اسم الـ rule بدون القيم (مثل max:255 → max)
      $ruleName = explode(':', $rule)[0];
      return in_array($ruleName, $allowed);
    });
  }

  // =========================================================
  // بناء الـ Response
  // =========================================================
  private function buildResponse($project, ProvisionProjectFromSchemaDTO $dto): array
  {
    $dataTypesDetails = [];

    foreach ($this->dataTypeMap as $key => $id) {
      // فقط الأسماء الأصلية — نتجنب التكرار
      if (!in_array($key, array_column($dto->customDataTypes, 'name'))) {
        continue;
      }

      $fields = \App\Models\DataTypeField::where('data_type_id', $id)
        ->orderBy('sort_order')
        ->get(['id', 'name', 'type', 'required', 'translatable'])
        ->toArray();

      $dataTypesDetails[] = [
        'id'     => $id,
        'name'   => $key,
        'fields' => $fields,
      ];
    }

    return [
      'project' => [
        'id'                  => $project->id,
        'name'                => $project->name,
        'slug'                => $project->slug,
        'public_id'           => $project->public_id,
        'supported_languages' => $project->supported_languages,
        'enabled_modules'     => $project->enabled_modules,
      ],
      'data_types'   => $dataTypesDetails,
      'total_types'  => count($dataTypesDetails),
      'total_fields' => array_sum(array_map(fn($dt) => count($dt['fields']), $dataTypesDetails)),
      'modules'      => $dto->projectInfo['modules'] ?? ['cms'],
    ];
  }
}
