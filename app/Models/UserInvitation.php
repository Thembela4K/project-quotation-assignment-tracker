<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvitation extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_ACCEPTED = 'Accepted';

    public const STATUS_EMAIL_FAILED = 'Email Failed';

    public const STATUS_EXPIRED = 'Expired';

    protected $fillable = [
        'user_id',
        'invited_by',
        'email',
        'token_hash',
        'status',
        'expires_at',
        'sent_at',
        'accepted_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ! $this->accepted_at
            && $this->expires_at->isFuture();
    }
}
