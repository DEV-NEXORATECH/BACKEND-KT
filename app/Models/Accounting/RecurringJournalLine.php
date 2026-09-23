<?php
namespace App\Models\Accounting;use Illuminate\Database\Eloquent\Model;class RecurringJournalLine extends Model{protected $fillable=['recurring_journal_id','account_id','description','debit','credit','line_order'];protected $casts=['debit'=>'decimal:2','credit'=>'decimal:2'];}
