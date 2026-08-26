<?php

namespace App\Models;

use App\Traits\BelongsToProject as TraitsBelongsToProject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'owner_id', 'slug', 'supported_languages', 'enabled_modules', 'public_id'];

    protected $casts = [
        'supported_languages' => 'array',
        'enabled_modules' => 'array',
    ];

    // public function users()
    // {
    //   return $this->belongsToMany(User::class, 'project_user');
    // }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            /*
             | Derive the slug from the name only when one was not supplied.
             |
             | This used to overwrite unconditionally, which silently discarded
             | any slug the caller had set — including the unique one the
             | factory generates. Tests then ended up with the slug of
             | Str::slug($faker->company()), and company() is not unique, so a
             | repeated name collided with the global unique index on
             | projects.slug and failed the run at random.
             |
             | Nothing in the request path relies on the overwrite:
             | CreateProjectAction assigns $data['slug'] itself, and
             | CreateProjectRequest has no slug rule, so a client cannot supply
             | one.
             */
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function collections()
    {
        return $this->hasMany(DataCollection::class);
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    use TraitsBelongsToProject; // يضمن أي عملية create تحوي project_id
}
