<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fleet module: department / cost centre that requests and is charged for vehicle trips. */
class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'code', 'head_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('name');
    }

    public function label(): string
    {
        return $this->code ? "{$this->name} ({$this->code})" : $this->name;
    }
}
