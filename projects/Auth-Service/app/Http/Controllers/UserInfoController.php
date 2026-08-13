<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepositoryInterface;

class UserInfoController extends Controller
{
  protected $users;

  public function __construct(UserRepositoryInterface $userRepository)
  {
    $this->users = $userRepository;
  }

  public function show($id)
  {
    $user = $this->users->findById((int) $id);

    if (! $user) {
      return response()->json(['error' => 'User not found'], 404);
    }

    return response()->json([
      'id' => $user->id,
      'email' => $user->email,
    ]);
  }
}
