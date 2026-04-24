@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('crm.quote.quotes') }}</h1>
        <a href="{{ route('quotes.create') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
            {{ __('crm.quote.new') }}
        </a>
    </div>

    <x-flash-message />

    <form method="get" class="flex flex-wrap gap-3 mb-4">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('crm.quote.search_placeholder') }}"
               class="flex-1 min-w-[220px] rounded border-gray-300 text-sm"/>
        <select name="status" class="rounded border-gray-300 text-sm">
            <option value="">{{ __('crm.quote.all_statuses') }}</option>
            @foreach (['draft','sent','accepted','rejected','converted'] as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __('crm.quote.status_'.$s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-3 py-2 rounded bg-gray-100 text-sm">{{ __('crm.quote.filter') }}</button>
    </form>

    <div class="bg-white shadow overflow-hidden rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('crm.quote.number') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('crm.quote.customer') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('crm.quote.issued') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('crm.common.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('crm.finance.total') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse ($quotes as $quote)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $quote->quote_number }}</td>
                        <td class="px-4 py-3">{{ $quote->customer?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $quote->issue_date?->format('d.m.Y') }}</td>
                        <td class="px-4 py-3">{{ __('crm.quote.status_'.$quote->status) }}</td>
                        <td class="px-4 py-3 text-right">{{ config('crm.currency.code','CHF') }} {{ number_format((float) $quote->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('quotes.show', $quote) }}" class="text-indigo-600 hover:underline">{{ __('crm.common.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('crm.quote.empty_list') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $quotes->links() }}</div>
</div>
@endsection
