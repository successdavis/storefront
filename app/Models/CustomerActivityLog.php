<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerActivityLog extends Model
{
    use HasFactory;

    public const TYPE_ACCOUNT = 'account';
    public const TYPE_COMMUNICATION = 'communication';
    public const TYPE_NOTE = 'note';
    public const TYPE_SECURITY = 'security';
    public const TYPE_ORDER = 'order';

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'action',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
