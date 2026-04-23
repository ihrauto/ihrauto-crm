<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;

class ServiceController extends Controller
{
    public function store(StoreServiceRequest $request)
    {
        $this->authorize('create', Service::class);

        $validated = $request->validated();

        $validated['tenant_id'] = tenant_id();
        $validated['is_active'] = true;

        Service::create($validated);

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service created successfully.');
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $this->authorize('update', $service);

        $validated = $request->validated();

        // Handle is_active checkbox - if checkbox exists but unchecked, set false
        if ($request->has('is_active')) {
            $validated['is_active'] = (bool) $request->is_active;
        } else {
            unset($validated['is_active']);
        }

        $service->update($validated);

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        $service->delete();

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service deleted.');
    }

    public function toggle(Service $service)
    {
        $this->authorize('update', $service);

        $service->update(['is_active' => ! $service->is_active]);

        return redirect()->route('products-services.index', ['tab' => 'services'])
            ->with('success', 'Service status updated.');
    }
}
