---
description: Standard back button component style for IHRAUTO CRM
---

# Back Button Style

When adding a back button, use this consistent pattern:

```blade
<a href="{{ route('destination.route') }}"
    class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
    </svg>
    Back to [Destination]
</a>
```

## Properties:
- **Border:** `border border-indigo-200`
- **Text:** `text-indigo-600 text-sm font-medium`
- **Padding:** `px-4 py-2`
- **Radius:** `rounded-lg`
- **Hover:** `hover:bg-indigo-50`
- **Icon:** Left arrow SVG, `w-4 h-4 mr-2`

## Example Usage:
```blade
<a href="{{ route('work-orders.index') }}"
    class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
    </svg>
    Back to Work Orders
</a>
```
