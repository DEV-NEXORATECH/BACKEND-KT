<?php
namespace App\Models\Accounting;use Illuminate\Database\Eloquent\Model;class RecurringJournal extends Model{protected $fillable=['name','frequency','next_run','ends_at','description','is_active'];protected $casts=['next_run'=>'date','ends_at'=>'date','is_active'=>'boolean'];public function lines(){return $this->hasMany(RecurringJournalLine::class);}}
