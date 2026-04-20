<?php

namespace App\Domains\CMS\Read\Repositories\Field;

use App\Models\DataType;
use App\Models\DataTypeField;
use App\Models\Project;

class FieldRepositoryRead
{
  public function list(DataType $dataType)
  {
    $fileds = DataTypeField::where('data_type_id', $dataType->id)->get();
    $project = Project::where('id', $dataType->project_id)->first();
    foreach ($fileds as $field) {
      if ($field->translatable) {
        $field->supported_languages = $project->supported_languages;
      }
    }
    return $fileds;
  }
  public function indexTrashed(DataType $dataType)
  {
    return DataTypeField::onlyTrashed()->where('data_type_id', $dataType->id)->get();
  }
}
