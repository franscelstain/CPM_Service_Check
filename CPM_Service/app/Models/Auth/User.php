<?php

namespace App\Models\Auth;

use App\Models\Users\Category as UserCategory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Lumen\Auth\Authorizable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable;

    protected $table = 'u_users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'username',
        'last_ldap_login_at',
        'token',
        'category',
    ];

    // Eager-load relasi biar ikut saat Auth::guard(...)->user()
    protected $with = ['category'];

    public function getIdAttribute()
    {
        return $this->user_id;
    }

    // Tambah kolom "virtual" agar tampil seperti kolom view
    protected $appends = ['id', 'usercategory_name'];

    // Relasi ke kategori, sekaligus filter aktif
    public function category()
    {
        return $this->belongsTo(UserCategory::class, 'usercategory_id', 'usercategory_id')
                    ->where('is_active', 'Yes');
    }

    // Accessor: bikin kolom virtual usercategory_name
    public function getUsercategoryNameAttribute()
    {
        return $this->category ? ($this->category->usercategory_name ?? null) : null;
    }

    // (Opsional) scope aktif seperti di view
    public function scopeActive($q)
    {
        return $q->where('is_active', 'Yes');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
