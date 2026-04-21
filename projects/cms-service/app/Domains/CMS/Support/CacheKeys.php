<?php

namespace App\Domains\CMS\Support;

class CacheKeys
{
  const TTL_SHORT  = 300;    // 5 دقائق
  const TTL_MEDIUM = 3600;   // ساعة
  const TTL_LONG   = 86400;  // يوم

  // ============================================
  // 🔑 Projects
  // ============================================
  public static function allProjects(): string
  {
    return 'projects:all';
  }

  public static function project(int $id): string
  {
    return "projects:{$id}";
  }

  // ============================================
  // 🔑 DataTypes
  // ============================================
  public static function dataTypes(int $projectId): string
  {
    return "project:{$projectId}:data_types";
  }

  public static function dataType(int $id): string
  {
    return "data_types:{$id}";
  }

  public static function dataTypeBySlug(string $slug, int $projectId): string
  {
    return "project:{$projectId}:data_types:slug:{$slug}";
  }

  // ============================================
  // 🔑 Fields
  // ============================================
  public static function fields(int $dataTypeId): string
  {
    return "data_type:{$dataTypeId}:fields";
  }

  // ============================================
  // 🔑 Collections
  // ============================================
  public static function collections(int $projectId): string
  {
    return "project:{$projectId}:collections";
  }

  public static function collection(int $projectId, string $slug): string
  {
    return "project:{$projectId}:collections:{$slug}";
  }

  public static function collectionById(int $collectionId): string
  {
    return "collections:{$collectionId}";
  }

  public static function collectionItems(int $collectionId): string
  {
    return "collections:{$collectionId}:items";
  }

  public static function collectionEntries(int $collectionId): string
  {
    return "collections:{$collectionId}:entries";
  }

  // ============================================
  // 🔑 Data Entries
  // ============================================
  public static function entry(int $id, string $lang = 'default'): string
  {
    return "entries:{$id}:lang:{$lang}";
  }

  public static function entryBySlug(string $slug, string $lang = 'default'): string
  {
    return "entries:slug:{$slug}:lang:{$lang}";
  }

  // ============================================
  // 🔑 Ratings
  // ============================================
  public static function ratingStats(string $type, int $id): string
  {
    return "ratings:{$type}:{$id}:stats";
  }
}
