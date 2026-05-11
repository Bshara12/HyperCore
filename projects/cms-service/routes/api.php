<?php

use App\Domains\Auth\Service\AuthServiceClient;
use App\Http\Controllers\AIAgentController;
use App\Http\Controllers\AiConversationController;
use App\Http\Controllers\CmsAnalyticsController;
use App\Http\Controllers\ContentAccessController;
use App\Http\Controllers\SearchClickController;
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
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchSuggestionController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionFeatureRuleController;
use App\Support\CurrentProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Default Laravel Route
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');


/*
|--------------------------------------------------------------------------
| Project Creation (بدون resolve.project)
|--------------------------------------------------------------------------
*/

// Route::prefix('projects')->group(function () {

//   Route::post('/', [ProjectController::class, 'store']);
// })->middleware('auth.user');

Route::post('/projects', [ProjectController::class, 'store'])->middleware('auth.user');

/*
|--------------------------------------------------------------------------
| Test Routes
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Protected Project APIs
|--------------------------------------------------------------------------
*/

/*
    |--------------------------------------------------------------------------
    | Projects
    |--------------------------------------------------------------------------
    */

Route::middleware('resolve.project')->group(function () {
  // CMS routes لاحقًا
  Route::get('/projects/resolve', [ProjectController::class, 'resolve']);
  Route::post('/projects/{project}', [ProjectController::class, 'update']);
  Route::get('/projects/{project}', [ProjectController::class, 'show']);
  Route::get('/projects', [ProjectController::class, 'index']);
  Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
  // Route::get('/entries/{id}', [EntryDetailController::class, 'show']);
  Route::post('/check-project-access', [ProjectAccessController::class, 'check']);
});


Route::get(
  '/projects/{project}/data-types/{slug}/entries',
  [DataTypeEntriesController::class, 'index']
);



Route::prefix('cms')->middleware(['resolve.project', 'auth.user'])->group(function () {

  /*
    |--------------------------------------------------------------------------
    | Entries
    |--------------------------------------------------------------------------
    */
  Route::get('/projects/{project}/entries', [ProjectEntriesController::class, 'index']);
  Route::get('/entries/{entry:slug}', [EntryDetailController::class, 'show']);
  Route::post('/entries/bulk', [EntryDetailController::class, 'showMany']);

  Route::get('/entries/{entrySlug}/versions', [
    EntryVersionController::class,
    'index'
  ]);

  Route::delete('/entries/{entry:slug}', [
    DataEntryController::class,
    'destroy'
  ]);

  Route::get(
    '/entries/{entry:slug}/with-relations',
    [EntryDetailController::class, 'showwithrelation']
  );

  Route::get(
    '/entries/{entry:slug}/same-type',
    [EntryDetailController::class, 'showwithsametype']
  );
  // Route::get(
  //   '/projects/{project}/data-types/{slug}/entries',
  //   [DataTypeEntriesController::class, 'index']
  // );
  Route::post(
    '/entries/{entry:slug}/publish',
    DataEntryPublishController::class
  );


  /*
    |--------------------------------------------------------------------------
    | Data Entries
    |--------------------------------------------------------------------------
    */

  Route::post(
    '/data-types/{dataType:slug}/entries',
    [DataEntryController::class, 'store']
  );

  // -------------------------
  // Collections
  // -------------------------
  // static
  Route::get('/collections', [DataCollectionController::class, 'index']);
  Route::get('/collections/id/{collectionId}', [DataCollectionController::class, 'showById'])->whereNumber('collectionId');
  Route::post('/collections/{collectionSlug}/insert', [DataCollectionController::class, 'addItems']);
  Route::delete('/collections/{collectionSlug}/items', [DataCollectionController::class, 'removeItems']);
  Route::post('/collections/{collectionSlug}/items/reorder', [DataCollectionController::class, 'reorderItems']);
  Route::get('/collections/{collectionSlug}/entries', [DataCollectionController::class, 'getEntries']);
  Route::patch('/collections/{collectionSlug}/deactivate', [DataCollectionController::class, 'deactivate']);

  // CRUD
  Route::get('/collections/{collectionSlug}', [DataCollectionController::class, 'show']);
  Route::post('/collections', [DataCollectionController::class, 'store']);
  Route::patch('/collections/{collectionSlug}', [DataCollectionController::class, 'update']);
  Route::delete('/collections/{collectionSlug}', [DataCollectionController::class, 'destroy']);
});

// -------------------------
// Data Entries
// -------------------------
Route::put('/projects/{project}/data-types/{dataType}/entries/{entry}', [DataEntryController::class, 'update']);


Route::post('/data-types/{dataType}/entries', [DataEntryController::class, 'store'])->middleware('resolve.project');
// Route::post('/projects/{project}/data-types/{dataType}/entries', [DataEntryController::class, 'store']);

Route::delete('/projects/{project}/data-types/{dataType}/entries/{entry}', [DataEntryController::class, 'destroy']);

Route::post('/entries/{entry}/publish', DataEntryPublishController::class);

Route::middleware('auth:sanctum')->group(function () {});
// Route::post('/data-entries/{id}', [DataEntryController::class, 'update']);
Route::put(
  '/data-types/{dataType:slug}/entries/{entry:slug}',
  [DataEntryController::class, 'update']
);

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


/*
    |--------------------------------------------------------------------------
    | CMS
    |--------------------------------------------------------------------------
    */

Route::prefix('cms')->middleware('resolve.project')->group(function () {

  /*
        |--------------------------------------------------------------------------
        | Data Types
        |--------------------------------------------------------------------------
        */

  Route::get('/data-types/trashed', [
    DataTypeController::class,
    'trashed'
  ]);

  Route::post('/data-types/{id}/restore', [
    DataTypeController::class,
    'restore'
  ]);

  Route::delete('/data-types/{id}/force-delete', [
    DataTypeController::class,
    'forceDelete'
  ]);

  Route::post(
    '/data-types',
    [DataTypeController::class, 'store']
  )->middleware('permission:cms.datatype.create');

  Route::get('/data-types', [
    DataTypeController::class,
    'index'
  ]);

  Route::get('/data-types/{slug}', [
    DataTypeController::class,
    'show'
  ]);

  Route::put(
    '/data-types/{dataType}',
    [DataTypeController::class, 'update']
  )->middleware('permission:cms.datatype.update');

  Route::delete(
    '/data-types/{dataType}',
    [DataTypeController::class, 'destroy']
  )->middleware('permission:cms.datatype.delete');


  /*
        |--------------------------------------------------------------------------
        | Fields
        |--------------------------------------------------------------------------
        */

  Route::get(
    '/data-types/{dataType}/fields/trashed',
    [FieldController::class, 'trashed']
  );

  Route::post('/fields/{id}/restore', [
    FieldController::class,
    'restore'
  ]);

  Route::delete('/fields/{id}/force-delete', [
    FieldController::class,
    'forceDelete'
  ]);

  Route::post(
    '/data-types/{dataType}/fields',
    [FieldController::class, 'store']
  );
  // ->middleware('permission:cms.field.create');

  Route::get(
    '/data-types/{dataType}/fields',
    [FieldController::class, 'index']
  );

  Route::put(
    '/fields/{field}',
    [FieldController::class, 'update']
  )->middleware('permission:cms.field.update');

  Route::delete(
    '/fields/{field}',
    [FieldController::class, 'destroy']
  )->middleware('permission:cms.field.delete');
});


/*
    |--------------------------------------------------------------------------
    | Permission Test
    |--------------------------------------------------------------------------
    */

Route::post(
  '/datatype',
  [DataTypeController::class, 'store']
)->middleware('permission:cms.datatype.create');
// });

// CRUD
Route::post('/data-types/{dataType}/fields', [FieldController::class, 'store']);
Route::get('/data-types/{dataType}/fields', [FieldController::class, 'index']);
Route::put('/fields/{field}', [FieldController::class, 'update']);
Route::delete('/fields/{field}', [FieldController::class, 'destroy']);

// -------------------------
// Collections
// -------------------------
// static
Route::get('/collections/{collectionSlug}', [DataCollectionController::class, 'show']);
Route::get('/collections/id/{collectionId}', [DataCollectionController::class, 'showById'])->whereNumber('collectionId');
Route::post('/collections/{collectionSlug}/insert', [DataCollectionController::class, 'addItems']);
Route::delete('/collections/{collectionSlug}/items', [DataCollectionController::class, 'removeItems']);
Route::post('/collections/{collectionSlug}/items/reorder', [DataCollectionController::class, 'reorderItems']);
Route::get('/collections/{collectionSlug}/entries', [DataCollectionController::class, 'getEntries']);

// CRUD
Route::get('/collections', [DataCollectionController::class, 'index']);
Route::post('/collections', [DataCollectionController::class, 'store']);
Route::patch('/collections/{collectionSlug}', [DataCollectionController::class, 'update']);
Route::delete('/collections/{collectionSlug}', [DataCollectionController::class, 'destroy']);
// });


// -------------------------
// Payments
// -------------------------
Route::middleware(['resolve.project', 'auth.user'])
  ->prefix('payments')
  ->group(function () {
    Route::post('/pay', [PaymentController::class, 'charge']);
    Route::post('/installment', [PaymentController::class, 'payInstallment']);
    Route::post('/refund', [PaymentController::class, 'refund']);
    // ->middleware('permission:payment.refund');
  });

// تعبئة رصيد — أدمن فقط
Route::post('/wallet/topup', [PaymentController::class, 'topUp'])
  ->middleware(['auth.user', 'permission:wallet.topup']);

// -------------------------
// CMS Analytics
// -------------------------
Route::prefix('cms/analytics')->middleware('auth.user')->group(function () {

  // --- Admin ---
  Route::prefix('admin')->group(function () {
    Route::get('/overview', [CmsAnalyticsController::class, 'adminOverview']);
    Route::get('/projects-growth', [CmsAnalyticsController::class, 'projectsGrowth']);
  });

  // --- Project Owner ---
  Route::prefix('projects')->middleware('resolve.project')->group(function () {
    Route::get('/content', [CmsAnalyticsController::class, 'contentSummary']);
    Route::get('/content-growth', [CmsAnalyticsController::class, 'contentGrowth']);
    Route::get('/top-rated', [CmsAnalyticsController::class, 'topRated']);
    Route::get('/ratings', [CmsAnalyticsController::class, 'ratingsReport']);
  });
});


// Rate
Route::post('/ratings', [RatingController::class, 'store'])->middleware(['auth.user', 'resolve.project']);
Route::get('/ratings', [RatingController::class, 'index'])->middleware('auth.user');
Route::get('/ratings/stats', [RatingController::class, 'stats'])->middleware('auth.user');





// search
Route::get('/search', SearchController::class)->middleware('auth.user', 'resolve.project');
Route::post('/search/click', SearchClickController::class)->middleware('auth.user', 'resolve.project');
Route::get('/search/suggestions', SearchSuggestionController::class)
  ->middleware(['resolve.project']);  // لا يحتاج auth إلزامي

Route::get('/search/popular', PopularSearchController::class)
  ->middleware(['resolve.project']);

// ─── Search Admin / Debug APIs ────────────────────────────────────────────
Route::prefix('admin/search')
  ->middleware(['auth.user'])
  ->group(function () {

    Route::post('/debug',         [\App\Http\Controllers\SearchAdminController::class, 'debug']);
    Route::get('/logs',           [\App\Http\Controllers\SearchAdminController::class, 'logs']);
    Route::get('/problems',       [\App\Http\Controllers\SearchAdminController::class, 'problems']);
    Route::post('/ai/re-run',     [\App\Http\Controllers\SearchAdminController::class, 'aiReRun']);
    Route::post('/compare',       [\App\Http\Controllers\SearchAdminController::class, 'compare']);
    Route::get('/config',         [\App\Http\Controllers\SearchAdminController::class, 'getConfig']);
    Route::post('/config',        [\App\Http\Controllers\SearchAdminController::class, 'setConfig']);
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

Route::middleware(['auth.user'])->prefix('ai')->group(function () {
  Route::get('/conversations', [AiConversationController::class, 'index']);
  Route::post('/conversations', [AiConversationController::class, 'store']);
  Route::get('/conversations/{id}', [AiConversationController::class, 'show']);
  Route::delete('/conversations/{id}', [AiConversationController::class, 'destroy']);
});




Route::prefix('subscriptions')->group(function () {

  Route::post('/plans', [PlanController::class, 'store']);
});

Route::post(
  '/subscriptions',
  [SubscriptionController::class, 'store']
)->middleware('auth.user');

Route::post(
  '/subscriptions/{subscription}/renew',
  [SubscriptionController::class, 'renew']
)->middleware('auth.user');

Route::post(
  '/subscriptions/{subscription}/cancel',
  [SubscriptionController::class, 'cancel']
)->middleware('auth.user');


Route::post(
  '/subscription-feature-rules',
  [SubscriptionFeatureRuleController::class, 'store']
);


Route::post(
  '/content-access',
  [ContentAccessController::class, 'store']
);

Route::put(
  '/content-access-metadata/{metadata}',
  [ContentAccessController::class, 'update']
);

Route::delete(
  '/content-access/{metadata}',
  [ContentAccessController::class, 'destroy']
);

Route::patch(
  '/content-access/{metadata}/activate',
  [ContentAccessController::class, 'activate']
);

Route::get(

  '/content-access',

  [ContentAccessController::class, 'index']
);

Route::get(

  '/content-access/{id}',

  [ContentAccessController::class, 'show']
);

// -------------------------
// Data Entries
// -------------------------
// Route::put('/projects/{project}/data-types/{dataType}/entries/{entry}', [DataEntryController::class, 'update']);


// Route::post('/projects/{project}/data-types/{dataType}/entries', [DataEntryController::class, 'store']);

// Route::delete('/projects/{project}/data-types/{dataType}/entries/{entry}', [DataEntryController::class, 'destroy']);

// Route::post('/entries/{entry}/publish', DataEntryPublishController::class);

// Route::middleware('auth:sanctum')->group(function () {});
// Route::post('/data-entries/{id}', [DataEntryController::class, 'update']);


// Route::post(
//   '/data-entries/versions/{version}/restore',
//   [DataEntryController::class, 'restore']
// );
// Route::get(
//   '/entries/{id}/with-relations',
//   [EntryDetailController::class, 'showwithrelation']
// );
// Route::get(
//   '/entries/{id}/same-type',
//   [EntryDetailController::class, 'showwithsametype']
// );
Route::get('/b', function () {
  return "CMS OK";
});

Route::get('/test', function () {
  return gethostname();
});
