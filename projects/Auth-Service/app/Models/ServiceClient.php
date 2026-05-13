<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceClient extends Model
{
    protected $table = 'service_clients';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
    ];
}
