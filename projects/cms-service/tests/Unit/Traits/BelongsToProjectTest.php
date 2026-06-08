<?php

namespace Tests\Unit\Traits;

use App\Models\Project;
use App\Traits\BelongsToProject;
use Illuminate\Database\Eloquent\Model;

// ─── Models وهمية متوافقة تماماً مع توقيع لارافيل ─────────────────────────

class BelongsToProjectDummyPost extends Model
{
  use BelongsToProject;
  protected $fillable = ['project_id'];

  /**
   * 🔥 تصحيح التوقيع ليتوافق مع الـ Model الأصلي تماماً
   */
  public function fireModelEvent($event, $halt = true)
  {
    return parent::fireModelEvent($event, $halt);
  }
}

class BelongsToProjectDummyProject extends Project
{
  use BelongsToProject;

  /**
   * 🔥 تصحيح التوقيع هنا أيضاً
   */
  public function fireModelEvent($event, $halt = true)
  {
    return parent::fireModelEvent($event, $halt);
  }
}

// ─── كود الاختبار والتغطية الشاملة ───────────────────────────────────────

beforeEach(function () {
  if (app()->bound('currentProject')) {
    app()->offsetUnset('currentProject');
  }
});


// الفحص 1: تغطية شرط (if $model instanceof Project)
test('it ignores the model execution if it is an instance of Project', function () {
  $currentProject = new Project();
  $currentProject->id = 10;
  app()->instance('currentProject', $currentProject);

  $dummyProject = new BelongsToProjectDummyProject();
  $dummyProject->fireModelEvent('creating');

  expect($dummyProject->project_id)->toBeNull();
});


// الفحص 2: تغطية شرط (if ! App::bound("currentProject"))
test('it ignores execution if currentProject is not bound in the container', function () {
  $dummyPost = new BelongsToProjectDummyPost();
  $dummyPost->fireModelEvent('creating');

  expect($dummyPost->project_id)->toBeNull();
});


// الفحص 3: تغطية شرط (if (empty($model->project_id))) في حال كان ممتلئاً بالفعل
test('it does not overwrite the project_id if it is already provided manually', function () {
  $currentProject = new Project();
  $currentProject->id = 55;
  app()->instance('currentProject', $currentProject);

  $dummyPost = new BelongsToProjectDummyPost();
  $dummyPost->project_id = 999;

  $dummyPost->fireModelEvent('creating');

  expect($dummyPost->project_id)->toBe(999);
});


// الفحص 4: المسار السعيد والناجح لحقن القيمة تلقائياً
test('it automatically injects currentProject id when project_id is empty', function () {
  $currentProject = new Project();
  $currentProject->id = 77;
  app()->instance('currentProject', $currentProject);

  $dummyPost = new BelongsToProjectDummyPost();
  expect($dummyPost->project_id)->toBeNull();

  $dummyPost->fireModelEvent('creating');

  expect($dummyPost->project_id)->toBe(77);
});
