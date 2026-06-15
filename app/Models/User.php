<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_DIRECTOR = 'director';

    public const ROLE_RECEPTION = 'reception';

    public const ROLE_DEPARTMENT_USER = 'department_user';

    public const ROLE_BUSINESS_ANALYST = 'business_analyst';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_DIRECTOR => 'Director',
        self::ROLE_RECEPTION => 'Reception',
        self::ROLE_DEPARTMENT_USER => 'Department User',
        self::ROLE_BUSINESS_ANALYST => 'Business Analyst',
    ];

    protected $fillable = [
        'staff_member_id',
        'department_id',
        'invited_by',
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'receives_submissions',
        'can_access_sppra',
        'invited_at',
        'invitation_accepted_at',
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
            'is_active' => 'boolean',
            'receives_submissions' => 'boolean',
            'can_access_sppra' => 'boolean',
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function crmNotifications(): HasMany
    {
        return $this->hasMany(CrmNotification::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canManage(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION);
    }

    public function canManageFinance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION);
    }

    public function canDraftFinance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION, self::ROLE_DEPARTMENT_USER);
    }

    public function canApproveFinance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canViewReports(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION, self::ROLE_BUSINESS_ANALYST);
    }

    public function canViewRequisitions(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION, self::ROLE_BUSINESS_ANALYST);
    }

    public function canApproveRequisitions(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canReleaseRequisitionFunds(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canFulfillRequisitions(): bool
    {
        return $this->canReleaseRequisitionFunds();
    }

    public function canReviewSubmissions(): bool
    {
        return $this->receives_submissions
            || $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canAccessSppra(): bool
    {
        return $this->can_access_sppra
            || $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canViewPortfolio(): bool
    {
        return $this->canManage() || $this->canReviewSubmissions() || $this->hasRole(self::ROLE_BUSINESS_ANALYST);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function canManageAttendance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION);
    }

    public function displayLogin(): string
    {
        return $this->username ?: (string) $this->email;
    }
}
