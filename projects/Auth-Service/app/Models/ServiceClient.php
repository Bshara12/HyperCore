<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceClient extends Model
{
    protected $table = 'service_clients';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }
}
