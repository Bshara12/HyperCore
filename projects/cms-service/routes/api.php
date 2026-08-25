<?php

use App\Domains\Auth\Service\AuthServiceClient;
use App\Http\Controllers\AiConversationController;
use App\Http\Controllers\CmsAnalyticsController;
use App\Http\Controllers\ContentAccessController;
use App\Http\Controllers\DataCollectionController;
use App\Http\Controllers\DataEntryController;
use App\Http\Controllers\DataEntryPublishController;
use App\Http\Controllers\DataTypeController;
use App\Http\Controllers\DataTypeEntriesController;
use App\Http\Controllers\EntryDetailController;
use App\Http\Controllers\EntryVersionController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PopularSearchController;
use App\Http\Controllers\ProjectAccessController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectEntriesController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SearchAdminController;
use App\Http\Controllers\SearchClickController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchSuggestionController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionFeatureRuleController;
use App\Support\CurrentProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;














Route::prefix('cms')->middleware(['resolve.project', 'auth.user', 'throttle:api.standard'])->group(function () {

  /*
   * |--------------------------------------------------------------------------
   * | Data Types
   * |--------------------------------------------------------------------------
   */
  Route::prefix('data-types')->group(function () {
    Route::get('/trashed', [DataTypeController::class, 'trashed'])->name('cms.data-types.trashed');
    Route::post('/{id}/restore', [DataTypeController::class, 'restore'])->name('cms.data-types.restore')->middleware('throttle:api.heavy');
    Route::delete('/{id}/force-delete', [DataTypeController::class, 'forceDelete'])->name('cms.data-types.force-delete')->middleware('throttle:api.heavy');

    Route::post('/', [DataTypeController::class, 'store'])->name('cms.data-types.store')->middleware(['permission:cms.datatype.create', 'throttle:api.heavy']);
    Route::get('/', [DataTypeController::class, 'index'])->name('cms.data-types.index');
    Route::get('/{slug}', [DataTypeController::class, 'show'])->name('cms.data-types.show');
    Route::put('/{dataType}', [DataTypeController::class, 'update'])->name('cms.data-types.update')->middleware(['permission:cms.datatype.update', 'throttle:api.heavy']);
    Route::delete('/{dataType}', [DataTypeController::class, 'destroy'])->name('cms.data-types.destroy')->middleware(['permission:cms.datatype.delete', 'throttle:api.heavy']);
  });

  /*
   * |--------------------------------------------------------------------------
   * | Fields
   * |--------------------------------------------------------------------------
   */
  Route::prefix('data-types/{dataType}/fields')->group(function () {
    Route::post('/', [FieldController::class, 'store'])->name('cms.fields.store')->middleware(['permission:cms.field.create', 'throttle:api.heavy']);
    Route::get('/', [FieldController::class, 'index'])->name('cms.fields.index');
    Route::get('/trashed', [FieldController::class, 'trashed'])->name('cms.fields.trashed');
  });

  Route::prefix('fields')->middleware('throttle:api.heavy')->group(function () {
    Route::put('/{field}', [FieldController::class, 'update'])->name('cms.fields.update')->middleware('permission:cms.field.update');
    Route::delete('/{field}', [FieldController::class, 'destroy'])->name('cms.fields.destroy')->middleware('permission:cms.field.delete');
    Route::post('/{id}/restore', [FieldController::class, 'restore'])->name('cms.fields.restore');
    Route::delete('/{id}/force-delete', [FieldController::class, 'forceDelete'])->name('cms.fields.force-delete');
  });

  /*
   * |--------------------------------------------------------------------------
   * | DataCollection
   * |--------------------------------------------------------------------------
   */
  Route::prefix('collections')->group(function () {
    Route::get('/', [DataCollectionController::class, 'index'])->name('cms.collections.index');
    Route::get('/id/{collectionId}', [DataCollectionController::class, 'showById'])->whereNumber('collectionId')->name('cms.collections.show-by-id');
    Route::get('/{collectionSlug}', [DataCollectionController::class, 'show'])->name('cms.collections.show');
    Route::get('/{collectionSlug}/entries', [DataCollectionController::class, 'getEntries'])->name('cms.collections.entries');

    Route::middleware('throttle:api.heavy')->group(function () {
      Route::post('/', [DataCollectionController::class, 'store'])->name('cms.collections.store')->middleware('permission:cms.collection.create');
      Route::patch('/{collectionSlug}', [DataCollectionController::class, 'update'])->name('cms.collections.update')->middleware('permission:cms.collection.update');
      Route::delete('/{collectionSlug}', [DataCollectionController::class, 'destroy'])->name('cms.collections.destroy')->middleware('permission:cms.collection.delete');

      Route::post('/{collectionSlug}/insert', [DataCollectionController::class, 'addItems'])->name('cms.collections.items.insert')->middleware('permission:cms.collection.update');
      Route::delete('/{collectionSlug}/items', [DataCollectionController::class, 'removeItems'])->name('cms.collections.items.remove')->middleware('permission:cms.collection.update');
      Route::post('/{collectionSlug}/items/reorder', [DataCollectionController::class, 'reorderItems'])->name('cms.collections.items.reorder')->middleware('permission:cms.collection.update');
      Route::patch('/{collectionSlug}/deactivate', [DataCollectionController::class, 'deactivate'])->name('cms.collections.deactivate')->middleware('permission:cms.collection.update');
    });
  });
});

/*
   * |--------------------------------------------------------------------------
   * | Payments
   * |--------------------------------------------------------------------------
   */
Route::middleware(['auth.user', 'throttle:api.heavy'])->group(function () {

  Route::prefix('payments')->middleware('resolve.project')->group(function () {
    Route::post('/pay', [PaymentController::class, 'charge'])->name('payments.charge');
    Route::post('/installment', [PaymentController::class, 'payInstallment'])->name('payments.installment');
    Route::post('/refund', [PaymentController::class, 'refund'])->middleware('permission:payment.refund')->name('payments.refund');
  });

  // تعبئة رصيد — أدمن فقط
  Route::post('/wallet/topup', [PaymentController::class, 'topUp'])
    ->middleware('permission:wallet.topup')
    ->name('wallet.topup');
});

/*
   * |--------------------------------------------------------------------------
   * | Analytics
   * |--------------------------------------------------------------------------
   */
Route::prefix('cms/analytics')->middleware(['auth.user', 'throttle:api.standard'])->group(function () {
  Route::get('/admin', [CmsAnalyticsController::class, 'adminOverview'])->name('cms.analytics.admin.overview');
  Route::get('/projectOwner', [CmsAnalyticsController::class, 'projectOverview'])->middleware('resolve.project')->name('cms.analytics.projects.overview');
});

/*
   * |--------------------------------------------------------------------------
   * | AI Conversations
   * |--------------------------------------------------------------------------
   */
Route::prefix('ai')
  ->middleware(['auth.user', 'throttle:api.standard'])
  ->group(function () {
    Route::get('/conversations', [AiConversationController::class, 'index'])->name('ai-conversations.index');
    Route::get('/conversations/{id}', [AiConversationController::class, 'show'])->name('ai-conversations.show');
    Route::post('/conversations', [AiConversationController::class, 'store'])->middleware('throttle:api.ai')->name('ai-conversations.store');
    Route::delete('/conversations/{id}', [AiConversationController::class, 'destroy'])->middleware('throttle:api.heavy')->name('ai-conversations.destroy');
  });


























Route::get('/user', function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');


/*
* |--------------------------------------------------------------------------
* | Projects
* |--------------------------------------------------------------------------
*/

Route::post('/projects', [ProjectController::class, 'store'])
  ->middleware(['auth.user', 'throttle:api.heavy']);

Route::get('/projects', [ProjectController::class, 'index'])
  ->middleware(['auth.user', 'throttle:api.standard']);

Route::middleware(['resolve.project', 'auth.user', 'throttle:api.standard'])->group(function () {
  Route::get('/projects/resolve', [ProjectController::class, 'resolve']);
  Route::post('/projects/{project}', [ProjectController::class, 'update'])
    ->middleware('throttle:api.heavy');
  Route::get('/projects/{project}', [ProjectController::class, 'show']);
  Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
    ->middleware('throttle:api.heavy');
  Route::post('/check-project-access', [ProjectAccessController::class, 'check']);
});

Route::get(
  '/projects/{project}/data-types/{slug}/entries',
  [DataTypeEntriesController::class, 'index']
)->middleware(['auth.user', 'resolve.project', 'throttle:api.standard']);


/*
* |--------------------------------------------------------------------------
* | Entries
* |--------------------------------------------------------------------------
*/

  Route::prefix('cms')->middleware(['resolve.project', 'auth.user','throttle:api.standard'])->group(function () {

  Route::get('/projects/{project}/entries', [ProjectEntriesController::class, 'index']);
  Route::get('/entries/{entry:slug}', [EntryDetailController::class, 'show']);
  Route::post('/entries/bulk', [EntryDetailController::class, 'showMany']);

  Route::get('/entries/{entrySlug}/versions', [
    EntryVersionController::class,
    'index',
  ]);

  Route::delete('/entries/{entry:slug}', [
    DataEntryController::class,
    'destroy',
  ]);

  Route::get(
    '/entries/{entry:slug}/with-relations',
    [EntryDetailController::class, 'showwithrelation']
  );

  Route::get(
    '/entries/{entry:slug}/same-type',
    [EntryDetailController::class, 'showwithsametype']
  );
  Route::post(
    '/entries/{entry:slug}/publish',
    DataEntryPublishController::class
  );

  Route::post(
    '/data-types/{dataType:slug}/entries',
    [DataEntryController::class, 'store']
  )->middleware(['auth.user', 'resolve.project']);


  Route::delete('/entries/{entry}', [DataEntryController::class, 'destroy']);
  Route::post('/entries/{entry}/publish', DataEntryPublishController::class);

  Route::patch(
    '/data-entries/{entry:slug}',
    [DataEntryController::class, 'update']
  )->middleware('resolve.project');

  Route::post('/stock/decrement', [StockController::class, 'decrement']);

  Route::delete(
    '/data-types/{dataType:slug}/entries/{entry:slug}',
    [DataEntryController::class, 'destroyByType']
  );

  Route::post(
    '/data-entries/versions/{version}/restore',
    [DataEntryController::class, 'restore']
  );
});



// Rate
Route::post('/ratings', [RatingController::class, 'store'])
  ->middleware(['auth.user', 'resolve.project', 'throttle:api.heavy']);
Route::get('/ratings', [RatingController::class, 'index'])
  ->middleware(['auth.user', 'throttle:api.standard']);
Route::get('/ratings/stats', [RatingController::class, 'stats'])
  ->middleware(['auth.user', 'throttle:api.standard']);

// search
Route::get('/search', SearchController::class)
  ->middleware(['auth.user', 'resolve.project', 'throttle:api.standard']);
Route::post('/search/click', SearchClickController::class)
  ->middleware(['auth.user', 'resolve.project', 'throttle:api.standard']);
Route::get('/search/suggestions', SearchSuggestionController::class)
  ->middleware(['resolve.project', 'throttle:api.standard']);  // لا يحتاج auth إلزامي

Route::get('/search/popular', PopularSearchController::class)
  ->middleware(['resolve.project', 'throttle:api.standard']);

// ─── Search Admin / Debug APIs ────────────────────────────────────────────
Route::prefix('admin/search')
  ->middleware(['auth.user', 'throttle:api.heavy'])
  ->group(function () {
    // TODO(admin): هاد كلو admin/debug endpoints، لازم permission middleware
    // مثلاً ->middleware('permission:search.admin') قبل ما يوصلها أي مستخدم auth عادي
    Route::post('/debug', [SearchAdminController::class, 'debug']);
    Route::get('/logs', [SearchAdminController::class, 'logs']);
    Route::get('/problems', [SearchAdminController::class, 'problems']);
    Route::post('/ai/re-run', [SearchAdminController::class, 'aiReRun'])
      ->middleware('throttle:api.ai');
    Route::post('/compare', [SearchAdminController::class, 'compare']);
    Route::get('/config', [SearchAdminController::class, 'getConfig']);
    Route::post('/config', [SearchAdminController::class, 'setConfig']);
  });



// Route::middleware(['auth.user'])->prefix('ai')->group(function () {
//   Route::get('/conversations', [AiConversationController::class, 'index'])
//     ->name('ai-conversations.index');

//   Route::post('/conversations', [AiConversationController::class, 'store'])
//     ->name('ai-conversations.store');

//   Route::get('/conversations/{id}', [AiConversationController::class, 'show'])
//     ->name('ai-conversations.show');

//   Route::delete('/conversations/{id}', [AiConversationController::class, 'destroy'])
//     ->name('ai-conversations.destroy');
// });



Route::prefix('subscriptions')->group(function () {
  Route::post('/plans', [PlanController::class, 'store']);

  Route::get('/plans', [PlanController::class, 'index'])
  ->middleware(['auth.user', 'throttle:api.standard']);

Route::get('/plans/{id}', [PlanController::class, 'show'])
  ->middleware(['auth.user', 'throttle:api.standard']);
});

Route::post(
  '/subscriptions',
  [SubscriptionController::class, 'store']
)->middleware('auth.user');


Route::post('/subscriptions', [SubscriptionController::class, 'store'])
  ->middleware(['auth.user', 'throttle:api.heavy']);
Route::post('/subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])
  ->middleware(['auth.user', 'throttle:api.heavy']);
Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
  ->middleware(['auth.user', 'throttle:api.heavy']);

Route::get(
  '/subscriptions/{subscription}',
  [SubscriptionController::class, 'show']
)->middleware(['auth.user', 'throttle:api.standard']);

Route::post('/subscription-feature-rules', [SubscriptionFeatureRuleController::class, 'store'])
  ->middleware(['throttle:api.heavy']);

Route::post('/content-access', [ContentAccessController::class, 'store'])
  ->middleware(['auth.user', 'throttle:api.heavy']);
Route::put('/content-access-metadata/{metadata}', [ContentAccessController::class, 'update'])
  ->middleware(['auth.user', 'throttle:api.heavy']);
Route::delete('/content-access/{metadata}', [ContentAccessController::class, 'destroy'])
  ->middleware(['auth.user', 'throttle:api.heavy']);
Route::patch('/content-access/{metadata}/activate', [ContentAccessController::class, 'activate'])
  ->middleware(['auth.user', 'throttle:api.heavy']);
Route::get('/content-access', [ContentAccessController::class, 'index'])
  ->middleware(['auth.user', 'throttle:api.standard']);
Route::get('/content-access/{id}', [ContentAccessController::class, 'show'])
  ->middleware(['auth.user', 'throttle:api.standard']);

/*
|--------------------------------------------------------------------------
| Project Membership (بدون auth.user لأن المستخدم قد لا يملك توكناً بعد)
| مربوط بـ slug مباشرة عبر Route Model Binding — لا يحتاج أي Header
|--------------------------------------------------------------------------
*/
Route::post('/projects/{project}/join', [ProjectController::class, 'join'])
    ->middleware('throttle:10,1');
/*
 | عرض أعضاء المشروع — يتطلب توكناً صالحاً (auth.user) + تحديد المشروع
 | يُنصَح بإضافة middleware صلاحية مثل permission:project.viewMembers
 | إذا أردت حصر الرؤية على الـ admin/owner فقط دون بقية الأعضاء
 */
Route::get('/projects/{project}/members', [ProjectController::class, 'members'])
    ->middleware(['resolve.project', 'auth.user']);

Route::post('/projects/{project}/leave', [ProjectController::class, 'leave'])
    ->middleware(['auth.user']);


Route::get('/b', function () {
  return 'CMS OK';
});

Route::get('/ping', function () {
  return response()->json([
    'ok' => true,
    'time' => now()
  ]);
});

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'time' => now()
    ]);
});

Route::get('/test', function () {
  return gethostname();
});


Route::middleware('resolve.project')->get('/tenant-test', function () {
  return response()->json([
    'project_id' => app('currentProject')->id,
    'project_name' => app('currentProject')->name,
  ]);
});

Route::get('/test-auth', function (AuthServiceClient $auth) {
  $token = request()->bearerToken();

  $user = $auth->getUserFromToken($token);

  return response()->json($user);
});

// routes/api.php - مؤقت للـ debugging فقط
Route::get('/debug/search-user', function (Request $request) {
  $user = $request->attributes->get('auth_user');
  $projectId = CurrentProject::id();

  return response()->json([
    'user_raw' => $user,
    'user_id' => $user['id'] ?? $user['data']['id'] ?? null,
    'user_structure' => is_array($user) ? array_keys($user) : gettype($user),
    'project_id' => $projectId,
    'token' => substr($request->bearerToken() ?? '', 0, 15) . '...',
  ]);
})->middleware(['auth.user', 'resolve.project']);