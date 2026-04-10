@include('errors.layouts.error', [
    'code' => '500',
    'title' => 'Something Went Wrong',
    'message' => 'An unexpected error occurred on our end. The incident has been logged and the team has been notified. Please try again in a moment.',
    'iconBgClass' => 'bg-red-500/20',
    'codeColorClass' => 'text-red-300',
    'icon' => '<svg class="w-10 h-10 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
])
