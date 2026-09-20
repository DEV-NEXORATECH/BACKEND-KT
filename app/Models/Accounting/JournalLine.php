<?php

namespace App\Models\Accounting;

use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Department;
use App\Models\Master\Donor;
use App\Models\Master\Program;
use App\Models\Master\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_id',
        'account_id',
        'donor_id',
        'program_id',
        'project_id',
        'budget_line_id',
        'department_id',
        'line_description',
        'debit',
        'credit',
        'line_order',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
