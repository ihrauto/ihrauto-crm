<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class TenantValidation
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where(fn ($query) => $query->where('tenant_id', tenant_id()));
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)
            ->where(fn ($query) => $query->where('tenant_id', tenant_id()));
    }
}
