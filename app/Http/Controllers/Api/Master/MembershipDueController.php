<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\MembershipDue;
use Illuminate\Http\Request;

class MembershipDueController extends BaseMasterController
{
    protected string $modelClass = MembershipDue::class;
    protected string $resourceClass = '';
    protected string $storeRequestClass = '';
    protected string $updateRequestClass = '';
    protected array $searchableColumns = ['reference', 'status', 'payment_reference'];
    protected array $defaultWith = ['member:id,code,name', 'fiscalYear:id,year,name'];

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'reference' => ['required', 'string', 'max:60', 'unique:membership_dues,reference'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['nullable', 'in:draft,issued,paid,waived,cancelled'],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['created_by'] = $request->user()?->id;
        $due = MembershipDue::create($data);
        return response()->json(['success' => true, 'data' => $due->load($this->defaultWith)], 201);
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $due = MembershipDue::findOrFail($id);
        $data = $request->validate([
            'customer_id' => ['sometimes', 'exists:customers,id'],
            'fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'due_date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'in:draft,issued,paid,waived,cancelled'],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'journal_id' => ['nullable', 'exists:journals,id'],
            'notes' => ['nullable', 'string'],
        ]);
        if (($data['status'] ?? null) === 'paid' && empty($data['paid_at'])) $data['paid_at'] = now()->toDateString();
        $data['updated_by'] = $request->user()?->id;
        $due->update($data);
        return response()->json(['success' => true, 'data' => $due->fresh()->load($this->defaultWith)]);
    }
}
