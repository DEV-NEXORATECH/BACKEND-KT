<?php
namespace App\Http\Controllers\Api\Master;
use App\Http\Controllers\Controller;
use App\Models\Master\ProjectLogframe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
class ProjectLogframeController extends Controller {
 public function index(Request $r){
  $query=ProjectLogframe::with('project:id,code,name')
   ->when($r->filled('project_id'),fn($q)=>$q->where('project_id',$r->integer('project_id')))
   ->when($r->filled('fiscal_year_id'),fn($q)=>$q->whereHas('project.fiscalYears',fn($yearQuery)=>$yearQuery->whereKey($r->integer('fiscal_year_id'))));
  return response()->json(['success'=>true,'data'=>$query->latest()->get()]);
 }
 public function store(Request $r){$d=$r->validate(['project_id'=>'required|exists:projects,id','fiscal_year_id'=>'nullable|integer|exists:fiscal_years,id','level'=>'required|in:impact,outcome,output,activity','code'=>'nullable|string|max:50','description'=>'required|string','indicator'=>'nullable|string|max:255','baseline'=>'nullable|numeric','target'=>'nullable|numeric','actual'=>'nullable|numeric','unit'=>'nullable|string|max:50','period_start'=>'nullable|date','period_end'=>'nullable|date']);$d['code']=$d['code']??$this->nextCode($d['project_id'],$d['level']);return response()->json(['success'=>true,'data'=>ProjectLogframe::create($d)],201);}
 private function nextCode(int $projectId,string $level): string { $prefix=['impact'=>'IMP','outcome'=>'OUT','output'=>'OUTP','activity'=>'ACT'][$level]??'FW'; $used=ProjectLogframe::query()->where('project_id',$projectId)->where('level',$level)->pluck('code'); $max=0; foreach($used as $code){if(preg_match('/^'.preg_quote($prefix,'/').'-(\d+)$/i',(string)$code,$match)){$max=max($max,(int)$match[1]);}} return $prefix.'-'.str_pad((string)($max+1),2,'0',STR_PAD_LEFT); }
 public function generateActivities(Request $r){
  $d=$r->validate(['project_id'=>'required|exists:projects,id']);
  $created=DB::transaction(function() use($d){
   $outputs=ProjectLogframe::query()->where('project_id',$d['project_id'])->where('level','output')->orderBy('id')->get();
   $saved=[];
   foreach($outputs as $output){
    $prefix=strtoupper($output->code).'-ACT-';
    if(ProjectLogframe::query()->where('project_id',$d['project_id'])->where('level','activity')->where('code','like',$prefix.'%')->exists()) continue;
    $saved[]=ProjectLogframe::create([
     'project_id'=>$d['project_id'],
     'level'=>'activity',
     'code'=>$prefix.'01',
     'description'=>'Aktivitas pelaksanaan: '.$output->description,
     'indicator'=>$output->indicator,
     'baseline'=>$output->baseline,
     'target'=>$output->target,
     'period_start'=>$output->period_start,
     'period_end'=>$output->period_end,
    ]);
   }
   return collect($saved);
  });
  return response()->json(['success'=>true,'data'=>$created,'created'=>$created->count(),'message'=>$created->count().' activity generated.']);
 }
 public function update(Request $r,ProjectLogframe $projectLogframe){$projectLogframe->update($r->validate(['fiscal_year_id'=>'nullable|integer|exists:fiscal_years,id','level'=>'sometimes|in:impact,outcome,output,activity','description'=>'sometimes|string','indicator'=>'nullable|string','baseline'=>'nullable|numeric','target'=>'nullable|numeric','actual'=>'nullable|numeric','unit'=>'nullable|string|max:50','period_start'=>'nullable|date','period_end'=>'nullable|date']));return response()->json(['success'=>true,'data'=>$projectLogframe]);}
 public function destroy(ProjectLogframe $projectLogframe){$projectLogframe->delete();return response()->json(['success'=>true]);}
 public function import(Request $r){
  $r->validate(['project_id'=>'required|exists:projects,id','file'=>'required|file|mimes:xlsx,xls,csv|max:10240']);
  $workbook=IOFactory::load($r->file('file')->getRealPath());
  $sheet=$workbook->getSheetByName('FrameworkLogframe') ?: $workbook->getActiveSheet();
  $rows=$sheet->toArray(null,true,true,true); $created=[]; $level=null; $counters=[]; $errors=[];
  foreach($rows as $i=>$row){
   $first=trim((string)($row['A']??'')); $second=trim((string)($row['B']??''));
   $marker=strtolower($first);
   if(in_array($marker,['impact','outcome','output','activity'],true)){ $level=$marker; $counters[$level]=($counters[$level]??0)+1; continue; }
   if(!$level || ($first==='' && $second==='')) continue;
   if(in_array(strtolower(trim((string)($row['C']??''))),['achieved','planned','source (where evidence will be sought)'],true)) continue;
   $description=$first!==''?$first:$second; $indicator=$first!==''?$second:'';
   if(mb_strlen($description)<3){$errors[]="Row {$i}: description is too short."; continue;}
   $code=strtoupper($level).'-'.($counters[$level]??1); $counters[$level]=($counters[$level]??1)+1;
   $created[]=['project_id'=>$r->integer('project_id'),'level'=>$level,'code'=>$code,'description'=>$description,'indicator'=>$indicator,'baseline'=>is_numeric($row['D']??null)?$row['D']:null,'target'=>is_numeric($row['E']??null)?$row['E']:null];
  }
  if($errors) throw ValidationException::withMessages(['file'=>$errors]);
  $saved=DB::transaction(fn()=>collect($created)->map(fn($data)=>ProjectLogframe::create($data))->values());
  return response()->json(['success'=>true,'data'=>$saved,'imported'=>$saved->count()]);
 }
}
