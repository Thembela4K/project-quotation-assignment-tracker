<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SalesQuotation extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SUBMITTED = 'Submitted for Approval';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_SENT = 'Sent';

    public const STATUS_ACCEPTED = 'Accepted';

    public const STATUS_DECLINED = 'Declined';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUS_CONVERTED = 'Converted';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
        self::STATUS_EXPIRED,
        self::STATUS_CONVERTED,
    ];

    protected $fillable = [
        'client_id',
        'department_id',
        'created_by',
        'approved_by',
        'quotation_number',
        'title',
        'status',
        'issue_date',
        'valid_until',
        'subtotal',
        'discount_total',
        'vat_total',
        'total',
        'notes',
        'terms',
        'approval_notes',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'sent_at',
        'accepted_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesQuotationItem::class)->orderBy('position');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class)->latest();
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class)->latest('delivery_date');
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
