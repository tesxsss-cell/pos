<?php

namespace App\Http\Controllers;

use App\Enums\StockRequestStatus;
use App\Models\Product;
use App\Models\StockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * HF-03 Request stok: cabang mengajukan kebutuhan barang ke pusat,
 * admin/pemilik menyetujui atau menolak.
 */
class StockRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('stock-requests.index', [
            'requests' => StockRequest::query()
                ->with(['branch', 'requester', 'items'])
                ->when(! $user->isCentral(), fn ($q) => $q->where('branch_id', $user->branch_id))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => StockRequestStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()->branch_id, 403, 'Akun Anda belum ditempatkan pada cabang mana pun.');

        return view('stock-requests.form', [
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'sku', 'name', 'unit']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        $stockRequest = DB::transaction(function () use ($data, $user) {
            $request = StockRequest::create([
                'code' => 'RQ-'.now()->format('ymd').'-'.str_pad((string) (StockRequest::whereDate('created_at', now()->toDateString())->count() + 1), 4, '0', STR_PAD_LEFT),
                'branch_id' => $user->branch_id,
                'requested_by' => $user->id,
                'status' => StockRequestStatus::Menunggu,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $request->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity_requested'],
                ]);
            }

            return $request;
        });

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('status', "Request stok {$stockRequest->code} terkirim ke pusat.");
    }

    public function show(Request $request, StockRequest $stockRequest): View
    {
        $this->guard($request, $stockRequest);

        return view('stock-requests.show', [
            'stockRequest' => $stockRequest->load(['items.product', 'branch', 'requester', 'responder', 'transfers']),
        ]);
    }

    /** Persetujuan pusat: jumlah yang disetujui boleh lebih kecil dari permintaan. */
    public function approve(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        abort_if(! $request->user()->isCentral(), 403, 'Hanya admin atau pemilik yang dapat menyetujui request stok.');

        if (! $stockRequest->isPending()) {
            return back()->withErrors(['status' => 'Request stok ini sudah diproses.']);
        }

        $data = $request->validate([
            'approved' => ['required', 'array'],
            'approved.*' => ['required', 'integer', 'min:0'],
            'response_note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($stockRequest, $data, $request) {
            foreach ($stockRequest->items as $item) {
                $approved = (int) ($data['approved'][$item->id] ?? $item->quantity_requested);
                $item->update(['quantity_approved' => min($approved, $item->quantity_requested)]);
            }

            $stockRequest->update([
                'status' => StockRequestStatus::Disetujui,
                'responded_by' => $request->user()->id,
                'responded_at' => now(),
                'response_note' => $data['response_note'] ?? null,
            ]);
        });

        return back()->with('status', 'Request stok disetujui. Lanjutkan dengan membuat dokumen mutasi stok.');
    }

    public function reject(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        abort_if(! $request->user()->isCentral(), 403, 'Hanya admin atau pemilik yang dapat menolak request stok.');

        if (! $stockRequest->isPending()) {
            return back()->withErrors(['status' => 'Request stok ini sudah diproses.']);
        }

        $data = $request->validate(['response_note' => ['required', 'string', 'max:255']]);

        $stockRequest->update([
            'status' => StockRequestStatus::Ditolak,
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
            'response_note' => $data['response_note'],
        ]);

        return back()->with('status', 'Request stok ditolak.');
    }

    private function guard(Request $request, StockRequest $stockRequest): void
    {
        $user = $request->user();

        abort_if(! $user->isCentral() && $user->branch_id !== $stockRequest->branch_id, 403, 'Request stok ini bukan milik cabang Anda.');
    }
}
