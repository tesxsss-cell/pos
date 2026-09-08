<?php

namespace App\Enums;

// HF-03 Kartu stok: jenis pergerakan persediaan.
enum StockMovementType: string
{
    case PembelianMasuk = 'pembelian_masuk';
    case TransferKeluar = 'transfer_keluar';
    case TransferMasuk = 'transfer_masuk';
    case PenjualanKeluar = 'penjualan_keluar';
    case PembatalanPenjualan = 'pembatalan_penjualan';
    case PenyesuaianMasuk = 'penyesuaian_masuk';
    case PenyesuaianKeluar = 'penyesuaian_keluar';

    public function label(): string
    {
        return match ($this) {
            self::PembelianMasuk => 'Pembelian Masuk',
            self::TransferKeluar => 'Transfer Keluar',
            self::TransferMasuk => 'Transfer Masuk',
            self::PenjualanKeluar => 'Penjualan Keluar',
            self::PembatalanPenjualan => 'Pembatalan Penjualan',
            self::PenyesuaianMasuk => 'Penyesuaian Masuk',
            self::PenyesuaianKeluar => 'Penyesuaian Keluar',
        };
    }

    public function isIncoming(): bool
    {
        return in_array($this, [
            self::PembelianMasuk, self::TransferMasuk,
            self::PembatalanPenjualan, self::PenyesuaianMasuk,
        ], true);
    }
}
