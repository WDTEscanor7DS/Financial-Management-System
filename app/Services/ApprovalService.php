<?php

namespace App\Services;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function createRequest(string $workflowCode, string $sourceModule, ?int $sourceId, string $description, int $userId): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::where('code', $workflowCode)->where('is_active', true)->firstOrFail();
        $steps = $workflow->steps;

        if ($steps->isEmpty()) {
            throw ValidationException::withMessages([
                'workflow' => 'This approval workflow has no steps configured.',
            ]);
        }

        return DB::transaction(function () use ($workflow, $steps, $sourceModule, $sourceId, $description, $userId) {
            $request = ApprovalRequest::create([
                'approval_workflow_id' => $workflow->id,
                'source_module' => $sourceModule,
                'source_id' => $sourceId,
                'description' => $description,
                'current_sequence' => 1,
                'status' => 'Pending',
                'requested_by' => $userId,
            ]);

            foreach ($steps as $step) {
                ApprovalAction::create([
                    'approval_request_id' => $request->id,
                    'approval_workflow_step_id' => $step->id,
                    'status' => 'Pending',
                ]);
            }

            AuditService::log('Submitted Approval Request', 'Approval Engine', (string) $request->id);

            return $request->load('actions.step', 'workflow');
        });
    }

    public function actOnStep(ApprovalRequest $request, string $decision, ?string $remarks, User $actor): ApprovalRequest
    {
        if ($request->status !== 'Pending') {
            throw ValidationException::withMessages([
                'status' => 'This request has already been finalized.',
            ]);
        }

        $currentAction = $request->actions()
            ->whereHas('step', fn ($q) => $q->where('sequence', $request->current_sequence))
            ->first();

        if (!$currentAction) {
            throw ValidationException::withMessages([
                'step' => 'No pending step found for this request.',
            ]);
        }

        if (!$actor->can($currentAction->step->required_permission)) {
            throw ValidationException::withMessages([
                'permission' => 'You are not authorized to act on this step.',
            ]);
        }

        if (!in_array($decision, ['Approved', 'Rejected'])) {
            throw ValidationException::withMessages([
                'decision' => 'Decision must be Approved or Rejected.',
            ]);
        }

        return DB::transaction(function () use ($request, $currentAction, $decision, $remarks, $actor) {
            $currentAction->update([
                'status' => $decision,
                'acted_by' => $actor->id,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            if ($decision === 'Rejected') {
                $request->update(['status' => 'Rejected']);
            } else {
                $totalSteps = $request->workflow->steps()->count();

                if ($request->current_sequence >= $totalSteps) {
                    $request->update(['status' => 'Approved']);
                } else {
                    $request->update(['current_sequence' => $request->current_sequence + 1]);
                }
            }

            AuditService::log($decision . ' Approval Step', 'Approval Engine', (string) $request->id);

            return $request->fresh()->load('actions.step', 'workflow');
        });
    }
}