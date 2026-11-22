<?php

namespace App\Http\Controllers;

use App\Models\{Department,Membership};
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    public function mine(Request $request)
    {
        return Membership::where('user_id', $request->user()->id)
            ->with('department')
            ->latest()
            ->get();
    }

    public function join(Request $request, Department $department)
    {
        $user = $request->user();
        abort_unless(
            $user->role === 'student' && (int) $user->department_id === $department->id,
            403,
            'You may only join your registered academic department.'
        );

        return DB::transaction(function () use ($user, $department) {
            $membership = Membership::where('user_id', $user->id)
                ->where('department_id', $department->id)
                ->lockForUpdate()
                ->first();

            if ($membership && in_array($membership->status, ['pending', 'approved'], true)) {
                return response()->json(['message' => 'Your request is already '.$membership->status.'.'], 422);
            }

            if ($membership) {
                $membership->update(['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
            } else {
                $membership = Membership::create([
                    'user_id' => $user->id,
                    'department_id' => $department->id,
                    'status' => 'pending',
                ]);
            }

            $key = 'membership:'.$membership->id.':submitted:'.now()->format('YmdHis').':'.\Illuminate\Support\Str::random(8);
            $organizerLink = '/organizer?tab=members&department_id='.$department->id;

            Hub::notify($user, 'membership', 'Membership request submitted',
                'Your request to join '.$department->name.' was submitted and is pending review. Reference #'.$membership->id.'.',
                '/departments/'.$department->id, $key.':student');
            Hub::notifyAdmins('membership', 'New department membership request',
                $user->name.' requested to join '.$department->name.'. Reference #'.$membership->id.'.',
                $organizerLink, $key.':admins');
            Hub::notifyDepartmentManagers($department->id, 'members', 'membership', 'New membership request',
                $user->name.' requested to join '.$department->name.'. Reference #'.$membership->id.'.',
                $organizerLink, $key.':managers');

            return response()->json(['message' => 'Membership request submitted.', 'membership' => $membership], 201);
        });
    }

    public function cancel(Request $request, Department $department)
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $department) {
            $membership = Membership::where('user_id', $user->id)
                ->where('department_id', $department->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();
            $membership->update(['status' => 'cancelled']);
            $key = 'membership:'.$membership->id.':cancelled:'.Str::uuid();
            $organizerLink = '/organizer?tab=members&department_id='.$department->id;

            Hub::notify($user, 'membership', 'Membership request cancelled',
                'Your request to join '.$department->name.' was cancelled.',
                '/departments/'.$department->id, $key.':student');
            Hub::notifyAdmins('membership', 'Membership request cancelled',
                $user->name.' cancelled the request to join '.$department->name.'. Reference #'.$membership->id.'.',
                $organizerLink, $key.':admins');
            Hub::notifyDepartmentManagers($department->id, 'members', 'membership', 'Membership request cancelled',
                $user->name.' cancelled the request to join '.$department->name.'. Reference #'.$membership->id.'.',
                $organizerLink, $key.':managers');

            return ['message' => 'Request cancelled.'];
        });
    }

    public function requests(Request $request)
    {
        $user = $request->user();
        $departmentId = $user->role === 'admin'
            ? (int) $request->input('department_id')
            : (int) $user->department_id;

        abort_unless($departmentId, 422, 'Please select a department.');
        Hub::canManage($user, 'members', $departmentId);

        return Membership::with(['user:id,name,email,course', 'department:id,name'])
            ->where('department_id', $departmentId)
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->get();
    }

    public function review(Request $request, Membership $membership)
    {
        $user = $request->user();
        Hub::canManage($user, 'members', $membership->department_id);
        $data = $request->validate(['status' => 'required|in:approved,rejected']);

        return DB::transaction(function () use ($user, $membership, $data) {
            $membership = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            abort_unless($membership->status === 'pending', 422, 'Only pending requests can be reviewed.');

            $membership->update([
                'status' => $data['status'],
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            $membership->load(['user', 'department']);
            Hub::notify(
                $membership->user,
                'membership',
                'Department membership '.$data['status'],
                'Your request for '.$membership->department->name.' was '.$data['status'].'.',
                '/departments/'.$membership->department_id,
                'membership:'.$membership->id.':decision:'.$data['status']
            );
            Hub::log($user, 'membership.'.$data['status'], 'membership', $membership->id);

            return $membership;
        });
    }

    public function remove(Request $request, Membership $membership)
    {
        $user = $request->user();
        Hub::canManage($user, 'members', $membership->department_id);

        return DB::transaction(function () use ($user, $membership) {
            $membership = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            abort_unless($membership->status === 'approved', 422, 'Only approved members can be removed.');
            $membership->update(['status' => 'removed', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            $membership->load(['user', 'department']);
            Hub::notify($membership->user, 'membership', 'Department membership removed',
                'Your approved membership in '.$membership->department->name.' was removed.',
                '/departments/'.$membership->department_id,
                'membership:'.$membership->id.':removed');
            Hub::log($user, 'membership.removed', 'membership', $membership->id);

            return ['message' => 'Member removed and notified.'];
        });
    }
}
