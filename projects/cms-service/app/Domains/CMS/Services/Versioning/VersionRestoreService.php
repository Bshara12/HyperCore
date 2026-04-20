<?php

namespace App\Domains\CMS\Services\Versioning;

use App\Models\DataEntry;
use App\Models\DataEntryVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VersionRestoreService
{
    public function __construct(
        protected VersionCreator $versionCreator
    ) {}

    public function restore(int $versionId, ?int $userId = null): void
    {
        DB::transaction(function () use ($versionId, $userId) {

            $version = DataEntryVersion::findOrFail($versionId);
            $snapshot = $version->snapshot;

            if (is_string($snapshot)) {
                $decoded = json_decode($snapshot, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $snapshot = $decoded;
                }
            }

            if (!is_array($snapshot)) {
                throw new \TypeError('Invalid snapshot format: expected array or valid JSON string.');
            }

            $entry = DataEntry::findOrFail($version->data_entry_id);

            // حذف القيم الحالية
            $entry->values()->delete();

            // إعادة إدخال snapshot values (bulk insert)
            $snapshotValues = $snapshot['values'] ?? [];

            if (!is_array($snapshotValues)) {
                throw new \TypeError('Invalid snapshot values format: expected array.');
            }

            $bulk = [];
            $now = now();

            // Format A: array of rows: [ {data_type_field_id, language, value}, ... ]
            $looksLikeRowFormat = !empty($snapshotValues)
                && isset($snapshotValues[0])
                && is_array($snapshotValues[0])
                && array_key_exists('data_type_field_id', $snapshotValues[0]);

            if ($looksLikeRowFormat) {
                $bulk = collect($snapshotValues)->map(function ($value) use ($entry, $now) {
                    return [
                        'data_entry_id' => $entry->id,
                        'data_type_field_id' => $value['data_type_field_id'],
                        'language' => $value['language'],
                        'value' => $value['value'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray();
            } else {
                // Format B (legacy): associative map: ['title_en' => '...', 'price' => 123]
                $dataTypeId = $snapshot['data_type_id'] ?? $entry->data_type_id;

                foreach ($snapshotValues as $key => $val) {
                    if (!is_string($key)) {
                        continue;
                    }

                    $lang = null;
                    $fieldSlug = $key;

                    if (str_contains($key, '_')) {
                        $parts = explode('_', $key);
                        $maybeLang = end($parts);
                        if (in_array($maybeLang, ['en', 'ar'], true)) {
                            $lang = $maybeLang;
                            array_pop($parts);
                            $fieldSlug = implode('_', $parts);
                        }
                    }

                    $fieldId = DB::table('data_type_fields')
                        ->where('data_type_id', $dataTypeId)
                        ->where('name', $fieldSlug)
                        ->value('id');

                    if (!$fieldId) {
                        Log::warning('Version restore: field not found for snapshot key', [
                            'version_id' => $versionId,
                            'data_type_id' => $dataTypeId,
                            'field' => $fieldSlug,
                            'key' => $key,
                        ]);
                        continue;
                    }

                    $bulk[] = [
                        'data_entry_id' => $entry->id,
                        'data_type_field_id' => $fieldId,
                        'language' => $lang,
                        'value' => is_array($val) ? json_encode($val) : (string) $val,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($bulk)) {
                $entry->values()->insert($bulk);
            }

            // تحديث حالة entry
            $entry->update([
                'status' => $snapshot['entry']['status'] ?? $entry->status,
                'scheduled_at' => $snapshot['entry']['scheduled_at'] ?? $entry->scheduled_at,
                'published_at' => $snapshot['entry']['published_at'] ?? $entry->published_at,
            ]);

            // إنشاء version جديدة تمثل عملية restore
            $this->versionCreator->create($entry, $userId);
        });
    }
}
