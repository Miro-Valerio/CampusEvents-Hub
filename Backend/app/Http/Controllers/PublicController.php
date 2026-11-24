<?php

namespace App\Http\Controllers;

use App\Models\{Department, Event};
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function departments()
    {
        return Department::withCount(['users', 'events'])->orderBy('name')->get();
    }

    public function department(Department $department)
    {
        return $department->loadCount(['users', 'events']);
    }

    public function events(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:60',
            'department_id' => 'nullable|integer|exists:departments,id',
            'date' => 'nullable|date',
            'sort' => 'nullable|in:soonest,newest,popular',
            'archive' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
        ]);

        $archive = $request->boolean('archive');
        $now = now();
        $query = Event::query()
            ->with([
                'department:id,name,code',
            ])
            ->withCount(['rsvps as attendee_count' => fn ($q) => $q->where('status', 'active')]);

        if ($archive) {
            $query->where(function ($events) use ($now) {
                $events->where('status', 'cancelled')
                    ->orWhere(fn ($ended) => $ended->where('status', 'published')->where('ends_at', '<', $now));
            });
        } else {
            $query->where('status', 'published')->where('ends_at', '>=', $now);
        }

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('venue', 'like', '%'.$search.'%')
                ->orWhereHas('department', fn ($d) => $d->where('name', 'like', '%'.$search.'%')));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->input('date'));
        }

        $sort = $request->input('sort', $archive ? 'newest' : 'soonest');
        if ($sort === 'newest') {
            if ($archive) {
                $query->orderByRaw("CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END")
                    ->orderByDesc('ends_at')
                    ->orderByDesc('created_at');
            } else {
                $query->latest();
            }
        } elseif ($sort === 'popular') {
            $query->orderByDesc('views');
        } elseif ($archive) {
            $query->orderBy('ends_at')->orderByDesc('created_at');
        } else {
            $query->orderByRaw('CASE WHEN starts_at > ? THEN 0 ELSE 1 END', [$now])
                ->orderBy('starts_at');
        }

        return response()->json($query->paginate(12))
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Event $event)
    {
        $user = auth('sanctum')->user();
        $canManage = $user && ($user->role === 'admin'
            || ($event->department_id !== null && $user->canDepartment('events', $event->department_id)));
        abort_unless(in_array($event->status,['published','cancelled'],true) || $canManage, 404);

        $event->increment('views');

        return $event->load([
            'department:id,name,code,description,email',
        ])->loadCount(['rsvps as attendee_count' => fn ($q) => $q->where('status', 'active')]);
    }

    public function attendeePreview(Event $event)
    {
        abort_unless(
            in_array($event->status,['published','cancelled'],true),
            404
        );

        $names = $event->rsvps()->where('status', 'active')->with('user:id,name')->limit(15)->get()->map(function ($row) {
            $parts = preg_split('/\s+/u', trim($row->user->name));

            return implode(' ', array_map(fn ($word) => mb_substr($word, 0, 1).'***', $parts));
        });

        return ['names' => $names, 'count' => $event->rsvps()->where('status', 'active')->count()];
    }

    public function calendar(Request $request)
    {
        $data = $request->validate(['year' => 'required|integer|between:2020,2100', 'month' => 'required|integer|between:1,12']);
        $begin = \Carbon\Carbon::createFromDate($data['year'], $data['month'], 1)->startOfDay();
        $end = $begin->copy()->endOfMonth();

        return Event::query()->whereIn('status',['published','cancelled'])
            ->whereBetween('starts_at', [$begin, $end])
            ->with(['department:id,name,code'])
            ->orderBy('starts_at')
            ->get();
    }

    public function categories()
    {
        return Event::query()->whereIn('status',['published','cancelled'])
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    public function stats()
    {
        return [
            'events' => Event::where('status','published')->where('ends_at', '>=', now())->count(),
            'departments' => Department::count(),
        ];
    }
}
