<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\TenantProvisioningService;

class RegisterTenantOwner
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService
    ) {
    }

    /**
     * Handle tenant owner registration.
     *
     * Creates a new tenant with 14-day trial, creates the owner user,
     * assigns admin role, and fires the Registered event for email verification.
     *
     * @param  array  $data  Array containing: name, email, password, company_name
     * @return User The created user
     */
    public function handle(array $data): User
    {
        return $this->tenantProvisioningService->provisionOwner($data);
    }
}
