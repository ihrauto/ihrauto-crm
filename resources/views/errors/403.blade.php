@include('errors.layouts.error', [
    'code' => '403',
    'title' => 'Access Denied',
    'message' => $exception->getMessage() ?: 'You don\'t have permission to access this resource. If you believe this is a mistake, contact your administrator.',
    'iconBgClass' => 'bg-amber-500/20',
    'codeColorClass' => 'text-amber-300',
    'icon' => '<svg class="w-10 h-10 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
])
