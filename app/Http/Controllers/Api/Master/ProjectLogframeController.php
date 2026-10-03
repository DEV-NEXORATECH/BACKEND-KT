<?php
namespace App\Http\Controllers\Api\Master;
use App\Http\Controllers\Controller;
use App\Models\Master\ProjectLogframe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
class ProjectLogframeController extends Controller {
 public function index(Request $r){return response()->json(['success'=>true,'data'=>ProjectLogframe::with('project:id,code,name')->when($r->filled('project_id'),fn($q)=>$q->where('project_id',$r->integer('project_id')))->latest()->get()]);}
 public function store(Request $r){$d=$r->validate(['project_id'=>'required|exists:projects,id','level'=>'required|in:impact,outcome,output,activity','code'=>'required|string|max:50','description'=>'required|string','indicator'=>'nullable|string|max:255','baseline'=>'nullable|numeric','target'=>'nullable|numeric','actual'=>'nullable|numeric','unit'=>'nullable|string|max:50','period_start'=>'nullable|date','period_end'=>'nullable|date']);return response()->json(['success'=>true,'data'=>ProjectLogframe::create($d)],201);}
 public function update(Request $r,ProjectLogframe $projectLogframe){$projectLogframe->update($r->validate(['level'=>'sometimes|in:impact,outcome,output,activity','description'=>'sometimes|string','indicator'=>'nullable|string','baseline'=>'nullable|numeric','target'=>'nullable|numeric','actual'=>'nullable|numeric','unit'=>'nullable|string|max:50','period_start'=>'nullable|date','period_end'=>'nullable|date']));return response()->json(['success'=>true,'data'=>$projectLogframe]);}
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
