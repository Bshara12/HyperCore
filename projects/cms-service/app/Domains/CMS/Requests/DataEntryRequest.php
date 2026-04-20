<?php

namespace App\Domains\CMS\Requests;

use App\Domains\CMS\DTOs\Data\CreateDataEntryDTO;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DataEntryRequest extends FormRequest
{
  protected function prepareForValidation(): void
  {
    if ($this->isMethod('post')) {
      $slug = $this->input('slug');
      $title = $this->input('title');

      if ($title === null || $title === '') {
        $valuesTitleEn = $this->input('values.title.en');
        $valuesTitleAr = $this->input('values.title.ar');
        $valuesTitle = $this->input('values.title');

        $titleCandidate = $valuesTitleEn ?: $valuesTitleAr;

        if (($titleCandidate === null || $titleCandidate === '') && is_array($valuesTitle)) {
          foreach ($valuesTitle as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
              $titleCandidate = $candidate;
              break;
            }
          }
        }

        if (is_string($titleCandidate) && $titleCandidate !== '') {
          $title = $titleCandidate;
          $this->merge([
            'title' => $title,
          ]);
        }
      }

      if (($slug === null || $slug === '') && ($title !== null && $title !== '')) {
        $this->merge([
          'slug' => Str::slug($title),
        ]);
      }
    }
  }

  public function rules(): array
  {

    $rules = [
      'values' => [$this->isMethod('patch') ? 'sometimes' : 'required', 'array'],
      'seo' => ['nullable', 'array'],
      'relations' => ['nullable', 'array'],
      'relations.*.relation_id' => ['required_with:relations', 'integer'],
      'relations.*.related_entry_ids' => ['required_with:relations', 'array'],
      'relations.*.related_entry_ids.*' => ['integer'],
      'files' => ['nullable', 'array'],
      'status' => ['nullable', 'string', 'in:draft,published,scheduled'],
      'scheduled_at' => [
        'required_if:status,scheduled',
        'nullable',
        'date'
      ],
    ];

    if ($this->isMethod('post')) {
      $rules['slug'] = [
        'required_without:title',
        'string',
        Rule::unique('data_entries', 'slug')->where(fn($q) => $q->where('project_id', $this->projectId())),
      ];
      $rules['title'] = ['required_without:slug', 'string'];
    }

    return $rules;
  }


  public function toDto(): CreateDataEntryDTO
  {
    return new CreateDataEntryDto(
      values: $this->input('values'),
      seo: $this->input('seo'),
      relations: $this->input('relations'),
      status: $this->input('status'),
      scheduled_at: $this->input('scheduled_at')
    );
  }

  public function projectId(): int
  {
    return CurrentProject::id();
  }

  // public function dataTypeId(): int
  // {
  //   $dataType = $this->route('dataType');

  //   if ($dataType instanceof DataType) {
  //     return (int) $dataType->id;
  //   }

  //   return (int) $dataType;
  // }
  public function entry(): DataEntry
  {
    return $this->route('entry');
  }

  public function dataTypeId(): int
  {
    return (int) $this->entry()->data_type_id;
  }
  public function filesInput(): array
  {
    return $this->file('files', []);
  }

  public function entryId(): int
  {
    $entryslug = $this->route('entry');

    $entry = DataEntry::query()->where('slug',$entryslug)->first();

    if ($entry instanceof DataEntry) {
      return (int) $entry->id;
    }

    return (int) $entry;
  }
}
