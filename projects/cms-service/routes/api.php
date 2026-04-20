<?php

use App\Domains\Auth\Service\AuthServiceClient;
use App\Http\Controllers\DataCollectionController;
use App\Http\Controllers\DataEntryController;
use App\Http\Controllers\DataEntryPublishController;
use App\Http\Controllers\DataTypeController;
use App\Http\Controllers\DataTypeEntriesController;
use App\Http\Controllers\EntryDetailController;
use App\Http\Controllers\EntryVersionController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectAccessController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectEntriesController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\StockController;
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
  Route::get(
    '/projects/{project}/data-types/{slug}/entries',
    [DataTypeEntriesController::class, 'index']
  );
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


Route::post('/projects/{project}/data-types/{dataType}/entries', [DataEntryController::class, 'store']);

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
  )->middleware('permission:cms.field.create');

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




// Rate
Route::post('/ratings', [RatingController::class, 'store'])->middleware(['auth.user','resolve.project']);
Route::get('/ratings', [RatingController::class, 'index'])->middleware('auth.user');
Route::get('/ratings/stats', [RatingController::class, 'stats'])->middleware('auth.user');



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