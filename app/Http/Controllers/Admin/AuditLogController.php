<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display the audit logs listing page with filtering and pagination.
     *
     * Authorization: Protected by role:Admin middleware in routes.
     *
     * Query optimizations:
     * 1. Eager load actor relationship to avoid N+1
     * 2. Use conditional when() clauses for optional filters
     * 3. Index-friendly filtering on actor_id and activity_type
     */
    public function index(Request $request): Response
    {
        $logs = ActivityLog::query()
            ->with('actor:id,first_name,last_name,email')
            ->when($request->filled('user'), function (Builder $query) use ($request) {
                $query->where('actor_id', $request->input('user'));
            })
            ->when($request->filled('action'), function (Builder $query) use ($request) {
                $query->where('activity_type', $request->input('action'));
            })
            ->when($request->filled('entity'), function (Builder $query) use ($request) {
                $query->where('loggable_type', $request->input('entity'));
            })
            ->when($request->filled('date_from'), function (Builder $query) use ($request) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            })
            ->when($request->filled('date_to'), function (Builder $query) use ($request) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => $this->formatLogForList($log));

        // Get distinct activity types for filter dropdown
        $activityTypes = ActivityLog::query()
            ->select('activity_type')
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type');

        // Get distinct entity types for filter dropdown
        $entityTypes = ActivityLog::query()
            ->select('loggable_type')
            ->distinct()
            ->orderBy('loggable_type')
            ->pluck('loggable_type')
            ->map(fn (string $type) => [
                'value' => $type,
                'label' => class_basename($type),
            ]);

        // Get users who have performed actions for filter dropdown
        $users = User::query()
            ->whereIn('id', ActivityLog::select('actor_id')->distinct())
            ->select('id', 'first_name', 'last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => "{$user->first_name} {$user->last_name}",
            ]);

        return Inertia::render('admin/AuditLogs', [
            'logs' => $logs,
            'activityTypes' => $activityTypes,
            'entityTypes' => $entityTypes,
            'users' => $users,
            'filters' => [
                'user' => $request->input('user'),
                'action' => $request->input('action'),
                'entity' => $request->input('entity'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
        ]);
    }

    /**
     * Format an activity log entry for display.
     *
     * @return array<string, mixed>
     */
    protected function formatLogForList(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'activity_type' => $log->activity_type,
            'entity_type' => class_basename($log->loggable_type),
            'entity_id' => $log->loggable_id,
            'metadata' => $log->metadata,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => "{$log->actor->first_name} {$log->actor->last_name}",
                'email' => $log->actor->email,
            ] : null,
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $log->created_at->diffForHumans(),
        ];
    }
}
