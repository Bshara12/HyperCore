<?php

namespace Tests\Feature\Domains\CMS\Actions\Stock;

use App\Domains\CMS\Actions\Stock\DecrementStockAction;
use App\Events\SystemLogEvent;
use App\Models\DataEntry;
use App\Models\DataEntryValue;
use App\Models\DataType;
use App\Models\DataTypeField;
use App\Models\Field; 
use App\Models\Project; 
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new DecrementStockAction();
    
    // 1. إنشاء مشروع عبر الـ Factory
    $this->project = Project::factory()->create();
    
    // 2. إنشاء النوع وربطه بالمشروع
    $this->dataType = DataType::factory()->create([
        'project_id' => $this->project->id,
    ]);

    // 3. إنشاء حقل المخزون وربطه بالنوع
    $this->countField = DataTypeField::factory()->create([
        'data_type_id' => $this->dataType->id,
        'name' => 'count',
        'type' => 'number'
    ]);
});

// 🟢 1. اختبار مسار النجاح بالكامل
test('it decrements stock successfully and dispatches event', function () {
    Event::fake([SystemLogEvent::class]);

    // إنشاء منتج عبر الـ Factory
    $product = DataEntry::factory()->create([
        'data_type_id' => $this->dataType->id,
        'project_id'   => $this->project->id 
    ]);
    
    // إنشاء قيمة المخزون الحالي (تعديل اسم الحقل لـ data_type_field_id) ⭐
    DataEntryValue::factory()->create([
        'data_entry_id'      => $product->id,
        'data_type_field_id' => $this->countField->id, // تعديل هنا
        'value'              => '10'
    ]);

    $items = [
        ['product_id' => $product->id, 'quantity' => 3]
    ];

    $this->action->execute($items);

    // التأكيد على تحديث القيمة في قاعدة البيانات
    $updatedValue = DataEntryValue::where('data_entry_id', $product->id)
        ->where('data_type_field_id', $this->countField->id) // تعديل هنا
        ->first();

    expect((int) $updatedValue->value)->toBe(7);

    // التأكيد على إرسال الحدث
    Event::assertDispatched(SystemLogEvent::class, function ($event) use ($product) {
        return $event->module === 'cms' 
            && $event->eventType === 'update_count'
            && $event->userId === $product->id;
    });
});

// 🔴 2. اختبار فشل العثور على المنتج (ناجح مسبقاً)
test('it throws exception if product does not exist', function () {
    $items = [
        ['product_id' => 999, 'quantity' => 1]
    ];

    expect(fn() => $this->action->execute($items))
        ->toThrow(ModelNotFoundException::class);
});

// 🔴 3. اختبار فشل عدم وجود حقل المخزون للمنتج (ناجح مسبقاً)
test('it throws exception if count field is missing for product', function () {
    $product = DataEntry::factory()->create([
        'data_type_id' => $this->dataType->id,
        'project_id'   => $this->project->id
    ]);

    $items = [
        ['product_id' => $product->id, 'quantity' => 1]
    ];

    expect(fn() => $this->action->execute($items))
        ->toThrow(\Exception::class, "Count field not found for product {$product->id}");
});

// 🔴 4. اختبار فشل نقص المخزون الحالي
test('it throws exception if current stock is insufficient', function () {
    $product = DataEntry::factory()->create([
        'data_type_id' => $this->dataType->id,
        'project_id'   => $this->project->id
    ]);
    
    // تعديل اسم الحقل لـ data_type_field_id ⭐
    DataEntryValue::factory()->create([
        'data_entry_id'      => $product->id,
        'data_type_field_id' => $this->countField->id, // تعديل هنا
        'value'              => '2'
    ]);

    $items = [
        ['product_id' => $product->id, 'quantity' => 5]
    ];

    expect(fn() => $this->action->execute($items))
        ->toThrow(\Exception::class, "Not enough stock for product {$product->id}");
});