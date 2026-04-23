<?php

namespace App\Models;

use App\Support\TenantCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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

    public static function planCatalog(): array
    {
        return [
            self::PLAN_BASIC => [
                'name' => 'Basic',
                'price' => 49,
                'price_label' => 'EUR 49',
                'billing_label' => '/month',
                'description' => 'For lean teams that need customer intake, work orders, and invoicing in one place.',
                'highlights' => ['1 user', '100 customers', '200 vehicles', '50 work orders / month'],
                'limits' => [
                    'max_users' => 1,
                    'max_customers' => 100,
                    'max_vehicles' => 200,
                    'max_work_orders' => 50,
                ],
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
                'name' => 'Standard',
                'price' => 149,
                'price_label' => 'EUR 149',
                'billing_label' => '/month',
                'description' => 'For growing garages that need shared visibility across service, finance, and storage.',
                'highlights' => ['5 users', '1,000 customers', '3,000 vehicles', 'Unlimited work orders'],
                'limits' => [
                    'max_users' => 5,
                    'max_customers' => 1000,
                    'max_vehicles' => 3000,
                    'max_work_orders' => null,
                ],
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
                'name' => 'Custom',
                'price' => null,
                'price_label' => 'Custom',
                'billing_label' => 'plan',
                'description' => 'For workshop groups, custom rollout needs, and businesses that need deeper integration support.',
                'highlights' => ['Unlimited users', 'Unlimited customers', 'Unlimited vehicles', 'Unlimited work orders'],
                'limits' => [
                    'max_users' => 999999,
                    'max_customers' => 999999,
                    'max_vehicles' => 999999,
                    'max_work_orders' => null,
                ],
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
        ];
    }

    public static function planDefinition(?string $plan): array
    {
        return self::planCatalog()[$plan] ?? self::planCatalog()[self::PLAN_BASIC];
    }

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
            if (! $tenant->slug) {
                $tenant->slug = Str::slug($tenant->name);
            }

            // Auto-generate subdomain if not provided
            if (! $tenant->subdomain) {
                $tenant->subdomain = $tenant->slug;
            }

            // Set trial end date
            if ($tenant->is_trial && ! $tenant->trial_ends_at) {
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

    public function apiTokens(): HasMany
    {
        return $this->hasMany(TenantApiToken::class);
    }

    /**
     * Scopes
     *
     * Bug review DATA-12: `active()` intentionally checks ONLY is_active,
     * not expiry. Tenants that let their trial lapse stay reachable (so
     * the tenant middleware can route them to the billing page to
     * renew). For callers that need "active AND currently paid", use
     * `notExpired()` which adds the expiry filter.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Bug review DATA-12: strict scope for "tenant can use the product
     * right now" — adds the trial / subscription expiry check on top of
     * scopeActive(). Use this for background jobs that enumerate
     * currently-paying tenants (billing, provisioning, notifications);
     * do NOT use it from the request middleware because expired tenants
     * still need to reach the billing-renewal endpoint.
     */
    public function scopeNotExpired($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($trial) {
                    $trial->where('is_trial', true)
                        ->where(function ($ends) {
                            $ends->whereNull('trial_ends_at')
                                ->orWhere('trial_ends_at', '>=', now());
                        });
                })->orWhere(function ($subscribed) {
                    $subscribed->where('is_trial', false)
                        ->where(function ($ends) {
                            $ends->whereNull('subscription_ends_at')
                                ->orWhere('subscription_ends_at', '>=', now());
                        });
                });
            });
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

        return "https://{$this->subdomain}.".config('app.domain', 'yourapp.com');
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
            return max(0, now()->diffInDays($this->trial_ends_at, false));
        }
        if (! $this->is_trial && $this->subscription_ends_at) {
            return max(0, now()->diffInDays($this->subscription_ends_at, false));
        }

        return null;
    }

    /**
     * C-05: tenant-specific tax rate lookup.
     *
     * Settings JSON can override the platform default (config/crm.php).
     * Swiss tenants keep 8.1% VAT automatically; international tenants can
     * override without a code change.
     *
     * Returned as float percentage (e.g. 8.1, not 0.081).
     */
    public function taxRate(): float
    {
        $settings = $this->settings ?? [];
        $candidate = $settings['tax_rate'] ?? null;

        if (is_numeric($candidate)) {
            return (float) $candidate;
        }

        return (float) config('crm.tax_rate', 8.1);
    }

    /**
     * Business Logic Methods
     *
     * Bug review DATA-05: the $forceFresh flag bypasses the 60-second
     * count cache. PlanQuota::lockedQuotaExceeded uses this when it
     * holds a FOR UPDATE lock on the tenant row — inside that critical
     * section we must read the authoritative count, not a stale cached
     * value, otherwise concurrent invite-accepts still overbook.
     */
    public function canAddUser(bool $forceFresh = false): bool
    {
        $count = $forceFresh
            ? $this->users()->count()
            : \App\Support\CachedQuery::remember("tenant_{$this->id}_user_count", 60, fn () => $this->users()->count());

        return $count < $this->max_users;
    }

    public function canAddCustomer(bool $forceFresh = false): bool
    {
        $count = $forceFresh
            ? $this->customers()->count()
            : \App\Support\CachedQuery::remember("tenant_{$this->id}_customer_count", 60, fn () => $this->customers()->count());

        return $count < $this->max_customers;
    }

    public function canAddVehicle(bool $forceFresh = false): bool
    {
        $count = $forceFresh
            ? $this->vehicles()->count()
            : \App\Support\CachedQuery::remember("tenant_{$this->id}_vehicle_count", 60, fn () => $this->vehicles()->count());

        return $count < $this->max_vehicles;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function enableFeature(string $feature): void
    {
        $features = $this->features ?? [];
        if (! in_array($feature, $features)) {
            $features[] = $feature;
            $this->update(['features' => $features]);
            TenantCache::forgetTenant($this);
        }
    }

    public function disableFeature(string $feature): void
    {
        $features = $this->features ?? [];
        $features = array_filter($features, fn ($f) => $f !== $feature);
        $this->update(['features' => array_values($features)]);
        TenantCache::forgetTenant($this);
    }

    public function updateLastActivity(): void
    {
        if (! $this->last_activity_at || $this->last_activity_at->lt(now()->subMinutes(5))) {
            $this->update(['last_activity_at' => now()]);
        }
    }

    public function suspend(): void
    {
        $this->update(['is_active' => false]);
        TenantCache::forgetTenant($this);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
        TenantCache::forgetTenant($this);
    }

    public function convertToSubscription(string $plan, \Carbon\Carbon $endsAt): void
    {
        $definition = self::planDefinition($plan);

        $this->update([
            'is_trial' => false,
            'plan' => $plan,
            'subscription_ends_at' => $endsAt,
            'trial_ends_at' => null,
            'max_users' => $definition['limits']['max_users'],
            'max_customers' => $definition['limits']['max_customers'],
            'max_vehicles' => $definition['limits']['max_vehicles'],
            'max_work_orders' => $definition['limits']['max_work_orders'],
            'features' => $definition['features'],
            'is_active' => true,
        ]);
        TenantCache::forgetTenant($this);
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
        $definition = self::planDefinition($this->plan);

        return [
            'max_users' => $definition['limits']['max_users'],
            'max_customers' => $definition['limits']['max_customers'],
            'max_vehicles' => $definition['limits']['max_vehicles'],
            'max_work_orders' => $definition['limits']['max_work_orders'],
            'features' => $definition['features'],
        ];
    }

    /**
     * Check if tenant can create a new work order (for BASIC plan monthly limit)
     *
     * Bug review DATA-05: $forceFresh bypasses any count cache in
     * getMonthlyWorkOrderCount() when called under a tenant-row lock.
     */
    public function canCreateWorkOrder(bool $forceFresh = false): bool
    {
        if (in_array($this->plan, [self::PLAN_STANDARD, self::PLAN_CUSTOM])) {
            return true;
        }

        $maxWorkOrders = $this->max_work_orders ?? 50;

        return $this->getMonthlyWorkOrderCount($forceFresh) < $maxWorkOrders;
    }

    /**
     * Get remaining work orders for this month (BASIC plan)
     */
    public function getRemainingWorkOrdersAttribute(): ?int
    {
        if (in_array($this->plan, [self::PLAN_STANDARD, self::PLAN_CUSTOM])) {
            return null;
        }

        $maxWorkOrders = $this->max_work_orders ?? 50;

        return max(0, $maxWorkOrders - $this->getMonthlyWorkOrderCount());
    }

    /**
     * Get cached monthly work order count (60-second TTL).
     *
     * Bug review DATA-05: $forceFresh skips the cache so quota checks
     * under a tenant-row lock see the authoritative count.
     */
    protected function getMonthlyWorkOrderCount(bool $forceFresh = false): int
    {
        $produce = fn () => $this->workOrders()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($forceFresh) {
            return $produce();
        }

        $key = "tenant_{$this->id}_wo_month_".now()->format('Y_m');

        return Cache::remember($key, 60, $produce);
    }

    /**
     * Check if tenant has access to Tire Hotel feature
     */
    public function hasTireHotel(): bool
    {
        return in_array($this->plan, [self::PLAN_STANDARD, self::PLAN_CUSTOM], true)
            && $this->hasFeature('tire_hotel');
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
