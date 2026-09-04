<?php

namespace App\Services;

use App\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function submit(array $data, int $requesterId): ProcurementRequest
    {
        return DB::transaction(function () use ($data, $requesterId) {
            $request = ProcurementRequest::create([
                ...$data,
                'requester_id' => $requesterId,
                'date_submitted' => now()->toDateString(),
                'status' => 'Pending Review', // never accepted from the client -- see below
            ]);

            AuditService::log('Submitted Procurement Request', 'Procurement & Requests', (string) $request->id);
            NotificationService::push(sprintf('New procurement request submitted: PR-%05d.', $request->id));

            return $request;
        });
    }

    /**
     * Section 14/32: the controller for this method is gated behind the
     * approve_procurement_request / reject_procurement_request permissions
     * (Employee's role has neither), and the status machine below is the
     * second, independent line of defence -- even a caller that somehow
     * reached this method cannot set an illegal status, because
     * canTransitionTo() only allows Pending Review -> Approved|Rejected.
     */
    public function review(int $requestId, string $decision, int $reviewerId, ?string $remarks): ProcurementRequest
    {
        $target = $decision === 'approve' ? 'Approved' : 'Rejected';

        return DB::transaction(function () use ($requestId, $target, $reviewerId, $remarks) {
            /** @var ProcurementRequest $request */
            $request = ProcurementRequest::query()->lockForUpdate()->findOrFail($requestId);

            if (! $request->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move this request from {$request->status} to {$target}.",
                ]);
            }

            $request->update([
                'status' => $target,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => now(),
                'remarks' => $remarks,
            ]);

            AuditService::log(
                $decision === 'approve' ? 'Approved Request' : 'Rejected Request',
                'Procurement & Requests',
                (string) $request->id
            );
            NotificationService::push(sprintf(
                'Procurement request PR-%05d was %s.',
                $request->id,
                strtolower($target)
            ));

            return $request->refresh();
        });
    }

    public function advance(int $requestId, string $target): ProcurementRequest
    {
        return DB::transaction(function () use ($requestId, $target) {
            /** @var ProcurementRequest $request */
            $request = ProcurementRequest::query()->lockForUpdate()->findOrFail($requestId);

            if (! $request->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move this request from {$request->status} to {$target}.",
                ]);
            }

            $request->update(['status' => $target]);
            AuditService::log('Updated Request Status to '.$target, 'Procurement & Requests', (string) $request->id);

            return $request->refresh();
        });
    }

    public function delete(ProcurementRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $id = $request->id;
            $request->delete();
            AuditService::log('Deleted Procurement Request', 'Procurement & Requests', (string) $id);
        });
    }
}
