<?php

namespace App\Http\Requests;

use App\Support\DashboardWidgetCatalog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ENG-009: validates the user-submitted enabled widget list.
 *
 * Note: we do NOT reject unknown keys here. The service drops them
 * silently to keep stale tabs / older clients working. We only enforce
 * shape (string array, capped length, lowercase identifier pattern) so
 * an attacker can't push 1MB of JSON into the column.
 */
class StoreDashboardStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'keys' => ['present', 'array', 'max:'.DashboardWidgetCatalog::MAX_KEYS],
            'keys.*' => ['string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
