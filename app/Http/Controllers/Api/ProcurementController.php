<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcurementRequestStoreRequest;
use App\Http\Requests\ProcurementReviewRequest;
use App\Models\ProcurementRequest as ProcurementRequestModel;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcurementController extends Controller
{
    public function __construct(private readonly ProcurementService $service) {}

    /**
     * Mirrors visibleRequests() from the existing js/procurement.js: a role
     * that cannot approve requests (Employee, College Administrator) only
     * ever sees its own submissions. This is enforced here -- server-side
     * -- rather than only in the frontend's render logic, since that JS
     * function never actually restricted what the old localStorage layer
     * would return to a curious user poking at the console.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $canApprove = $user->can('approve_procurement_request');

        $query = ProcurementRequestModel::with(['requester', 'reviewer', 'department']);

        if (! $canApprove) {
            $query->where('requester_id', $user->id);
        }

        $requests = $query
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('request_type'), fn ($q, $v) => $q->where('request_type', $v))
            ->orderByDesc('date_submitted')
            ->get();

        return response()->json(['data' => $requests->map($this->transform(...))]);
    }

    public function store(ProcurementRequestStoreRequest $request)
    {
        $procurementRequest = $this->service->submit($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($procurementRequest)], 201);
    }

    public function review(ProcurementReviewRequest $request, ProcurementRequestModel $procurementRequest)
    {
        $ability = $request->input('decision') === 'approve' ? 'approve_procurement_request' : 'reject_procurement_request';

        if (! $request->user()->can($ability)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        $procurementRequest = $this->service->review(
            $procurementRequest->id,
            $request->input('decision'),
            $request->user()->id,
            $request->input('remarks')
        );

        return response()->json(['data' => $this->transform($procurementRequest)]);
    }

    public function advance(Request $request, ProcurementRequestModel $procurementRequest)
    {
        $request->validate(['status' => ['required', Rule::in(['Procurement Processing', 'Completed'])]]);

        if (! $request->user()->can('advance_procurement_request')) {
            abort(403, 'You do not have permission to perform this action.');
        }

        $procurementRequest = $this->service->advance($procurementRequest->id, $request->input('status'));

        return response()->json(['data' => $this->transform($procurementRequest)]);
    }

    public function destroy(ProcurementRequestModel $procurementRequest)
    {
        $this->service->delete($procurementRequest);

        return response()->json(status: 204);
    }

    private function transform(ProcurementRequestModel $p): array
    {
        return [
            'id' => sprintf('PR-%05d', $p->id),
            'raw_id' => $p->id,
            'requester' => $p->requester->name,
            'requesterId' => $p->requester_id,
            'department' => $p->department->name,
            'requestType' => $p->request_type,
            'description' => $p->description,
            'quantity' => $p->quantity,
            'estimatedCost' => (float) $p->estimated_cost,
            'priority' => $p->priority,
            'dateSubmitted' => $p->date_submitted->toDateString(),
            'status' => $p->status,
            'reviewer' => $p->reviewer?->name,
            'remarks' => $p->remarks,
        ];
    }
}
