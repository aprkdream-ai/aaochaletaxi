<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Expiry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FleetDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = ['type', 'number', 'expires_on', 'file_path', 'original_name', 'mime', 'size'];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function expiry(): array
    {
        return Expiry::of($this->expires_on);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
