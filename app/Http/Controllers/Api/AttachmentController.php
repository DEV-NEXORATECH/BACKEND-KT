<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Asset\FixedAsset;
use App\Models\Expense\ExpenseRequest;
use App\Models\Finance\TaxTransaction;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Services\Rbac\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Upload an attachment for any target ERP transaction module.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'module' => ['required', 'string', 'in:expense,ap,pr,po,grn,invoice,journal,tax,asset'],
            'entity_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,docx,xlsx', 'max:10240'],
        ]);

        $module = $request->input('module');
        $entityId = (int) $request->input('entity_id');
        $file = $request->file('file');

        $entity = $this->findEntity($module, $entityId);
        $this->authorizeAttachment($request, $module, $entity, true);

        $path = $file->store("secure_attachments/{$module}", 'local');
        $originalName = $file->getClientOriginalName();

        $attachmentInfo = [
            'name' => $originalName,
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ];

        $currentAttachments = is_array($entity->attachments) ? $entity->attachments : [];
        $currentAttachments[] = $path; // standard path list
        $entity->update(['attachments' => $currentAttachments]);

        return response()->json([
            'success' => true,
            'message' => 'File lampiran berhasil diunggah.',
            'data' => [
                'module' => $module,
                'entity_id' => $entityId,
                'attachment' => $attachmentInfo,
                'total_attachments' => count($currentAttachments),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Download or view an attachment securely.
     */
    public function download(Request $request, string $module, int $id, int $index): StreamedResponse|JsonResponse
    {
        $entity = $this->findEntity($module, $id);
        $this->authorizeAttachment($request, $module, $entity);

        $attachments = is_array($entity->attachments) ? $entity->attachments : [];

        if (! isset($attachments[$index])) {
            throw ValidationException::withMessages(['attachment' => 'Lampiran tidak ditemukan pada indeks tersebut.']);
        }

        $filePath = $attachments[$index];

        if (! Storage::disk('local')->exists($filePath)) {
            // Check if legacy relative path string without disk prefix
            if (file_exists(storage_path('app/'.$filePath))) {
                return response()->download(storage_path('app/'.$filePath));
            }
            throw ValidationException::withMessages(['file' => 'File fisik tidak ditemukan di server.']);
        }

        return Storage::disk('local')->download($filePath);
    }

    /**
     * Find target model entity by module name.
     */
    private function findEntity(string $module, int $id)
    {
        $modelMap = [
            'expense' => ExpenseRequest::class,
            'ap' => SupplierInvoice::class,
            'pr' => PurchaseRequest::class,
            'po' => PurchaseOrder::class,
            'grn' => GoodsReceipt::class,
            'invoice' => SupplierInvoice::class,
            'journal' => Journal::class,
            'tax' => TaxTransaction::class,
            'asset' => FixedAsset::class,
        ];

        $class = $modelMap[$module] ?? null;
        if (! $class) {
            throw ValidationException::withMessages(['module' => 'Modul transaksi tidak valid.']);
        }

        $entity = $class::find($id);
        if (! $entity) {
            throw ValidationException::withMessages(['entity_id' => 'Data transaksi tidak ditemukan.']);
        }

        return $entity;
    }

    /**
     * Do not let a caller turn an entity ID into a file-read/write primitive.
     * Modules without an attachments column are explicitly rejected until their
     * transaction schema and ownership rule have been implemented.
     */
    private function authorizeAttachment(Request $request, string $module, $entity, bool $upload = false): void
    {
        if (! Schema::hasColumn($entity->getTable(), 'attachments')) {
            throw ValidationException::withMessages([
                'module' => 'Attachment belum didukung untuk modul ini.',
            ]);
        }

        $permissions = match ($module) {
            'expense' => $upload ? ['expense.create', 'expense.approve', 'expense.post', 'expense.pay'] : ['expense.view', 'expense.approve', 'expense.post', 'expense.pay'],
            'pr' => ['procurement.pr.create', 'procurement.pr.update', 'procurement.pr.approve'],
            'po' => ['procurement.po.create', 'procurement.po.approve'],
            'grn' => ['procurement.grn.create', 'procurement.grn.view'],
            'ap', 'invoice' => ['ap.view', 'ap.post', 'ap.pay', 'procurement.invoice.create', 'procurement.invoice.view'],
            'journal' => ['accounting.journal.view', 'accounting.journal.create', 'accounting.journal.update'],
            'tax' => ['tax.view', 'tax.manage'],
            'asset' => ['asset.view', 'asset.create', 'asset.capitalize'],
            default => [],
        };
        if (! $request->user()->hasAnyPermission($permissions)) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak memiliki permission untuk mengakses lampiran modul ini.');
        }

        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            $ownerColumn = match ($module) {
                'expense', 'pr' => 'requester_id',
                'ap', 'invoice', 'po', 'grn', 'journal', 'tax', 'asset' => 'created_by',
                default => null,
            };
            if ($ownerColumn === null || ! isset($entity->{$ownerColumn}) || (int) $entity->{$ownerColumn} !== (int) $request->user()->id) {
                abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengakses lampiran transaksi milik pengguna lain.');
            }
        }
    }
}
