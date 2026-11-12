<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportEventRequest;
use App\Models\Event;
use App\Models\EventReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventReportController extends Controller
{
    public function store(ReportEventRequest $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'organizer' && $user->organizer?->events()->whereKey($event->id)->exists()) {
            return response()->json(['message' => 'You cannot report your own event.'], 422);
        }

        $report = EventReport::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'reason' => $request->validated('reason'),
                'details' => $request->validated('details'),
                'status' => EventReport::STATUS_OPEN,
                'resolved_at' => null,
            ],
        );

        return response()->json([
            'message' => 'Thanks — your report was sent to the organizer.',
            'report' => $report,
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function organizerIndex(Request $request): JsonResponse
    {
        $organizerId = $request->user()->organizer->id;

        $reports = EventReport::query()
            ->with(['event:id,title,starts_at,status', 'user:id,name,email'])
            ->whereHas('event', fn ($query) => $query->where('organizer_id', $organizerId))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $reports]);
    }

    public function resolve(EventReport $report): JsonResponse
    {
        Gate::authorize('update', $report->event);

        $report->update([
            'status' => EventReport::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Report marked as resolved.',
            'report' => $report->fresh(),
        ]);
    }
}
