<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sourcing\StoreSourcingRequestRequest;
use App\Http\Resources\SourcingRequestResource;
use App\Models\SourcingRequest;
use Illuminate\Http\Request;

class SourcingRequestController extends Controller
{
    public function index(Request $request)
    {
        $sourcingRequests = SourcingRequest::query()
            ->where('user_id', $request->user()->id)
            ->with('links')
            ->latest()
            ->get();

        return response()->json([
            'data' => SourcingRequestResource::collection($sourcingRequests),
        ]);
    }

    public function store(StoreSourcingRequestRequest $request)
    {
        $sourcingRequest = SourcingRequest::query()->create([
            'user_id' => $request->user()->id,
            'reference' => SourcingRequest::generateReference(),
            'type' => $request->string('type')->toString(),
            'status' => 'submitted',
            'title' => $request->string('title')->toString(),
            'details' => $request->string('details')->toString(),
            'quantity' => $request->integer('quantity'),
            'budget_text' => $request->input('budget_text'),
            'delivery_date' => $request->date('delivery_date'),
            'notes' => $request->input('notes'),
        ]);

        collect($request->input('links', []))
            ->filter()
            ->each(fn (string $url) => $sourcingRequest->links()->create(['url' => $url]));

        return response()->json([
            'message' => 'Sourcing request submitted successfully.',
            'data' => [
                'id' => $sourcingRequest->id,
                'reference' => $sourcingRequest->reference,
                'status' => $sourcingRequest->status,
                'status_label' => $sourcingRequest->status_label,
            ],
        ], 201);
    }
}
