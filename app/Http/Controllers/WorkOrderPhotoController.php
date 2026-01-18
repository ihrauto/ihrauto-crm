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
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB max
            'type' => 'required|in:before,after',
            'caption' => 'nullable|string|max:255',
        ]);

        $file = $request->file('photo');
        $tenantId = auth()->user()->tenant_id;

        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

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

        return back()->with('success', ucfirst($request->type) . ' photo uploaded successfully.');
    }

    /**
     * Delete a photo (only uploader or admin can delete).
     */
    public function destroy(WorkOrder $workOrder, WorkOrderPhoto $photo)
    {
        // Authorization: Only uploader or admin can delete
        if ($photo->user_id !== auth()->id() && !auth()->user()->hasRole(['admin', 'owner'])) {
            abort(403, 'You can only delete your own photos.');
        }

        // Delete file from storage
        Storage::disk('public')->delete($photo->path);

        // Delete database record
        $photo->delete();

        return back()->with('success', 'Photo deleted successfully.');
    }
}
