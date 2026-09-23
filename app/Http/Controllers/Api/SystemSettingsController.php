<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\AuditLog;
use App\Models\Master\Currency;
use App\Models\Master\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingsController extends Controller
{
    private const POLICY_DEFAULTS = [
        'enable_multi_level_approval' => true,
        'require_receipt_for_expense' => true,
        'auto_escalate_days' => 3,
        'hard_lock_closed_periods' => true,
        'enable_over_budget_blocking' => true,
    ];

    private const NOTIFICATION_DEFAULTS = [
        'email_pending_approval' => true,
        'email_over_budget_alert' => true,
        'email_tax_deadline' => true,
        'digest_frequency' => 'daily',
    ];

    public function show(): JsonResponse
    {
        $organization = Organization::query()->with('baseCurrency:id,code,name')->where('is_active', true)->orderBy('id')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'organization' => $organization,
                'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
                'policy' => $this->value('approval_budget_policy', self::POLICY_DEFAULTS),
                'notifications' => $this->value('notification_preferences', self::NOTIFICATION_DEFAULTS),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization' => ['nullable', 'array'],
            'organization.name' => ['required_with:organization', 'string', 'max:150'],
            'organization.legal_name' => ['nullable', 'string', 'max:150'],
            'organization.npwp' => ['nullable', 'string', 'max:50'],
            'organization.address' => ['nullable', 'string'],
            'organization.base_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'organization.fiscal_year_start_month' => ['nullable', 'integer', 'between:1,12'],
            'policy' => ['nullable', 'array'],
            'policy.enable_multi_level_approval' => ['required_with:policy', 'boolean'],
            'policy.require_receipt_for_expense' => ['required_with:policy', 'boolean'],
            'policy.auto_escalate_days' => ['required_with:policy', 'integer', 'min:1', 'max:90'],
            'policy.hard_lock_closed_periods' => ['required_with:policy', 'boolean'],
            'policy.enable_over_budget_blocking' => ['required_with:policy', 'boolean'],
            'notifications' => ['nullable', 'array'],
            'notifications.email_pending_approval' => ['required_with:notifications', 'boolean'],
            'notifications.email_over_budget_alert' => ['required_with:notifications', 'boolean'],
            'notifications.email_tax_deadline' => ['required_with:notifications', 'boolean'],
            'notifications.digest_frequency' => ['required_with:notifications', 'in:immediate,daily,weekly'],
        ]);

        DB::transaction(function () use ($data, $request) {
            if (isset($data['organization'])) {
                $organization = Organization::query()->where('is_active', true)->orderBy('id')->firstOrFail();
                $before = $organization->only(['name', 'legal_name', 'npwp', 'address', 'base_currency_id', 'fiscal_year_start_month']);
                $organization->update([...$data['organization'], 'updated_by' => $request->user()->id]);
                $this->audit($request, 'UPDATE_ORGANIZATION_SETTINGS', Organization::class, $organization->id, $before, $organization->fresh()->only(array_keys($before)));
            }

            foreach (['policy' => 'approval_budget_policy', 'notifications' => 'notification_preferences'] as $input => $key) {
                if (! isset($data[$input])) continue;
                $setting = ApplicationSetting::query()->firstOrNew(['key' => $key]);
                $before = $setting->exists ? $setting->value : null;
                $setting->fill(['value' => $data[$input], 'updated_by' => $request->user()->id])->save();
                $this->audit($request, 'UPDATE_SYSTEM_SETTINGS', ApplicationSetting::class, $setting->id, $before, $data[$input]);
            }
        });

        return response()->json(['success' => true, 'message' => 'System settings berhasil disimpan.']);
    }

    private function value(string $key, array $defaults): array
    {
        $setting = ApplicationSetting::query()->where('key', $key)->first();

        return array_replace($defaults, $setting?->value ?? []);
    }

    private function audit(Request $request, string $action, string $type, int $id, ?array $before, ?array $after): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'module' => 'settings', 'platform' => strtolower($request->header('X-Client-Platform', 'web')), 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'previous_values' => $before, 'new_values' => $after, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
