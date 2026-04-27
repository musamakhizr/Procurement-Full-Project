<?php

namespace App\Http\Controllers;

use App\Models\ProcurementListItem;
use App\Models\SourcingRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $actionItems = SourcingRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (SourcingRequest $sourcingRequest) => [
                'id' => $sourcingRequest->reference,
                'title' => $sourcingRequest->title,
                'status' => $sourcingRequest->status,
                'status_text' => $sourcingRequest->status_label,
                'next_step' => $sourcingRequest->next_step,
                'action' => $sourcingRequest->action_label,
                'urgent' => in_array($sourcingRequest->status, ['needs_info', 'quoted'], true),
                'date' => $sourcingRequest->created_at?->toDateString(),
            ]);

        $recentActivity = SourcingRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (SourcingRequest $sourcingRequest) => [
                'id' => $sourcingRequest->id,
                'action' => "{$sourcingRequest->reference} {$sourcingRequest->title}",
                'time' => $sourcingRequest->created_at?->diffForHumans(),
            ]);

        $monthSpend = ProcurementListItem::query()
            ->where('user_id', $user->id)
            ->get()
            ->sum(fn (ProcurementListItem $item) => $item->quantity * $item->unit_price);

        return response()->json([
            'summary' => [
                'pending_requests' => SourcingRequest::query()->where('user_id', $user->id)->whereIn('status', ['submitted', 'under_review', 'needs_info', 'quoted'])->count(),
                'active_orders' => SourcingRequest::query()->where('user_id', $user->id)->whereIn('status', ['quoted', 'approved', 'processing'])->count(),
                'month_spend' => round($monthSpend, 2),
                'savings_percentage' => 18,
            ],
            'action_items' => $actionItems,
            'recent_activity' => $recentActivity,
        ]);
    }
}
