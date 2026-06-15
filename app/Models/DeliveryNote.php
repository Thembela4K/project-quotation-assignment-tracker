<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DeliveryNote extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_ISSUED = 'Issued';

    public const STATUS_DELIVERED = 'Delivered';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'job_card_id',
        'sales_quotation_id',
        'client_id',
        'department_id',
        'created_by',
        'issued_by',
        'delivery_note_number',
        'status',
        'delivery_date',
        'recipient_name',
        'recipient_phone',
        'delivery_address',
        'notes',
        'issued_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'issued_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function salesQuotation(): BelongsTo
    {
        return $this->belongsTo(SalesQuotation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports()) {
            return $query;
        }

        return $query->where('department_id', $user->department_id);
    }
}
