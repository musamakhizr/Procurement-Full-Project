<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateSourcingRequestStatusRequest;
use App\Http\Resources\SourcingRequestResource;
use App\Models\SourcingRequest;
use Illuminate\Http\Request;

class AdminSourcingRequestController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        $requests = SourcingRequest::query()
            ->with(['links', 'user'])
            ->latest()
            ->paginate($perPage);

        return SourcingRequestResource::collection($requests);
    }

    public function update(UpdateSourcingRequestStatusRequest $request, SourcingRequest $sourcingRequest)
    {
        $sourcingRequest->update([
            'status' => $request->validated('status'),
        ]);

        $sourcingRequest->load(['links', 'user']);

        return response()->json([
            'message' => 'Sourcing request updated successfully.',
            'data' => new SourcingRequestResource($sourcingRequest),
        ]);
    }
}
