<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Plan constants
     */
    public const PLAN_BASIC = 'basic';

    public const PLAN_STANDARD = 'standard';

    public const PLAN_CUSTOM = 'custom';

    /**
     * Plan pricing constants (monthly in EUR)
     */
    public const PLAN_PRICES = [
        self::PLAN_BASIC => 49,
        self::PLAN_STANDARD => 149,
        self::PLAN_CUSTOM => null, // Contact sales
    ];

    /**
     * All available plans
     */
    public const ALL_PLANS = [
        self::PLAN_BASIC,
        self::PLAN_STANDARD,
        self::PLAN_CUSTOM,
    ];

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        'uid_number',
        'vat_registered',
        'vat_number',
        'bank_name',
        'iban',
        'account_holder',
        'invoice_email',
        'invoice_phone',
        'plan',
        'max_users',
        'max_customers',
        'max_vehicles',
        'max_work_orders',
        'is_active',
        'is_trial',
        'trial_ends_at',
        'subscription_ends_at',
        'features',
        'settings',
        'integrations',
        'logo_url',
        'primary_color',
        'secondary_color',
        'database_name',
        'timezone',
        'locale',
        'currency',
        'two_factor_required',
        'ip_whitelist',
        'audit_logs_enabled',
        'api_key',
        'api_rate_limit',
        'last_activity_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_trial' => 'boolean',
        'trial_ends_at' => 'date',
        'subscription_ends_at' => 'date',
        'features' => 'array',
        'settings' => 'array',
        'integrations' => 'array',
        'vat_registered' => 'boolean',
        'two_factor_required' => 'boolean',
        'ip_whitelist' => 'array',
        'audit_logs_enabled' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // Auto-generate slug if not provided
            if (!$tenant->slug) {
                $tenant->slug = Str::slug($tenant->name);
            }

            // Auto-generate subdomain if not provided
            if (!$tenant->subdomain) {
                $tenant->subdomain = $tenant->slug;
            }

            // Generate API key
            if (!$tenant->api_key) {
                $tenant->api_key = 'tk_' . Str::random(32);
            }

            // Set trial end date
            if ($tenant->is_trial && !$tenant->trial_ends_at) {
                $tenant->trial_ends_at = now()->addDays(14); // 14-day trial
            }
        });
    }

    /**
     * Relationships
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function tires(): HasMany
    {
        return $this->hasMany(Tire::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTrial($query)
    {
        return $query->where('is_trial', true);
    }

    public function scopeSubscribed($query)
    {
        return $query->where('is_trial', false)->where('is_active', true);
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('is_trial', true)->where('trial_ends_at', '<', now())
                ->orWhere('is_trial', false)->where('subscription_ends_at', '<', now());
        });
    }

    public function scopeByPlan($query, $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Accessors & Mutators
     */
    public function getFullUrlAttribute(): string
    {
        if ($this->domain) {
            return "https://{$this->domain}";
        }

        return "https://{$this->subdomain}." . config('app.domain', 'yourapp.com');
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->is_trial) {
            return $this->trial_ends_at && $this->trial_ends_at->isPast();
        }

        return $this->subscription_ends_at && $this->subscription_ends_at->isPast();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->is_trial && $this->trial_ends_at) {
            return max(0, $this->trial_ends_at->diffInDays(now(), false));
        }
        if (!$this->is_trial && $this->subscription_ends_at) {
            return max(0, $this->subscription_ends_at->diffInDays(now(), false));
        }

        return null;
    }

    /**
     * Business Logic Methods
     */
    public function canAddUser(): bool
    {
        return $this->users()->count() < $this->max_users;
    }

    public function canAddCustomer(): bool
    {
        return $this->customers()->count() < $this->max_customers;
    }

    public function canAddVehicle(): bool
    {
        return $this->vehicles()->count() < $this->max_vehicles;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function enableFeature(string $feature): void
    {
        $features = $this->features ?? [];
        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $this->update(['features' => $features]);
        }
    }

    public function disableFeature(string $feature): void
    {
        $features = $this->features ?? [];
        $features = array_filter($features, fn($f) => $f !== $feature);
        $this->update(['features' => array_values($features)]);
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function suspend(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function convertToSubscription(string $plan, \Carbon\Carbon $endsAt): void
    {
        $this->update([
            'is_trial' => false,
            'plan' => $plan,
            'subscription_ends_at' => $endsAt,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Plan-based feature limits
     *
     * BASIC: €49/month - Solo mechanics, small workshops
     * STANDARD: €149/month - Growing garages with multiple employees
     * CUSTOM: Contact sales - Large workshops, franchises, multi-location
     */
    public function getPlanLimits(): array
    {
        return match ($this->plan) {
            self::PLAN_BASIC => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50, // per month
                'features' => [
                    'dashboard_basic',
                    'customer_management',
                    'vehicle_checkin',
                    'work_orders_limited',
                    'appointments',
                    'invoicing_basic',
                ],
            ],
            self::PLAN_STANDARD => [
                'max_users' => 5,
                'max_customers' => 1000,
                'max_vehicles' => 3000,
                'max_work_orders' => null, // unlimited
                'features' => [
                    'dashboard_full',
                    'customer_management',
                    'vehicle_checkin',
                    'work_orders_unlimited',
                    'appointments',
                    'invoicing_custom',
                    'tire_hotel',
                    'multi_user',
                    'staff_management',
                    'advanced_reports',
                    'email_support',
                ],
            ],
            self::PLAN_CUSTOM => [
                'max_users' => 999999, // unlimited
                'max_customers' => 999999, // unlimited
                'max_vehicles' => 999999, // unlimited
                'max_work_orders' => null, // unlimited
                'features' => [
                    'dashboard_full',
                    'dashboard_custom',
                    'customer_management',
                    'vehicle_checkin',
                    'work_orders_unlimited',
                    'appointments',
                    'invoicing_whitelabel',
                    'tire_hotel',
                    'multi_user_unlimited',
                    'staff_management',
                    'advanced_reports',
                    'api_access',
                    'dedicated_support',
                    'custom_branding',
                    'custom_integrations',
                    'data_migration',
                    'on_premise_option',
                ],
            ],
            default => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50,
                'features' => ['dashboard_basic', 'customer_management'],
            ],
        };
    }

    /**
     * Check if tenant can create a new work order (for BASIC plan monthly limit)
     */
    public function canCreateWorkOrder(): bool
    {
        // STANDARD and CUSTOM have unlimited work orders
        if (in_array($this->plan, [self::PLAN_STANDARD, self::PLAN_CUSTOM])) {
            return true;
        }

        // BASIC plan has monthly limit
        $maxWorkOrders = $this->max_work_orders ?? 50;
        $currentMonthCount = $this->workOrders()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return $currentMonthCount < $maxWorkOrders;
    }

    /**
     * Get remaining work orders for this month (BASIC plan)
     */
    public function getRemainingWorkOrdersAttribute(): ?int
    {
        if (in_array($this->plan, [self::PLAN_STANDARD, self::PLAN_CUSTOM])) {
            return null; // unlimited
        }

        $maxWorkOrders = $this->max_work_orders ?? 50;
        $currentMonthCount = $this->workOrders()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return max(0, $maxWorkOrders - $currentMonthCount);
    }

    /**
     * Check if tenant has access to Tire Hotel feature
     */
    public function hasTireHotel(): bool
    {
        return in_array($this->plan, [self::PLAN_STANDARD, self::PLAN_CUSTOM]);
    }

    /**
     * Check if tenant has API access
     */
    public function hasApiAccess(): bool
    {
        return $this->plan === self::PLAN_CUSTOM;
    }

    /**
     * Check if tenant is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->is_trial && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if trial has expired
     */
    public function isTrialExpired(): bool
    {
        return $this->is_trial && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Statistics
     */
    public function getStatistics(): array
    {
        return [
            'users_count' => $this->users()->count(),
            'customers_count' => $this->customers()->count(),
            'vehicles_count' => $this->vehicles()->count(),
            'active_checkins_count' => $this->checkins()->where('status', '!=', 'completed')->count(),
            'total_checkins_count' => $this->checkins()->count(),
            'tires_stored_count' => $this->tires()->where('status', 'stored')->count(),
        ];
    }
}
