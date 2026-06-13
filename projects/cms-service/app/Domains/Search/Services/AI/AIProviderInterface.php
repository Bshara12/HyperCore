<?php

namespace App\Domains\Search\Services\AI;

/**
 * AIProviderInterface
 *
 * Contract لكل AI provider يُستخدم في الـ query normalization.
 *
 * كل provider يجب أن يُعيد نفس الـ shape:
 * {
 *   normalized_query: string   ← الـ query المُطبَّعة للبحث
 *   confidence:       float    ← 0.0 → 1.0
 *   reasoning:        string   ← للـ debugging فقط
 * }
 *
 * أو يُلقي exception إذا فشل → النظام ينتقل للـ provider التالي.
 */
interface AIProviderInterface
{
    /**
     * @param  string  $query     الـ query الأصلية من المستخدم
     * @param  string  $language  'en' | 'ar' | 'mixed'
     *
     * @return array{
     *   normalized_query: string,
     *   confidence:       float,
     *   reasoning:        string,
     * }
     *
     * @throws \RuntimeException إذا فشل الـ provider
     */
    public function normalize(string $query, string $language): array;

    /**
     * اسم الـ provider للـ logging
     */
    public function name(): string;
}