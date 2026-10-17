<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use App\Models\Master\BankAccount;
use App\Http\Requests\Master\StoreBankAccountRequest;
use App\Http\Requests\Master\UpdateBankAccountRequest;
use App\Http\Resources\Master\BankAccountResource;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankAccountController extends BaseMasterController
{
    protected string $modelClass = BankAccount::class;
    protected string $resourceClass = BankAccountResource::class;
    protected string $storeRequestClass = StoreBankAccountRequest::class;
    protected string $updateRequestClass = UpdateBankAccountRequest::class;
    protected array $searchableColumns = ['bank_name', 'account_number', 'account_name'];
    protected array $defaultWith = ['organization', 'currency', 'glAccount', 'fiscalYear'];

    public function postOpeningBalance(Request $request, BankAccount $bankAccount): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'offset_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'opening_balance_date' => ['required', 'date'],
        ]);

        if (! $bankAccount->gl_account_id) return response()->json(['success' => false, 'message' => 'Bank account harus memiliki COA terlebih dahulu.'], 422);
        if ((float) $bankAccount->opening_balance <= 0) return response()->json(['success' => false, 'message' => 'Opening balance harus lebih besar dari nol.'], 422);
        app(AccountingPeriodService::class)->ensureOpen($data['opening_balance_date'], 'opening_balance_date');

        $existing = Journal::query()->where('source_type', 'bank_account_opening_balance')->where('source_id', $bankAccount->id)->first();
        if ($existing) return response()->json(['success' => true, 'message' => 'Opening balance sudah diposting sebelumnya.', 'data' => $existing->load('lines')]);

        $journal = DB::transaction(function () use ($request, $data, $bankAccount) {
            $journal = Journal::create([
                'fiscal_year_id' => $data['fiscal_year_id'], 'journal_number' => 'OB-'.$bankAccount->id.'-'.now()->format('YmdHis'),
                'journal_date' => $data['opening_balance_date'], 'journal_type' => 'manual', 'reference' => 'OPENING-BALANCE-'.$bankAccount->account_number,
                'description' => 'Opening balance '.$bankAccount->bank_name.' '.$bankAccount->account_number, 'currency_id' => $bankAccount->currency_id,
                'exchange_rate' => 1, 'original_amount' => $bankAccount->opening_balance, 'converted_amount' => $bankAccount->opening_balance,
                'rate_source' => 'opening_balance_snapshot', 'rate_date' => $data['opening_balance_date'], 'status' => 'posted',
                'posted_by' => $request->user()?->id, 'posted_at' => now(), 'source_type' => 'bank_account_opening_balance',
                'source_id' => $bankAccount->id, 'created_by' => $request->user()?->id,
            ]);
            JournalLine::create(['journal_id' => $journal->id, 'account_id' => $bankAccount->gl_account_id, 'line_description' => 'Opening bank balance', 'debit' => $bankAccount->opening_balance, 'credit' => 0, 'line_order' => 1]);
            JournalLine::create(['journal_id' => $journal->id, 'account_id' => $data['offset_account_id'], 'line_description' => 'Opening balance offset', 'debit' => 0, 'credit' => $bankAccount->opening_balance, 'line_order' => 2]);
            return $journal;
        });

        $bankAccount->update(['opening_balance_journal_id' => $journal->id, 'fiscal_year_id' => $data['fiscal_year_id'], 'opening_balance_date' => $data['opening_balance_date']]);
        return response()->json(['success' => true, 'message' => 'Opening balance berhasil diposting ke jurnal.', 'data' => $journal->load('lines')], 201);
    }
}
