<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_verified',
        'otp_code',
        'otp_expires_at',
        'locked_until'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'otp_expires_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    // public function projects() {
    //     return $this->belongsToMany(Project::class)
    //         ->withPivot('role_id')
    //         ->withTimestamps();
    // }

    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_user'
        )->withPivot('role_id');
    }

    public function roles() {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')->withTimestamps();
    }

    public function permessions() {
        return $this->roles()
            ->with('permessions')
            ->get()
            ->pluck('permessions')
            ->flatten()
            ->unique('id');
    }

    public static function is_admin(User $user) {
        $role = DB::table('role_user')
                ->where('role_id', 3)
                ->where('user_id', $user->id)
                ->first();

        if(!empty($role)) {
            return true;
        }
        return false;
    }

    public static function is_super_admin(User $user) {
        $role = DB::table('role_user')
                ->where('role_id', 2)
                ->where('user_id', $user->id)
                ->first();

        if(!empty($role)) {
            return true;
        }
        return false;
    }

    public static function is_owner(User $user) {
        $role = DB::table('role_user')
                ->where('role_id', 1)
                ->where('user_id', $user->id)
                ->first();

        if(!empty($role)) {
            return true;
        }
        return false;
    }

    public static function is_user(User $user) {
        $role = DB::table('role_user')
                ->where('role_id', 4)
                ->where('user_id', $user->id)
                ->first();

        if(!empty($role)) {
            return true;
        }
        return false;
    }

}
