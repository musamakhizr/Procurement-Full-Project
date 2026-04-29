<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateSourcingRequestStatusRequest;
use App\Http\Resources\SourcingRequestResource;
use App\Models\SourcingRequest;

class AdminSourcingRequestController extends Controller
{
    public function index()
    {
        $requests = SourcingRequest::query()
            ->with(['links', 'user'])
            ->latest()
            ->get();

        return response()->json([
            'data' => SourcingRequestResource::collection($requests),
        ]);
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
