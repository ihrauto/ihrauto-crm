<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $validated['tenant_id'] = tenant()->id;
        $validated['is_active'] = true;

        Service::create($validated);

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service created successfully.');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean', // can be passed from edit form
        ]);

        if (! $request->has('is_active')) {
            $validated['is_active'] = false; // Checkbox unchecked means false
        }

        $service->update($validated);

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service deleted.');
    }

    public function toggle(Service $service)
    {
        $service->update(['is_active' => ! $service->is_active]);

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service status updated.');
    }
}
