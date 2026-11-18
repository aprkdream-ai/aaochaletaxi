<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $fillable = ['tenant_id', 'channel', 'provider', 'recipient', 'subject', 'body', 'status', 'provider_message_id', 'error', 'context'];

    public static function record(array $attributes): self
    {
        $attributes['body'] = isset($attributes['body']) ? mb_substr((string) $attributes['body'], 0, 2000) : null;
        $attributes['error'] = isset($attributes['error']) ? mb_substr((string) $attributes['error'], 0, 2000) : null;

        return static::create($attributes);
    }
}
