<?php

namespace App\Http\Controllers;

use App\Enums\StockRequestStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * HF-03 Modul Mutasi Stok: pengiriman barang gudang pusat -> cabang,
 * termasuk penerimaan di cabang. Harga pokok tiap batch terbawa (FIFO).
 */
class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $transfers) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('transfers.index', [
            'transfers' => StockTransfer::query()
                ->with(['fromBranch', 'toBranch', 'items'])
                ->when(! $user->isCentral(), fn ($q) => $q->where('to_branch_id', $user->branch_id))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->isCentral(), 403, 'Hanya pusat yang dapat membuat dokumen mutasi stok.');

        return view('transfers.form', [
            'branches' => Branch::query()->active()->orderBy('type')->orderBy('name')->get(),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'sku', 'name', 'unit']),
            'requests' => StockRequest::query()
                ->with(['branch', 'items.product'])
                ->where('status', StockRequestStatus::Disetujui->value)
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->isCentral(), 403, 'Hanya pusat yang dapat membuat dokumen mutasi stok.');

        $data = $request->validate([
            'from_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id', 'exists:branches,id'],
            'stock_request_id' => ['nullable', 'integer', 'exists:stock_requests,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required_without:stock_request_id', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $transfer = isset($data['stock_request_id'])
                ? $this->transfers->createFromRequest(
                    StockRequest::with('items')->findOrFail($data['stock_request_id']),
                    $data['from_branch_id'],
                    $data['note'] ?? null,
                )
                : $this->transfers->create(
                    $data['from_branch_id'],
                    $data['to_branch_id'],
                    $data['items'],
                    $data['note'] ?? null,
                );
        } catch (Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()])->withInput();
        }

        return redirect()->route('transfers.show', $transfer)
            ->with('status', "Dokumen mutasi {$transfer->code} dibuat. Klik Kirim untuk mengeluarkan stok gudang.");
    }

    public function show(Request $request, StockTransfer $transfer): View
    {
        $this->guard($request, $transfer);

        return view('transfers.show', [
            'transfer' => $transfer->load(['items.product', 'fromBranch', 'toBranch', 'shipper', 'receiver', 'stockRequest']),
        ]);
    }

    /** Pengiriman: stok keluar gudang memakai FIFO. */
    public function ship(Request $request, StockTransfer $transfer): RedirectResponse
    {
        abort_if(! $request->user()->isCentral(), 403, 'Hanya pusat yang dapat mengirim mutasi stok.');

        try {
            $this->transfers->ship($transfer, $request->user()->id);
        } catch (Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "Dokumen {$transfer->code} dikirim. Stok gudang berkurang sesuai FIFO.");
    }

    /** Penerimaan di cabang: lapisan FIFO dibentuk ulang dengan harga pokok yang sama. */
    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->guard($request, $transfer);

        $data = $request->validate([
            'received' => ['nullable', 'array'],
            'received.*' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->transfers->receive($transfer, $request->user()->id, $data['received'] ?? []);
        } catch (Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "Dokumen {$transfer->code} diterima. Stok cabang bertambah.");
    }

    private function guard(Request $request, StockTransfer $transfer): void
    {
        $user = $request->user();

        abort_if(
            ! $user->isCentral() && ! in_array($user->branch_id, [$transfer->from_branch_id, $transfer->to_branch_id], true),
            403,
            'Dokumen mutasi ini bukan milik cabang Anda.',
        );
    }
}
