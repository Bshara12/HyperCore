<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Actions\Data\PublishDataEntryAction;
use Illuminate\Http\Request;

class DataEntryPublishController extends Controller
{
  public function __invoke(
    string $entry,
    Request $request,
    PublishDataEntryAction $action
  ) {
    $authUser = $request->attributes->get('auth_user');
    $userId = null;
    if (is_array($authUser) && isset($authUser['id'])) {
      $userId = (int) $authUser['id'];
    } elseif (is_object($authUser) && isset($authUser->id)) {
      $userId = (int) $authUser->id;
    }

    $userId = $userId ?? auth()->id();

    return response()->json(
      $action->execute($entry, $userId)
    );
  }
}
