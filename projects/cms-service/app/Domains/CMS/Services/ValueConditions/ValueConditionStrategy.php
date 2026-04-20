<?php

namespace App\Domains\CMS\Services\ValueConditions;

interface ValueConditionStrategy
{
  /**
   * @param string $field     اسم الحقل في الـ DataTypeField
   * @param mixed  $value     القيمة القادمة من شرط التجميعة
   * @param int    $projectId معرف المشروع الحالي
   * @param int    $dataTypeId معرف نوع البيانات الحالي
   * @return int[] قائمة بمعرفات الـ DataEntry المطابقة
   */
  public function apply(string $field, $value, int $projectId, int $dataTypeId): array;
}
