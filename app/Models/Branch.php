<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['name', 'code', 'city', 'address', 'phone', 'is_head_office', 'is_active'];

    protected function casts(): array
    {
        return ['is_head_office' => 'boolean', 'is_active' => 'boolean'];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderByDesc('is_head_office')->orderBy('name');
    }
}
