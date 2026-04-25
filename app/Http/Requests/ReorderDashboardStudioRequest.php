<?php

namespace App\Http\Requests;

use App\Support\DashboardWidgetCatalog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ENG-009: validates the user-submitted widget order from drag-reorder.
 * Same shape rules as the toggle endpoint — the service drops unknown
 * keys silently to stay forward-compatible with stale tabs.
 */
class ReorderDashboardStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order' => ['present', 'array', 'max:'.DashboardWidgetCatalog::MAX_KEYS],
            'order.*' => ['string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
