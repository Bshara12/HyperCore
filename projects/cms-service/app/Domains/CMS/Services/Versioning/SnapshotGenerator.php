<?php

namespace App\Domains\CMS\Services\Versioning;

use App\Models\DataEntry;
use App\Models\DataEntryValue;
use Illuminate\Database\Eloquent\Collection;


// class SnapshotGenerator
// {
//   public function generate(DataEntry $entry): array
//   {
//     $entry->loadMissing(['values']);

//     return [
//       'entry' => [
//         'id' => $entry->id,
//         'status' => $entry->status,
//         'scheduled_at' => $entry->scheduled_at,
//         'published_at' => $entry->published_at,
//         'data_type_id' => $entry->data_type_id,
//         'project_id' => $entry->project_id,
//       ],
//       // 'values' => $entry->values->map(function ($value) {
//       //     return [
//       //         'data_type_field_id' => $value->data_type_field_id,
//       //         'language' => $value->language,
//       //         'value' => $value->value,
//       //     ];
//       // })->values()->toArray(),
//       'values' => $entry->values->map(
//         function (\App\Models\DataEntryValue $value): array {
//           return [
//             'data_type_field_id' => $value->data_type_field_id,
//             'language' => $value->language,
//             'value' => $value->value,
//           ];
//         }
//       )->values()->toArray(),
//     ];
//   }
// }


class SnapshotGenerator
{
    public function generate(DataEntry $entry): array
    {
        $entry->loadMissing(['values']);

        /** @var Collection<int, DataEntryValue> $values */
        $values = $entry->values;

        return [
            'entry' => [
                'id' => $entry->id,
                'status' => $entry->status,
                'scheduled_at' => $entry->scheduled_at,
                'published_at' => $entry->published_at,
                'data_type_id' => $entry->data_type_id,
                'project_id' => $entry->project_id,
            ],

            'values' => $values
                ->map(function (DataEntryValue $value): array {
                    return [
                        'data_type_field_id' => $value->data_type_field_id,
                        'language' => $value->language,
                        'value' => $value->value,
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }
}