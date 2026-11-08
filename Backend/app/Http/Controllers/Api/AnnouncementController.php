<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementReaction;
use App\Models\AnnouncementRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AnnouncementController extends Controller
{
    public const EMOJIS = ['👍', '❤️', '🎉', '👏', '😮', '😂'];

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $query = Announcement::query()
            ->with(['organizer:id,name', 'author:id,name', 'department:id,code,name'])
            ->with('reactions:id,announcement_id,user_id,emoji')
            ->when($user, fn (Builder $query) => $query->with([
                'reads' => fn ($reads) => $reads->where('user_id', $user->id),
            ]));
        $this->scopeVisible($query, $user, $request);
        $announcements = $query
            ->when($request->filled('course'), fn (Builder $query) => $query->where(
                fn (Builder $courses) => $courses
                    ->where('course', $request->query('course'))
                    ->orWhereNull('course'),
            ))
            ->when($request->filled('organizer_id'), fn (Builder $query) => $query->where('organizer_id', $request->integer('organizer_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhereHas('organizer', fn (Builder $organizer) => $organizer->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Announcement $announcement): array => $this->present($announcement, $user));

        return response()->json([
            'data' => $announcements,
            'unread_count' => $user ? $this->countUnread($user) : 0,
            'emojis' => self::EMOJIS,
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->countUnread($request->user())]);
    }

    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isVisible($announcement, $user), 404);

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        return response()->json([
            'is_read' => true,
            'unread_count' => $this->countUnread($user),
        ]);
    }

    public function react(Request $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(self::EMOJIS)],
        ]);
        $user = $request->user();
        abort_unless($this->isVisible($announcement, $user), 404);
        $existing = $announcement->reactions()->where('user_id', $user->id)->first();

        if ($existing?->emoji === $data['emoji']) {
            $existing->delete();
            $reacted = false;
        } else {
            $announcement->reactions()->updateOrCreate(
                ['user_id' => $user->id],
                ['emoji' => $data['emoji']],
            );
            $reacted = true;
        }

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        $announcement->load('reactions:id,announcement_id,user_id,emoji');

        return response()->json([
            'reacted' => $reacted,
            'emoji' => $data['emoji'],
            'reactions' => $this->reactionSummary($announcement, $user),
            'reaction_count' => $announcement->reactions->count(),
            'unread_count' => $this->countUnread($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Announcement $announcement, ?User $user): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'pinned' => $announcement->pinned,
            'course' => $announcement->course,
            'department' => $announcement->department?->code,
            'created_at' => $announcement->created_at,
            'organizer' => $announcement->organizer,
            'author' => $announcement->author,
            'is_read' => $user !== null
                && $announcement->relationLoaded('reads')
                && $announcement->reads->isNotEmpty(),
            'reactions' => $this->reactionSummary($announcement, $user),
            'reaction_count' => $announcement->reactions->count(),
        ];
    }

    private function reactionSummary(Announcement $announcement, ?User $user): Collection
    {
        return $announcement->reactions
            ->groupBy('emoji')
            ->map(fn (Collection $reactions, string $emoji): array => [
                'emoji' => $emoji,
                'count' => $reactions->count(),
                'reacted' => $user !== null
                    && $reactions->contains(fn (AnnouncementReaction $reaction): bool => $reaction->user_id === $user->id),
            ])
            ->sortByDesc('count')
            ->values();
    }

    private function countUnread(User $user): int
    {
        $query = Announcement::query();
        $this->scopeVisible($query, $user, request());

        return $query->whereDoesntHave('reads', fn (Builder $reads) => $reads->where('user_id', $user->id))->count();
    }

    private function scopeVisible(Builder $query, ?User $user, Request $request): void
    {
        if (! $user) {
            $query->whereNull('department_id');

            return;
        }
        if ($user->role !== 'admin') {
            $query->where(fn (Builder $audience) => $audience
                ->whereNull('department_id')
                ->orWhereHas('department', fn (Builder $department) => $department->where('code', $user->department)));
        }
        if (in_array($user->role, ['student', 'faculty'], true)) {
            $query->where(fn (Builder $audience) => $audience
                ->whereNull('course')
                ->orWhere('course', $user->course));
        }
    }

    private function isVisible(Announcement $announcement, User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($announcement->department_id
            && $announcement->department?->code !== $user->department) {
            return false;
        }

        return ! in_array($user->role, ['student', 'faculty'], true)
            || $announcement->course === null
            || $announcement->course === $user->course;
    }

    private function resolveUser(Request $request): ?User
    {
        if ($user = $request->user()) {
            return $user;
        }

        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $user = PersonalAccessToken::findToken($token)?->tokenable;

        return $user?->is_active ? $user : null;
    }
}
