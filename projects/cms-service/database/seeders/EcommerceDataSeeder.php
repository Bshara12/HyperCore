<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EcommerceDataSeeder extends Seeder
{
  public function run(): void
  {
    DB::transaction(function () {
      $now = now();

      $ownerId = DB::table('users')->where('email', 'ecommerce-owner@test.com')->value('id');
      if (!$ownerId) {
        $ownerId = DB::table('users')->insertGetId([
          'name' => 'Ecommerce Owner',
          'email' => 'ecommerce-owner@test.com',
          'password' => Hash::make('password'),
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $projectId = DB::table('projects')->where('slug', 'ecommerce-demo')->value('id');
      if (!$projectId) {
        $projectId = DB::table('projects')->insertGetId([
          'public_id' => (string) Str::uuid(),
          'slug' => 'ecommerce-demo',
          'name' => 'E-Commerce Demo Project',
          'owner_id' => $ownerId,
          'supported_languages' => json_encode(['en', 'ar']),
          'enabled_modules' => json_encode(['cms', 'ecommerce']),
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      DB::table('project_user')->updateOrInsert([
        'project_id' => $projectId,
        'user_id' => $ownerId,
      ], []);

      $categoryTypeId = DB::table('data_types')->where('project_id', $projectId)->where('slug', 'category')->value('id');
      if (!$categoryTypeId) {
        $categoryTypeId = DB::table('data_types')->insertGetId([
          'project_id' => $projectId,
          'name' => 'Category',
          'slug' => 'category',
          'description' => 'Categories',
          'is_active' => true,
          'settings' => json_encode([]),
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $productTypeId = DB::table('data_types')->where('project_id', $projectId)->where('slug', 'product')->value('id');
      if (!$productTypeId) {
        $productTypeId = DB::table('data_types')->insertGetId([
          'project_id' => $projectId,
          'name' => 'Product',
          'slug' => 'product',
          'description' => 'Products',
          'is_active' => true,
          'settings' => json_encode([]),
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $categoryNameFieldId = DB::table('data_type_fields')->where('data_type_id', $categoryTypeId)->where('name', 'name')->value('id');
      if (!$categoryNameFieldId) {
        $categoryNameFieldId = DB::table('data_type_fields')->insertGetId([
          'data_type_id' => $categoryTypeId,
          'name' => 'name',
          'type' => 'string',
          'required' => true,
          'translatable' => true,
          'validation_rules' => null,
          'settings' => json_encode([]),
          'sort_order' => 1,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $productTitleFieldId = DB::table('data_type_fields')->where('data_type_id', $productTypeId)->where('name', 'title')->value('id');
      if (!$productTitleFieldId) {
        $productTitleFieldId = DB::table('data_type_fields')->insertGetId([
          'data_type_id' => $productTypeId,
          'name' => 'title',
          'type' => 'string',
          'required' => true,
          'translatable' => true,
          'validation_rules' => null,
          'settings' => json_encode([]),
          'sort_order' => 1,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $productPriceFieldId = DB::table('data_type_fields')->where('data_type_id', $productTypeId)->where('name', 'price')->value('id');
      if (!$productPriceFieldId) {
        $productPriceFieldId = DB::table('data_type_fields')->insertGetId([
          'data_type_id' => $productTypeId,
          'name' => 'price',
          'type' => 'number',
          'required' => true,
          'translatable' => false,
          'validation_rules' => null,
          'settings' => json_encode([]),
          'sort_order' => 2,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $productSkuFieldId = DB::table('data_type_fields')->where('data_type_id', $productTypeId)->where('name', 'sku')->value('id');
      if (!$productSkuFieldId) {
        $productSkuFieldId = DB::table('data_type_fields')->insertGetId([
          'data_type_id' => $productTypeId,
          'name' => 'sku',
          'type' => 'string',
          'required' => true,
          'translatable' => false,
          'validation_rules' => null,
          'settings' => json_encode([]),
          'sort_order' => 3,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $productCategoryFieldId = DB::table('data_type_fields')->where('data_type_id', $productTypeId)->where('name', 'category')->value('id');
      if (!$productCategoryFieldId) {
        $productCategoryFieldId = DB::table('data_type_fields')->insertGetId([
          'data_type_id' => $productTypeId,
          'name' => 'category',
          'type' => 'relation',
          'required' => true,
          'translatable' => false,
          'validation_rules' => null,
          'settings' => json_encode(['related_data_type_id' => $categoryTypeId]),
          'sort_order' => 4,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $relationId = DB::table('data_type_relations')
        ->where('data_type_id', $productTypeId)
        ->where('related_data_type_id', $categoryTypeId)
        ->where('relation_name', 'category')
        ->value('id');

      if (!$relationId) {
        $relationId = DB::table('data_type_relations')->insertGetId([
          'data_type_id' => $productTypeId,
          'related_data_type_id' => $categoryTypeId,
          'relation_type' => 'many_to_one',
          'relation_name' => 'category',
          'pivot_table' => null,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $categories = [
        ['slug' => 'electronics', 'name_en' => 'Electronics', 'name_ar' => 'إلكترونيات'],
        ['slug' => 'shoes', 'name_en' => 'Shoes', 'name_ar' => 'أحذية'],
        ['slug' => 'home', 'name_en' => 'Home', 'name_ar' => 'منزل'],
      ];

      $categoryEntryIdsBySlug = [];
      foreach ($categories as $c) {
        $entryId = DB::table('data_entries')
          ->where('project_id', $projectId)
          ->where('data_type_id', $categoryTypeId)
          ->where('slug', $c['slug'])
          ->value('id');

        if (!$entryId) {
          $entryId = DB::table('data_entries')->insertGetId([
            'slug' => $c['slug'],
            'data_type_id' => $categoryTypeId,
            'project_id' => $projectId,
            'status' => 'published',
            'published_at' => $now,
            'created_by' => $ownerId,
            'updated_by' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
          ]);
        }

        $categoryEntryIdsBySlug[$c['slug']] = $entryId;

        DB::table('data_entry_values')->updateOrInsert([
          'data_entry_id' => $entryId,
          'data_type_field_id' => $categoryNameFieldId,
          'language' => 'en',
        ], [
          'value' => $c['name_en'],
          'updated_at' => $now,
          'created_at' => $now,
        ]);

        DB::table('data_entry_values')->updateOrInsert([
          'data_entry_id' => $entryId,
          'data_type_field_id' => $categoryNameFieldId,
          'language' => 'ar',
        ], [
          'value' => $c['name_ar'],
          'updated_at' => $now,
          'created_at' => $now,
        ]);
      }

      $products = [
        ['slug' => 'iphone-15-pro', 'title_en' => 'iPhone 15 Pro', 'title_ar' => 'آيفون 15 برو', 'price' => '1200', 'sku' => 'IP15PRO', 'category_slug' => 'electronics'],
        ['slug' => 'airpods-pro-2', 'title_en' => 'AirPods Pro 2', 'title_ar' => 'ايربودز برو 2', 'price' => '249', 'sku' => 'APP2', 'category_slug' => 'electronics'],
        ['slug' => 'nike-air-max', 'title_en' => 'Nike Air Max', 'title_ar' => 'نايك اير ماكس', 'price' => '160', 'sku' => 'NAM160', 'category_slug' => 'shoes'],
        ['slug' => 'running-sneakers', 'title_en' => 'Running Sneakers', 'title_ar' => 'حذاء ركض', 'price' => '95', 'sku' => 'RUN095', 'category_slug' => 'shoes'],
        ['slug' => 'coffee-maker', 'title_en' => 'Coffee Maker', 'title_ar' => 'آلة قهوة', 'price' => '80', 'sku' => 'COF080', 'category_slug' => 'home'],
        ['slug' => 'vacuum-cleaner', 'title_en' => 'Vacuum Cleaner', 'title_ar' => 'مكنسة كهربائية', 'price' => '210', 'sku' => 'VAC210', 'category_slug' => 'home'],
      ];

      $productEntryIds = [];
      foreach ($products as $p) {
        $entryId = DB::table('data_entries')
          ->where('project_id', $projectId)
          ->where('data_type_id', $productTypeId)
          ->where('slug', $p['slug'])
          ->value('id');

        if (!$entryId) {
          $entryId = DB::table('data_entries')->insertGetId([
            'slug' => $p['slug'],
            'data_type_id' => $productTypeId,
            'project_id' => $projectId,
            'status' => 'published',
            'published_at' => $now,
            'created_by' => $ownerId,
            'updated_by' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
          ]);
        }

        $productEntryIds[] = $entryId;

        DB::table('data_entry_values')->updateOrInsert([
          'data_entry_id' => $entryId,
          'data_type_field_id' => $productTitleFieldId,
          'language' => 'en',
        ], [
          'value' => $p['title_en'],
          'updated_at' => $now,
          'created_at' => $now,
        ]);

        DB::table('data_entry_values')->updateOrInsert([
          'data_entry_id' => $entryId,
          'data_type_field_id' => $productTitleFieldId,
          'language' => 'ar',
        ], [
          'value' => $p['title_ar'],
          'updated_at' => $now,
          'created_at' => $now,
        ]);

        DB::table('data_entry_values')->updateOrInsert([
          'data_entry_id' => $entryId,
          'data_type_field_id' => $productPriceFieldId,
          'language' => null,
        ], [
          'value' => (string) $p['price'],
          'updated_at' => $now,
          'created_at' => $now,
        ]);

        DB::table('data_entry_values')->updateOrInsert([
          'data_entry_id' => $entryId,
          'data_type_field_id' => $productSkuFieldId,
          'language' => null,
        ], [
          'value' => $p['sku'],
          'updated_at' => $now,
          'created_at' => $now,
        ]);

        $categoryEntryId = $categoryEntryIdsBySlug[$p['category_slug']] ?? null;
        if ($categoryEntryId) {
          DB::table('data_entry_relations')->updateOrInsert([
            'data_entry_id' => $entryId,
            'related_entry_id' => $categoryEntryId,
            'data_type_relation_id' => $relationId,
          ], [
            'created_at' => $now,
            'updated_at' => $now,
          ]);
        }
      }

      $featuredCollectionId = DB::table('data_collections')->where('project_id', $projectId)->where('slug', 'featured-products')->value('id');
      if (!$featuredCollectionId) {
        $featuredCollectionId = DB::table('data_collections')->insertGetId([
          'project_id' => $projectId,
          'data_type_id' => $productTypeId,
          'name' => 'Featured Products',
          'slug' => 'featured-products',
          'type' => 'manual',
          'conditions' => null,
          'conditions_logic' => 'and',
          'description' => 'Featured products collection',
          'is_active' => true,
          'is_offer' => false,
          'settings' => json_encode([]),
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $topItemIds = array_slice($productEntryIds, 0, 4);
      foreach ($topItemIds as $idx => $entryId) {
        DB::table('data_collection_items')->updateOrInsert([
          'collection_id' => $featuredCollectionId,
          'item_id' => $entryId,
        ], [
          'sort_order' => $idx + 1,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      $saleCollectionId = DB::table('data_collections')->where('project_id', $projectId)->where('slug', 'sale-products')->value('id');
      if (!$saleCollectionId) {
        $saleCollectionId = DB::table('data_collections')->insertGetId([
          'project_id' => $projectId,
          'data_type_id' => $productTypeId,
          'name' => 'Sale Products',
          'slug' => 'sale-products',
          'type' => 'dynamic',
          'conditions' => json_encode([
            ['field' => 'price', 'operator' => '>', 'value' => 100],
          ]),
          'conditions_logic' => 'and',
          'description' => 'Dynamic products where price > 100',
          'is_active' => true,
          'is_offer' => true,
          'settings' => json_encode([]),
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }

      DB::table('data_collection_items')->where('collection_id', $saleCollectionId)->delete();
      $dynamicItems = array_values(array_filter($productEntryIds, function ($entryId) use ($productPriceFieldId) {
        $price = DB::table('data_entry_values')
          ->where('data_entry_id', $entryId)
          ->where('data_type_field_id', $productPriceFieldId)
          ->whereNull('language')
          ->value('value');
        return $price !== null && (float) $price > 100;
      }));

      foreach ($dynamicItems as $idx => $entryId) {
        DB::table('data_collection_items')->insert([
          'collection_id' => $saleCollectionId,
          'item_id' => $entryId,
          'sort_order' => $idx + 1,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }
    });
  }
}
