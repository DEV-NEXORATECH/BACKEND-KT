<?php
namespace App\Http\Requests\Master; use Illuminate\Foundation\Http\FormRequest;
class UpdateAccountCategoryRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['code'=>'required|string|max:50|unique:account_categories,code,'.$this->route('id').',id','name'=>'required|string|max:150','account_type'=>'required|in:asset,liability,equity,revenue,expense','description'=>'nullable|string','is_active'=>'boolean']; } }
