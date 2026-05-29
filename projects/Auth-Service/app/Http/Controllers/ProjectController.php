<?php

namespace App\Http\Controllers;

use App\Services\JwtService;
use Illuminate\Support\Facades\Http;

class ProjectController extends Controller
{
  protected $jwtService;

  public function __construct(JwtService $jwtService)
  {
    $this->jwtService = $jwtService;
  }



  public function exsists_in_project($userId, $projectId): bool
  {
    $response = Http::withHeaders([
      'X-Project-Key' => $projectId,
    ])->post('http://localhost:8001/api/check-project-access', [
      'user_id' => $userId,
    ]);

    $data = $response->json('has_access');

    return $data;
  }
}
