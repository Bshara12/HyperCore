<?php

namespace App\Domains\CMS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AIGenerateSchemaRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      // Project Info
      'project_info'                      => 'required|array',
      'project_info.name'                 => 'required|string|max:255',
      'project_info.slug'                 => 'nullable|string|max:255',
      'project_info.languages'            => 'required|array|min:1',
      'project_info.languages.*'          => 'string|in:ar,en,fr,de,es,tr,zh',
      'project_info.modules'              => 'required|array|min:1',
      'project_info.modules.*'            => 'string|in:cms,ecommerce,booking',
      'project_info.description'          => 'nullable|string|max:1000',

      // Custom Data Types
      'custom_data_types'                 => 'nullable|array',
      'custom_data_types.*.name'          => 'required|string|max:255',
      'custom_data_types.*.slug'          => 'nullable|string|max:255',
      'custom_data_types.*.description'   => 'nullable|string',
      'custom_data_types.*.fields'        => 'required|array|min:1',

      // Fields
      'custom_data_types.*.fields.*.name'
      => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
      'custom_data_types.*.fields.*.type'
      => 'required|string|in:text,number,boolean,select,file,json,relation',
      'custom_data_types.*.fields.*.required'
      => 'boolean',
      'custom_data_types.*.fields.*.translatable'
      => 'boolean',
      'custom_data_types.*.fields.*.validation_rules'
      => 'nullable|array',
      'custom_data_types.*.fields.*.settings'
      => 'nullable|array',

      // Relations
      'relations'                         => 'nullable|array',
      'relations.*.source'                => 'required|string',
      'relations.*.target'                => 'required|string',
      'relations.*.type'                  => 'required|string|in:belongs_to,has_many,many_to_many',
      'relations.*.field_name'            => 'required|string',
      'relations.*.required'              => 'boolean',
    ];
  }
}
