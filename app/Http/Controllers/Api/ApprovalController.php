<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalActionRequest;
use App\Http\Requests\ApprovalRequestStoreRequest;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $service) {}

    public function index(Request $request)
    {
        $requests = ApprovalRequest::with(['workflow', 'actions.step', 'requester'])
            ->orderByDesc('created_at')
            ->get()
            ->map($this->transform(...));

        return response()->json(['data' => $requests]);
    }

    public function store(ApprovalRequestStoreRequest $request)
    {
        $approvalRequest = $this->service->createRequest(
            $request->validated('workflow_code'),
            'Manual',
            null,
            $request->validated('description'),
            $request->user()->id
        );

        return response()->json(['data' => $this->transform($approvalRequest)], 201);
    }

    public function act(ApprovalRequest $approvalRequest, ApprovalActionRequest $request)
    {
        $updated = $this->service->actOnStep(
            $approvalRequest,
            $request->validated('decision'),
            $request->validated('remarks'),
            $request->user()
        );

        return response()->json(['data' => $this->transform($updated)]);
    }

    private function transform(ApprovalRequest $r): array
    {
        return [
            'id' => sprintf('AR-%05d', $r->id),
            'raw_id' => $r->id,
            'workflowName' => $r->workflow->name,
            'sourceModule' => $r->source_module,
            'description' => $r->description,
            'status' => $r->status,
            'currentSequence' => $r->current_sequence,
            'requestedBy' => $r->requester?->name,
            'createdAt' => $r->created_at->toDateTimeString(),
            'steps' => $r->actions->map(fn ($a) => [
                'sequence' => $a->step->sequence,
                'stepName' => $a->step->step_name,
                'requiredPermission' => $a->step->required_permission,
                'status' => $a->status,
                'actedBy' => $a->actor?->name,
                'remarks' => $a->remarks,
                'actedAt' => $a->acted_at?->toDateTimeString(),
            ]),
        ];
    }
}