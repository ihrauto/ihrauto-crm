@include('errors.layouts.error', [
    'code' => '404',
    'title' => 'Page Not Found',
    'message' => $exception->getMessage() ?: 'The page you\'re looking for doesn\'t exist, has been moved, or is not accessible from your account.',
    'iconBgClass' => 'bg-indigo-500/20',
    'codeColorClass' => 'text-indigo-300',
    'icon' => '<svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
])
