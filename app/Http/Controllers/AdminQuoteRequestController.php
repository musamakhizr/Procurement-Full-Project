<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateQuoteRequestStatusRequest;
use App\Http\Resources\QuoteRequestResource;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;

class AdminQuoteRequestController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        $quoteRequests = QuoteRequest::query()
            ->with(['items', 'user'])
            ->latest()
            ->paginate($perPage);

        return QuoteRequestResource::collection($quoteRequests);
    }

    public function update(UpdateQuoteRequestStatusRequest $request, QuoteRequest $quoteRequest)
    {
        $quoteRequest->update([
            'status' => $request->validated('status'),
        ]);

        $quoteRequest->load(['items', 'user']);

        return response()->json([
            'message' => 'Quote request updated successfully.',
            'data' => new QuoteRequestResource($quoteRequest),
        ]);
    }
}
