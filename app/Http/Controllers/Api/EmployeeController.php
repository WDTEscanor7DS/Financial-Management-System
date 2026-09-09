<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::with('department')
            ->orderBy('full_name')
            ->get()
            ->map($this->transform(...));

        return response()->json(['data' => $employees]);
    }

    public function store(EmployeeRequest $request)
    {
        $employee = Employee::create($request->validated() + ['status' => 'Active']);

        return response()->json(['data' => $this->transform($employee->load('department'))], 201);
    }

    private function transform(Employee $e): array
    {
        return [
            'id' => $e->id,
            'employeeNo' => $e->employee_no,
            'fullName' => $e->full_name,
            'department' => $e->department?->name,
            'position' => $e->position,
            'employmentType' => $e->employment_type,
            'monthlyRate' => (float) $e->monthly_rate,
            'status' => $e->status,
            'hireDate' => $e->hire_date->toDateString(),
        ];
    }
}