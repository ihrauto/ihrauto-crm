<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkOrderPhotoController extends Controller
{
    /**
     * Upload a photo to a work order.
     */
    public function store(Request $request, WorkOrder $workOrder)
    {
        $this->authorize('create', WorkOrderPhoto::class);

        // Defense in depth: verify work order tenant matches request tenant
        abort_unless(
            (int) $workOrder->tenant_id === (int) tenant_id(),
            403,
            'Cannot upload photos to a work order from another tenant.'
        );

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB max
            'type' => 'required|in:before,after',
            'caption' => 'nullable|string|max:255',
        ]);

        $file = $request->file('photo');

        // Validate file is a real image (defense against MIME spoofing)
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            return back()->with('error', 'The uploaded file is not a valid image.');
        }

        $tenantId = tenant_id();

        // Generate unique filename
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        // Store in tenant-specific directory
        $path = "work-order-photos/{$tenantId}/{$workOrder->id}/{$filename}";
        Storage::disk('public')->put($path, file_get_contents($file));

        // Create database record
        $photo = WorkOrderPhoto::create([
            'tenant_id' => $tenantId,
            'work_order_id' => $workOrder->id,
            'user_id' => auth()->id(),
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'type' => $request->type,
            'caption' => $request->caption,
        ]);

        return back()->with('success', ucfirst($request->type).' photo uploaded successfully.');
    }

    /**
     * Delete a photo. Policy enforces:
     *   - Same tenant as actor AND current request context
     *   - Uploader OR admin/owner role
     *   - Admin/owner required after the work order is completed/invoiced
     */
    public function destroy(WorkOrder $workOrder, WorkOrderPhoto $photo)
    {
        // Defense in depth: route parameters must match
        abort_unless(
            (int) $photo->work_order_id === (int) $workOrder->id,
            404,
            'Photo does not belong to this work order.'
        );

        $this->authorize('delete', $photo);

        // Delete file from storage
        Storage::disk('public')->delete($photo->path);

        // Delete database record
        $photo->delete();

        return back()->with('success', 'Photo deleted successfully.');
    }
}
