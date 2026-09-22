@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
    {{-- Semua kartu berada dalam satu kolom yang berada di tengah pada layar komputer/laptop. --}}
    <div class="mx-auto w-full max-w-3xl space-y-4">
            <style>
                /* Scanner kasir menyatu di halaman, tanpa overlay/pop-up dan tanpa animasi. */
                #scanner-kasir .bcs-kotak {
                    min-height: 210px;
                    max-height: 340px;
                    aspect-ratio: 4 / 3;
                    border-radius: 10px;
                }
                #scanner-kasir .bcs-status,
                #scanner-kasir .bcs-aksi { display: none; }
                .kasir-pindai-grid { display: grid; gap: 16px; align-items: start; }
                #kamera-aktifkan, #kamera-nonaktifkan { min-height: 44px; }
                @media (min-width: 768px) {
                    #scanner-kasir .bcs-kotak { min-height: 250px; aspect-ratio: 16 / 9; }
                }
                @media (min-width: 1280px) {
                    .kasir-pindai-grid { grid-template-columns: minmax(0, 1fr) minmax(360px, .95fr); }
                    #scanner-kasir .bcs-kotak { min-height: 230px; max-height: 300px; }
                }
            </style>
            <div class="rounded-xl bg-white p-3 shadow sm:p-4">
                <section class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-3" aria-labelledby="judul-scanner-kasir">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 id="judul-scanner-kasir" class="text-sm font-semibold text-slate-900">Scanner barcode</h2>
                                <p id="status-scanner-kasir" class="text-xs text-slate-500" aria-live="polite">Menyiapkan kamera…</p>
                            </div>
                            <span id="indikator-scanner-kasir" class="rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Nonaktif</span>
                        </div>

                        <div id="scanner-kasir" class="mt-3 w-full overflow-hidden rounded-lg bg-black"></div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" id="kamera-aktifkan"
                                    class="min-h-11 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                Aktifkan kamera
                            </button>
                            <button type="button" id="kamera-nonaktifkan" disabled
                                    class="min-h-11 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50">
                                Nonaktifkan
                            </button>
                        </div>
                        <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                            Kamera mencoba aktif otomatis. Di HP gunakan HTTPS dan izinkan kamera belakang.
                        </p>
                </section>

                {{-- Kolom pencarian disembunyikan; hasil scan kamera/USB tetap diproses lewat elemen ini. --}}
                <input id="cari" type="text" autocomplete="off" class="hidden" aria-hidden="true" tabindex="-1">

                <div id="info-pindai" class="mt-4 hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"></div>
                <div id="hasil" class="mt-3 space-y-2"></div>

                {{-- =========== Keranjang (dapat dilipat) =========== --}}
                <section class="mt-4 border-t border-slate-200 pt-4">
                    <button type="button" id="keranjang-toggle" aria-expanded="false"
                            class="flex w-full items-center justify-between gap-3 text-left">
                        <span class="flex items-center gap-2">
                            <span class="text-base font-semibold text-slate-900">Keranjang</span>
                            <span id="keranjang-jumlah" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">0 barang</span>
                        </span>
                        <span class="flex items-center gap-1 text-xs font-semibold text-brand-700">
                            <span id="keranjang-toggle-teks">Lihat detail</span>
                            <svg id="keranjang-toggle-ikon" class="h-4 w-4 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </button>

                    <div id="keranjang-detail" class="hidden">
                        <div class="mt-3 flex items-center justify-end">
                            <button type="button" id="kosongkan" class="text-xs font-medium text-slate-500 hover:text-red-600">Kosongkan keranjang</button>
                        </div>

                        <p id="kosong" class="mt-2 rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-400">
                            Belum ada barang. Pindai barcode atau cari nama barang di atas.
                        </p>

                        <div id="keranjang" class="mt-3 space-y-2"></div>
                    </div>
                </section>

                {{-- =========== Pembayaran =========== --}}
                <section class="mt-4 border-t border-slate-200 pt-4">
                    <h2 class="text-base font-semibold text-slate-900">Pembayaran</h2>

                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Nama transaksi</label>
                        <input id="pelanggan" type="text"
                               placeholder="Otomatis terisi setelah scan barcode"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-400">Terisi otomatis dari barang pertama yang dipindai, tetapi tetap bisa Anda ketik/ubah sendiri bila scan bermasalah.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600">Metode bayar</label>
                        <select id="metode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($paymentMethods as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Diskon (Rp)</label>
                            <input id="diskon" type="number" min="0" step="0.01" value="0"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Uang dibayar (Rp)</label>
                            <input id="bayar" type="number" min="0" step="0.01" value="0"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600">Catatan</label>
                        <input id="catatan" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <dl class="space-y-1 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd id="v-subtotal" class="font-medium">Rp 0</dd></div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-2"><dt class="text-sm font-medium text-slate-600">Total</dt><dd id="v-total" class="pos-total">Rp 0</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Kembali</dt><dd id="v-kembali" class="font-medium">Rp 0</dd></div>
                    </dl>

                    <button type="button" id="simpan"
                            class="btn-primary-lg">
                        Simpan transaksi
                    </button>

                    <p id="pesan" class="text-xs"></p>
                    </div>
                </section>
            </div>

            {{-- Shift kasir (HF-06) --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Shift kasir</h2>

                @if ($shift)
                    <dl class="mt-2 space-y-1 text-sm text-slate-600">
                        <div class="flex justify-between"><dt>Kas awal</dt><dd>Rp {{ number_format((float) $shift->opening_cash, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt>Kas sistem</dt><dd>Rp {{ number_format((float) $shift->expected_cash, 0, ',', '.') }}</dd></div>
                    </dl>

                    <form method="POST" action="{{ route('pos.shift.close') }}" class="mt-3 space-y-2">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Uang fisik di laci (Rp)</label>
                            <input name="actual_cash" type="number" min="0" step="0.01" required
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Catatan</label>
                            <input name="note" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <button class="w-full rounded-md bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                            Tutup shift
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('pos.shift.open') }}" class="mt-3 space-y-2">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Kas awal (Rp)</label>
                            <input name="opening_cash" type="number" min="0" step="0.01" value="0" required
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <button class="w-full rounded-md bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                            Buka shift
                        </button>
                    </form>
                @endif
            </div>

            {{-- Mode offline --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Mode offline</h2>

                <dl class="mt-2 space-y-1 text-sm text-slate-600">
                    <div class="flex justify-between"><dt>Status jaringan</dt><dd id="status-jaringan" class="font-medium">-</dd></div>
                    <div class="flex justify-between"><dt>Transaksi menunggu</dt><dd id="jumlah-antrian" class="font-medium">0</dd></div>
                    <div class="flex justify-between"><dt>Barcode menunggu</dt><dd id="jumlah-antrian-barcode" class="font-medium">0</dd></div>
                </dl>

                <p id="info-katalog" class="mt-2 text-xs text-slate-500">Katalog offline belum diunduh.</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="kirim-antrian"
                            class="rounded-md bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">
                        Kirim transaksi
                    </button>
                    <button type="button" id="kirim-antrian-barcode"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
                        Kirim barcode
                    </button>
                    <button type="button" id="segarkan-katalog"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                        Segarkan katalog
                    </button>
                </div>
            </div>

            {{-- Transaksi terakhir --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Transaksi terakhir</h2>

                <div class="mt-2 space-y-2">
                    @forelse ($recentSales as $sale)
                        <div class="rounded-lg border border-slate-200 p-2 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $sale->invoice_no }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $sale->sold_at?->format('d/m H:i') }} &middot;
                                        Rp {{ number_format((float) $sale->total, 0, ',', '.') }}
                                        @if (! $sale->isCompleted()) &middot; <span class="text-red-600">dibatalkan</span> @endif
                                    </p>
                                </div>
                                <a href="{{ route('pos.receipt', $sale) }}" target="_blank"
                                   class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                    Struk
                                </a>
                            </div>

                            @if ($sale->isCompleted())
                                <form method="POST" action="{{ route('pos.void', $sale) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input name="reason" type="text" required placeholder="Alasan pembatalan"
                                           class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                    <button class="whitespace-nowrap rounded-md bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-500">
                                        Batalkan
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Belum ada transaksi pada shift ini.</p>
                    @endforelse
                </div>
            </div>
    </div>

    <script>
        window.POS_OFFLINE_CONFIG = {
            katalog: '{{ route('pos.catalog') }}',
            sinkronisasi: '{{ route('pos.sync') }}',
            sinkronisasiBarcode: '{{ route('pos.sync-barcode') }}',
            csrf: '{{ csrf_token() }}',
        };
    </script>
    {{--
        Scanner disatukan langsung di halaman Blade seperti index.html acuan:
        - tidak ada animasi/garis scan;
        - BarcodeDetector dipakai bila tersedia;
        - dekoder EAN/UPC lokal tetap berjalan tanpa internet;
        - dua pembacaan identik + checksum menjaga akurasi.
    --}}
    <script>
/**
 * offline-barcode.js
 * Dekoder barcode EAN-13 / EAN-8 / UPC-A murni JavaScript (tanpa dependensi, jalan offline).
 * Port algoritma scanline UPC/EAN ala ZXing. Sumber: berkas index.html contoh.
 *
 * Menyediakan window.OfflineBarcode dengan:
 *   decode(data, width, height) -> { code, format } | null   (dipakai pemindai kamera)
 *   decodeAny / decodeRow / decodeEAN13 / decodeEAN8 / reversed
 *
 * Dipakai bersama BarcodeDetector bawaan browser (bila ada) oleh barcode-scanner.js.
 */
(function(root){
  "use strict";
  // Pure-JS EAN-13 / EAN-8 / UPC-A decoder (offline, no dependencies).
  // Port of the classic ZXing UPC/EAN scanline algorithm.
  var L=[[3,2,1,1],[2,2,2,1],[2,1,2,2],[1,4,1,1],[1,1,3,2],[1,2,3,1],[1,1,1,4],[1,3,1,2],[1,2,1,3],[3,1,1,2]];
  var LG=L.concat(L.map(function(p){return p.slice().reverse();}));
  var START=[1,1,1], MID=[1,1,1,1,1];
  var FD=[0x00,0x0B,0x0D,0x0E,0x13,0x19,0x1C,0x15,0x16,0x1A];
  var MAX_AVG=0.48, MAX_IND=0.7;

  function variance(c,p){
    var n=c.length,total=0,plen=0,i;
    for(i=0;i<n;i++){total+=c[i];plen+=p[i];}
    if(total<plen) return Infinity;
    var unit=total/plen, maxi=MAX_IND*unit, v=0;
    for(i=0;i<n;i++){var cc=c[i],sc=p[i]*unit,d=cc>sc?cc-sc:sc-cc; if(d>maxi) return Infinity; v+=d*d;}
    return v/total;
  }
  function bestDigit(c,pats){
    var best=Infinity,bm=-1;
    for(var d=0;d<pats.length;d++){var v=variance(c,pats[d]); if(v<best){best=v;bm=d;}}
    return bm;
  }
  function record(bits,start,counters){
    var num=counters.length,i;
    for(i=0;i<num;i++) counters[i]=0;
    var end=bits.length;
    if(start>=end) return -1;
    var white=bits[start]===0, count=0, x=start;
    for(;x<end;x++){
      if((bits[x]===0)===white){ counters[count]++; }
      else { count++; if(count===num) break; counters[count]=1; white=!white; }
    }
    if(count===num) return x;
    if(count===num-1 && x===end && counters[num-1]>0) return x;
    return -1;
  }
  function findGuard(bits,rowOffset,whiteFirst,pattern){
    var plen=pattern.length, counters=new Array(plen), width=bits.length, i;
    for(i=0;i<plen;i++) counters[i]=0;
    var x=rowOffset;
    while(x<width && ((bits[x]===0)!==whiteFirst)) x++;
    var pos=0, pstart=x, white=whiteFirst;
    for(; x<width; x++){
      if((bits[x]===0)===white){ counters[pos]++; }
      else {
        if(pos===plen-1){
          if(variance(counters,pattern)<MAX_AVG) return [pstart,x];
          pstart+=counters[0]+counters[1];
          for(i=2;i<plen;i++) counters[i-2]=counters[i];
          counters[plen-2]=0; counters[plen-1]=0; pos--;
        } else { pos++; }
        counters[pos]=1; white=!white;
      }
    }
    return null;
  }
  function digitAt(bits,offset,pats){
    var c=[0,0,0,0], next=record(bits,offset,c);
    if(next<0) return null;
    var d=bestDigit(c,pats);
    if(d<0) return null;
    return {d:d,next:next};
  }
  function checkEAN13(s){
    if(s.length!==13) return false;
    var sum=0; for(var i=0;i<12;i++){var n=s.charCodeAt(i)-48; sum+=(i%2===0)?n:n*3;}
    return ((10-(sum%10))%10)===(s.charCodeAt(12)-48);
  }
  function checkEAN8(s){
    if(s.length!==8) return false;
    var sum=0; for(var i=0;i<7;i++){var n=s.charCodeAt(i)-48; sum+=(i%2===0)?n*3:n;}
    return ((10-(sum%10))%10)===(s.charCodeAt(7)-48);
  }
  function decodeEAN13(bits){
    var start=findGuard(bits,0,false,START); if(!start) return null;
    var off=start[1], digits="", lg=0, i, r;
    for(i=0;i<6;i++){ r=digitAt(bits,off,LG); if(!r) return null; digits+=(r.d%10); off=r.next; if(r.d>=10) lg|=(1<<(5-i)); }
    var first=FD.indexOf(lg); if(first<0) return null;
    var mid=findGuard(bits,off,true,MID); if(!mid) return null; off=mid[1];
    for(i=0;i<6;i++){ r=digitAt(bits,off,L); if(!r) return null; digits+=r.d; off=r.next; }
    var full=""+first+digits;
    if(!checkEAN13(full)) return null;
    return full;
  }
  function decodeEAN8(bits){
    var start=findGuard(bits,0,false,START); if(!start) return null;
    var off=start[1], digits="", i, r;
    for(i=0;i<4;i++){ r=digitAt(bits,off,L); if(!r) return null; digits+=r.d; off=r.next; }
    var mid=findGuard(bits,off,true,MID); if(!mid) return null; off=mid[1];
    for(i=0;i<4;i++){ r=digitAt(bits,off,L); if(!r) return null; digits+=r.d; off=r.next; }
    if(!checkEAN8(digits)) return null;
    return digits;
  }
  function decodeRow(bits){
    var e=decodeEAN13(bits);
    if(e) return e.charAt(0)==="0" ? {code:e.slice(1),format:"upc_a"} : {code:e,format:"ean_13"};
    var e8=decodeEAN8(bits);
    if(e8) return {code:e8,format:"ean_8"};
    return null;
  }
  function reversed(bits){
    var n=bits.length, out=new Uint8Array(n), i;
    for(i=0;i<n;i++) out[i]=bits[n-1-i];
    return out;
  }
  function decodeAny(bits){
    return decodeRow(bits) || decodeRow(reversed(bits));
  }
  function rowToBits(data,width,y){
    var base=y*width*4, min=255,max=0, x, lum=new Uint8Array(width);
    for(x=0;x<width;x++){ var i=base+x*4; var l=(data[i]*77+data[i+1]*150+data[i+2]*29)>>8; lum[x]=l; if(l<min)min=l; if(l>max)max=l; }
    if(max-min<32) return null;
    var th=(min+max)>>1, bits=new Uint8Array(width);
    for(x=0;x<width;x++) bits[x]= lum[x]<th ? 1:0;
    return bits;
  }
  function colToBits(data,width,height,x){
    var min=255,max=0,y,lum=new Uint8Array(height);
    for(y=0;y<height;y++){ var i=(y*width+x)*4; var l=(data[i]*77+data[i+1]*150+data[i+2]*29)>>8; lum[y]=l; if(l<min)min=l; if(l>max)max=l; }
    if(max-min<32) return null;
    var th=(min+max)>>1, bits=new Uint8Array(height);
    for(y=0;y<height;y++) bits[y]= lum[y]<th ? 1:0;
    return bits;
  }
  function scanLines(data,width,height,vertical){
    var major=vertical?width:height;
    var a0=Math.floor(major*0.15), a1=Math.floor(major*0.85);
    var lines=30, step=Math.max(1,Math.floor((a1-a0)/lines)), a;
    for(a=a0;a<a1;a+=step){
      var bits=vertical?colToBits(data,width,height,a):rowToBits(data,width,a);
      if(!bits) continue;
      var res=decodeAny(bits);
      if(res) return res;
    }
    return null;
  }
  function decode(data,width,height){
    return scanLines(data,width,height,false) || scanLines(data,width,height,true);
  }
  var API={decode:decode,decodeAny:decodeAny,decodeRow:decodeRow,decodeEAN13:decodeEAN13,decodeEAN8:decodeEAN8,reversed:reversed};
  if(typeof module!=="undefined" && module.exports) module.exports=API;
  root.OfflineBarcode=API;
})(typeof self!=="undefined"?self:this);

    </script>
    <script>
/**
 * barcode-scanner.js
 * Pemindai barcode/QR halaman kasir TANPA pustaka html5-qrcode.
 *
 * Mesin baca (mengikuti contoh index.html):
 *  1. BarcodeDetector bawaan browser (bila ada) \u2014 cepat & jalan offline.
 *  2. Dekoder OfflineBarcode (offline-barcode.js) untuk EAN-13/EAN-8/UPC-A
 *     pada browser tanpa BarcodeDetector (mis. sebagian iOS/Firefox).
 *
 * Kotak pemindai hanya menampilkan gambar kamera \u2014 TANPA animasi/garis
 * pindai \u2014 supaya ringan. Barcode terbaca otomatis (konfirmasi 2x + cek
 * digit untuk EAN/UPC) lalu memanggil onDeteksi.
 *
 * API publik (tetap kompatibel dengan halaman lama):
 *  BarcodeScanner.didukung()             -> apakah kamera dapat dipakai
 *  BarcodeScanner.mulai(opsi)            -> buka pemindai kamera (jendela penuh)
 *  BarcodeScanner.hentikan()             -> tutup pemindai jendela
 *  BarcodeScanner.pasang(opsi)           -> tanam kotak kamera ke dalam elemen (dipakai pop up)
 *  BarcodeScanner.bukaInputManual(opsi)  -> kotak ketik/tembak barcode
 *  BarcodeScanner.pasangTombol(akar)     -> pasang otomatis tombol [data-pindai]
 *  BarcodeScanner.pantauAlatPindai(opsi) -> tangkap ketikan cepat alat pemindai USB
 *
 * opsi umum:
 *  onDeteksi(kode)   dipanggil tiap barcode terbaca
 *  onGagal(galat)    dipanggil bila kamera gagal
 *  onStatus(teks)    dipanggil tiap status berubah (khusus pasang())
 *  sekaliPakai       true = tutup/berhenti setelah satu kode
 *  tanpaCadangan     true = jangan buka kotak ketik manual saat kamera gagal
 *  judul             judul jendela pemindai
 */
(function () {
    'use strict';

    var FORMAT_DIINGINKAN = [
        'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128',
        'code_39', 'code_93', 'itf', 'codabar', 'qr_code', 'data_matrix',
    ];
    var FORMAT_RITEL = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];
    var KONFIRMASI = 2;         // minimal 2 pembacaan identik sebelum diterima
    var UKURAN_BUFFER = 6;       // toleran terhadap satu frame buram/keliru di antaranya
    var JEDA_ULANG = 2500;      // ms: cegah kode sama terbaca berkali-kali
    var GAYA_TERPASANG = false;

    var overlayAktif = null;    // { lapisan, kontrol }
    var opsiAktif = {};

    /* ------------------------------------------------------------------ */
    /* Pembantu umum                                                       */
    /* ------------------------------------------------------------------ */

    function buatElemen(tag, gaya, teks) {
        var elemen = document.createElement(tag);
        if (gaya) { elemen.style.cssText = gaya; }
        if (teks !== undefined && teks !== null) { elemen.textContent = teks; }
        return elemen;
    }

    function buatTombol(teks, warna) {
        var tombol = buatElemen('button', '', teks);
        tombol.type = 'button';
        tombol.style.cssText = 'flex:0 1 auto;min-width:120px;padding:10px 14px;border:0;border-radius:10px;'
            + 'font-size:14px;font-weight:700;cursor:pointer;background:' + (warna || '#1f2937') + ';color:#fff;';
        return tombol;
    }

    function bersihkanKode(kode) {
        return String(kode === null || kode === undefined ? '' : kode).replace(/[\u0000-\u001f\u007f\s]+/g, '');
    }

    function hanyaAngka(v) { return /^[0-9]+$/.test(v); }

    /**
     * Cek digit EAN/UPC. Kode non-angka atau panjang tak lazim dibiarkan lolos
     * (mis. QR/Code-128 alfanumerik) supaya tidak menghalangi pembacaan sah.
     */
    function cekDigitEANUPC(v) {
        if (! hanyaAngka(v)) { return true; }
        var n = v.length;
        if (n !== 8 && n !== 12 && n !== 13 && n !== 14) { return true; }
        var body = v.slice(0, n - 1);
        var check = parseInt(v.charAt(n - 1), 10);
        var sum = 0;
        for (var i = 0; i < body.length; i++) {
            var d = parseInt(body.charAt(body.length - 1 - i), 10);
            sum += (i % 2 === 0) ? d * 3 : d;
        }
        var calc = (10 - (sum % 10)) % 10;
        return calc === check;
    }

    var _konteksAudio = null;
    function bunyikanBeep(berhasil) {
        try {
            var Audio = window.AudioContext || window.webkitAudioContext;
            if (! Audio) { return; }
            _konteksAudio = _konteksAudio || new Audio();
            var osilator = _konteksAudio.createOscillator();
            var penguat = _konteksAudio.createGain();
            osilator.type = 'square';
            osilator.frequency.value = berhasil === false ? 320 : 1180;
            penguat.gain.value = 0.05;
            osilator.connect(penguat);
            penguat.connect(_konteksAudio.destination);
            osilator.start();
            osilator.stop(_konteksAudio.currentTime + 0.09);
        } catch (galat) { /* nada gagal tidak perlu mengganggu kasir */ }
    }

    function getar(ms) {
        if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (galat) { /* abaikan */ } }
    }

    /** Kamera hanya bisa dipakai pada konteks aman (HTTPS atau localhost). */
    function didukung() {
        return !! (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext !== false;
    }

    /**
     * Apakah API kamera tersedia sama sekali (tanpa mempersoalkan konteks aman).
     * Dipakai agar kita SELALU mencoba meminta izin kamera sehingga peramban
     * benar-benar menampilkan prompt izin dan kotak pemindai tidak "hilang".
     */
    function adaKameraAPI() {
        return !! (navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    function suntikGaya() {
        if (GAYA_TERPASANG) { return; }
        GAYA_TERPASANG = true;
        var css = ''
            + '.bcs-kotak{position:relative;width:100%;background:#000;border-radius:12px;overflow:hidden;'
            + 'aspect-ratio:4/3;min-height:200px;max-height:60vh;}'
            + '.bcs-kotak>video{width:100%;height:100%;object-fit:cover;display:block;background:#000;}'
            + '.bcs-status{margin:8px 0 0;font-size:12px;line-height:1.4;color:#475569;min-height:16px;}'
            + '.bcs-aksi{margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;}'
            + '.bcs-select{flex:0 1 auto;min-width:130px;padding:9px 10px;border:1px solid #cbd5f5;border-radius:10px;'
            + 'font-size:13px;background:#fff;color:#0f172a;}'
            + '.bcs-overlay{position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,.94);display:flex;'
            + 'align-items:center;justify-content:center;padding:16px;}'
            + '.bcs-panel{width:100%;max-width:520px;max-height:92vh;overflow-y:auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;'
            + 'padding:16px;box-shadow:0 20px 45px rgba(0,0,0,.45);}'
            + '.bcs-panel .bcs-status{color:#e2e8f0;}'
            + '.bcs-kepala{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;}'
            + '.bcs-kepala h3{margin:0;font-size:16px;font-weight:700;color:#f8fafc;}'
            + '.bcs-kepala p{margin:2px 0 0;font-size:12px;color:#94a3b8;}'
            + '.bcs-tutup{border:0;background:transparent;color:#94a3b8;font-size:22px;line-height:1;cursor:pointer;padding:2px 6px;}';
        var gaya = document.createElement('style');
        gaya.setAttribute('data-bcs', '1');
        gaya.appendChild(document.createTextNode(css));
        document.head.appendChild(gaya);
    }

    /* ------------------------------------------------------------------ */
    /* Penerjemah pesan galat kamera                                       */
    /* ------------------------------------------------------------------ */

    function pesanGalat(galat) {
        if (! galat) { return 'Penyebab tidak diketahui.'; }
        var teks = typeof galat === 'string'
            ? galat
            : (galat.message || galat.name || (galat.toString ? galat.toString() : ''));
        teks = String(teks || '').trim();
        if (teks === '[object Object]') { teks = ''; }
        var nama = galat && galat.name ? String(galat.name) : '';
        var gabung = (nama + ' ' + teks).toLowerCase();
        if (gabung.indexOf('notallowed') !== -1 || gabung.indexOf('permission') !== -1 || gabung.indexOf('denied') !== -1 || gabung.indexOf('ditolak') !== -1) {
            return 'Izin kamera ditolak. Klik ikon kamera di address bar lalu pilih "Izinkan", atau pakai kotak ketik/tembak kode.';
        }
        if (gabung.indexOf('notfound') !== -1 || gabung.indexOf('devicesnotfound') !== -1 || gabung.indexOf('tidak ditemukan') !== -1 || gabung.indexOf('no camera') !== -1) {
            return 'Kamera tidak ditemukan. Pakai alat pemindai USB atau kotak ketik/tembak kode.';
        }
        if (gabung.indexOf('notreadable') !== -1 || gabung.indexOf('inuse') !== -1 || gabung.indexOf('trackstart') !== -1 || gabung.indexOf('could not start') !== -1) {
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
        }
        if (gabung.indexOf('secure') !== -1 || gabung.indexOf('https') !== -1 || gabung.indexOf('insecure') !== -1) {
            return 'Kamera hanya bisa dipakai lewat HTTPS atau localhost.';
        }
        if (gabung.indexOf('overconstrained') !== -1 || gabung.indexOf('constraint') !== -1) {
            return 'Kamera tidak mendukung pengaturan yang diminta. Coba ganti kamera.';
        }
        return teks || 'Penyebab tidak diketahui.';
    }

    function galatRamah(galat) {
        if (galat instanceof Error && galat.ramah === true) { return galat; }
        var hasil = new Error(pesanGalat(galat));
        hasil.ramah = true;
        hasil.asli = galat;
        return hasil;
    }

    /* ------------------------------------------------------------------ */
    /* Mesin kamera (BarcodeDetector + OfflineBarcode)                     */
    /* ------------------------------------------------------------------ */

    function buatSesi(video, onKode, setStatus) {
        var stream = null, track = null, detector = null;
        var pakaiOffline = false, jalan = false;
        var timerDetektor = 0, timerOffline = 0;
        var sibukDetektor = false, sibukOffline = false;
        var kanvas = document.createElement('canvas');
        var kctx = kanvas.getContext('2d', { willReadFrequently: true });
        var kodeTerakhir = '', waktuTerakhir = 0, bufferKode = [];

        function lapor(teks) { if (typeof setStatus === 'function') { setStatus(teks); } }

        function terima(nilai, format) {
            var kode = bersihkanKode(nilai);
            if (! kode) { return; }
            var sekarang = Date.now();
            if (kode === kodeTerakhir && (sekarang - waktuTerakhir) < JEDA_ULANG) { return; }
            bufferKode.push(kode);
            if (bufferKode.length > UKURAN_BUFFER) { bufferKode.shift(); }
            var jumlahSama = bufferKode.filter(function (satu) { return satu === kode; }).length;
            if (jumlahSama < KONFIRMASI) { return; }
            if (! cekDigitEANUPC(kode)) { bufferKode = []; return; }
            kodeTerakhir = kode; waktuTerakhir = sekarang; bufferKode = [];
            bunyikanBeep(true);
            getar(50);
            try { onKode(kode, format || ''); } catch (galat) { /* abaikan */ }
        }

        function siapkanMesin() {
            var adaDetector = typeof window.BarcodeDetector !== 'undefined';
            function buat(formats) {
                try { return formats ? new window.BarcodeDetector({ formats: formats }) : new window.BarcodeDetector(); }
                catch (galat) { return null; }
            }
            if (adaDetector && typeof window.BarcodeDetector.getSupportedFormats === 'function') {
                return window.BarcodeDetector.getSupportedFormats().then(function (didukungList) {
                    var f = FORMAT_DIINGINKAN.filter(function (x) { return didukungList.indexOf(x) !== -1; });
                    detector = buat(f.length ? f : null);
                    var adaRitel = FORMAT_RITEL.some(function (x) { return didukungList.indexOf(x) !== -1; });
                    // Jalankan dekoder lokal juga untuk EAN/UPC. Dua mesin yang bekerja
                    // paralel membuat pemindaian tetap akurat tanpa bergantung internet.
                    pakaiOffline = !! window.OfflineBarcode;
                }).catch(function () {
                    detector = buat(null);
                    pakaiOffline = !! window.OfflineBarcode;
                });
            }
            if (adaDetector) {
                detector = buat(null);
                pakaiOffline = !! window.OfflineBarcode;
                return Promise.resolve();
            }
            detector = null;
            pakaiOffline = !! window.OfflineBarcode;
            return Promise.resolve();
        }

        function tikDetektor() {
            if (! jalan) { return; }
            if (! sibukDetektor && detector && video.readyState >= 2) {
                sibukDetektor = true;
                detector.detect(video).then(function (daftar) {
                    sibukDetektor = false;
                    if (daftar && daftar.length) { terima(daftar[0].rawValue, daftar[0].format); }
                }).catch(function () { sibukDetektor = false; });
            }
            timerDetektor = setTimeout(tikDetektor, 90);
        }

        function tikOffline() {
            if (! jalan) { return; }
            if (! sibukOffline && video.readyState >= 2 && video.videoWidth) {
                sibukOffline = true;
                try {
                    var vw = video.videoWidth, vh = video.videoHeight;
                    var skala = Math.min(1, 1024 / vw);
                    var w = Math.max(1, Math.round(vw * skala));
                    var h = Math.max(1, Math.round(vh * skala));
                    if (kanvas.width !== w) { kanvas.width = w; }
                    if (kanvas.height !== h) { kanvas.height = h; }
                    kctx.drawImage(video, 0, 0, w, h);
                    var gambar = kctx.getImageData(0, 0, w, h);
                    var hasil = window.OfflineBarcode ? window.OfflineBarcode.decode(gambar.data, w, h) : null;
                    if (hasil && hasil.code) { terima(hasil.code, hasil.format || 'ean'); }
                } catch (galat) { /* frame gagal: lewati */ }
                sibukOffline = false;
            }
            timerOffline = setTimeout(tikOffline, 120);
        }

        function mulaiLoop() {
            if (detector) { tikDetektor(); }
            if (pakaiOffline) { tikOffline(); }
        }

        function mulai(deviceId) {
            if (jalan) { return Promise.resolve(); }
            lapor('Menyiapkan kamera\u2026');
            var pilihan = deviceId
                ? { deviceId: { exact: deviceId } }
                : {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                };
            function mintaKamera(v) { return navigator.mediaDevices.getUserMedia({ audio: false, video: v }); }
            return mintaKamera(pilihan).catch(function (galatAwal) {
                // Fallback supaya kotak pemindai tidak gagal muncul akibat kamera/deviceId
                // yang terlalu spesifik (OverconstrainedError / NotFoundError / NotReadableError).
                var nama = galatAwal && galatAwal.name ? String(galatAwal.name) : '';
                if (deviceId) {
                    return mintaKamera({ facingMode: { ideal: 'environment' } }).catch(function () { return mintaKamera(true); });
                }
                if (nama === 'OverconstrainedError' || nama === 'ConstraintNotSatisfiedError' || nama === 'NotFoundError' || nama === 'NotReadableError') {
                    return mintaKamera(true);
                }
                throw galatAwal;
            }).then(function (s) {
                stream = s;
                track = s.getVideoTracks()[0] || null;
                // Minta fokus kontinu bila kamera mendukungnya. Kegagalan constraint
                // tidak menghentikan scanner karena dukungan berbeda tiap perangkat.
                if (track && track.getCapabilities && track.applyConstraints) {
                    try {
                        var kemampuan = track.getCapabilities();
                        if (kemampuan.focusMode && kemampuan.focusMode.indexOf('continuous') !== -1) {
                            track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(function () {});
                        }
                    } catch (galatFokus) { /* abaikan */ }
                }
                video.setAttribute('playsinline', 'true');
                video.muted = true;
                video.srcObject = s;
                return video.play().catch(function () { /* sebagian browser butuh gestur; abaikan */ });
            }).then(function () {
                return siapkanMesin();
            }).then(function () {
                jalan = true;
                if (detector && pakaiOffline) { lapor('Arahkan barcode ke kamera\u2026'); }
                else if (detector) { lapor('Arahkan barcode ke kamera\u2026'); }
                else if (pakaiOffline) { lapor('Mode offline. Arahkan barcode EAN/UPC ke kamera\u2026'); }
                else { lapor('Kamera aktif, namun perangkat ini tidak punya pembaca barcode. Pakai input manual.'); }
                mulaiLoop();
            }).catch(function (galat) {
                lapor(pesanGalat(galat));
                throw galatRamah(galat);
            });
        }

        function henti() {
            jalan = false;
            if (timerDetektor) { clearTimeout(timerDetektor); timerDetektor = 0; }
            if (timerOffline) { clearTimeout(timerOffline); timerOffline = 0; }
            sibukDetektor = false; sibukOffline = false;
            if (stream) {
                stream.getTracks().forEach(function (t) { try { t.stop(); } catch (galat) { /* abaikan */ } });
                stream = null;
            }
            track = null; detector = null;
            try { video.pause(); video.srcObject = null; } catch (galat) { /* abaikan */ }
            bufferKode = [];
        }

        function bisaSenter() {
            if (! track || ! track.getCapabilities) { return false; }
            var cap = track.getCapabilities();
            return !! (cap && cap.torch);
        }

        function senter(nyala) {
            if (! bisaSenter()) { return Promise.reject(new Error('Senter tidak didukung.')); }
            return track.applyConstraints({ advanced: [{ torch: !! nyala }] });
        }

        return {
            mulai: mulai,
            henti: henti,
            senter: senter,
            bisaSenter: bisaSenter,
            sedangJalan: function () { return jalan; }
        };
    }

    /* ------------------------------------------------------------------ */
    /* pasang(): tanam kotak kamera polos ke dalam elemen (untuk pop up)   */
    /* ------------------------------------------------------------------ */

    function pasang(opsi) {
        opsi = opsi || {};
        var kontainer = typeof opsi.kontainer === 'string'
            ? document.getElementById(opsi.kontainer)
            : opsi.kontainer;
        if (! kontainer) { throw new Error('Kontainer pemindai tidak ditemukan.'); }

        var onDeteksi = typeof opsi.onDeteksi === 'function' ? opsi.onDeteksi : function () {};
        var onStatus = typeof opsi.onStatus === 'function' ? opsi.onStatus : null;

        suntikGaya();
        kontainer.innerHTML = '';

        var kotak = buatElemen('div');
        kotak.className = 'bcs-kotak';
        var video = document.createElement('video');
        video.setAttribute('playsinline', 'true');
        video.muted = true;
        video.autoplay = true;
        kotak.appendChild(video);

        var status = buatElemen('p');
        status.className = 'bcs-status';
        status.textContent = adaKameraAPI()
            ? 'Meminta akses kamera…'
            : 'Kamera tidak tersedia di peramban ini. Ketik atau tembak kode barcode di bawah.';

        var baris = buatElemen('div');
        baris.className = 'bcs-aksi';
        var tombolMulai = buatTombol('Mulai kamera', '#4f46e5');
        var tombolStop = buatTombol('Berhenti', '#334155');
        tombolStop.style.display = 'none';
        var pilihKamera = document.createElement('select');
        pilihKamera.className = 'bcs-select';
        pilihKamera.style.display = 'none';
        var tombolSenter = buatTombol('Senter', '#b45309');
        tombolSenter.style.display = 'none';
        baris.appendChild(tombolMulai);
        baris.appendChild(tombolStop);
        baris.appendChild(pilihKamera);
        baris.appendChild(tombolSenter);

        kontainer.appendChild(kotak);
        kontainer.appendChild(status);
        kontainer.appendChild(baris);

        function setStatus(teks) {
            status.textContent = teks || '';
            if (onStatus) { onStatus(teks || ''); }
        }

        var senterNyala = false;
        var sesi = buatSesi(video, function (kode, format) {
            setStatus('Terbaca: ' + kode);
            onDeteksi(kode, format);
            if (opsi.sekaliPakai === true) { hentikanSesi(); }
        }, setStatus);

        function isiDaftarKamera() {
            if (! navigator.mediaDevices || ! navigator.mediaDevices.enumerateDevices) { return; }
            navigator.mediaDevices.enumerateDevices().then(function (daftar) {
                var kamera = daftar.filter(function (d) { return d.kind === 'videoinput'; });
                if (kamera.length > 1) {
                    pilihKamera.innerHTML = '';
                    kamera.forEach(function (c, i) {
                        var opt = document.createElement('option');
                        opt.value = c.deviceId;
                        opt.textContent = c.label || ('Kamera ' + (i + 1));
                        pilihKamera.appendChild(opt);
                    });
                    pilihKamera.style.display = '';
                }
            }).catch(function () { /* abaikan */ });
        }

        var sedangMulai = false;
        function mulaiSesi() {
            if (sesi.sedangJalan() || sedangMulai) { return Promise.resolve(); }
            if (! adaKameraAPI()) {
                setStatus('Kamera tidak tersedia di peramban ini. Ketik atau tembak kode barcode di bawah.');
                tombolMulai.textContent = 'Coba kamera';
                tombolMulai.style.display = '';
                return Promise.reject(new Error('Kamera tidak tersedia.'));
            }
            sedangMulai = true;
            tombolMulai.disabled = true;
            setStatus('Meminta akses kamera…');
            return sesi.mulai(pilihKamera.value || null).then(function () {
                sedangMulai = false;
                tombolMulai.disabled = false;
                tombolMulai.textContent = 'Mulai kamera';
                tombolMulai.style.display = 'none';
                tombolStop.style.display = '';
                isiDaftarKamera();
                if (sesi.bisaSenter()) { tombolSenter.style.display = ''; }
            }).catch(function (galat) {
                sedangMulai = false;
                tombolMulai.disabled = false;
                tombolMulai.textContent = 'Izinkan kamera / Coba lagi';
                tombolMulai.style.display = '';
                setStatus(pesanGalat(galat));
                return Promise.reject(galatRamah(galat));
            });
        }

        function hentikanSesi() {
            sesi.henti();
            tombolStop.style.display = 'none';
            tombolSenter.style.display = 'none';
            tombolSenter.textContent = 'Senter';
            senterNyala = false;
            tombolMulai.style.display = '';
            tombolMulai.disabled = false;
        }

        tombolMulai.addEventListener('click', function () { mulaiSesi().catch(function () {}); });
        tombolStop.addEventListener('click', function () { hentikanSesi(); setStatus('Kamera berhenti.'); });
        tombolSenter.addEventListener('click', function () {
            senterNyala = ! senterNyala;
            sesi.senter(senterNyala).then(function () {
                tombolSenter.textContent = senterNyala ? 'Matikan senter' : 'Senter';
            }).catch(function () {
                senterNyala = false;
                setStatus('Senter tidak didukung kamera ini.');
            });
        });
        pilihKamera.addEventListener('change', function () {
            if (sesi.sedangJalan()) { sesi.henti(); mulaiSesi().catch(function () {}); }
        });

        return {
            mulai: mulaiSesi,
            hentikan: hentikanSesi,
            sedangJalan: function () { return sesi.sedangJalan(); },
            elemenVideo: video,
            setStatus: setStatus
        };
    }

    /* ------------------------------------------------------------------ */
    /* mulai(): pemindai jendela penuh (kompatibel dengan halaman lama)    */
    /* ------------------------------------------------------------------ */

    function mulai(opsi) {
        opsiAktif = opsi || {};
        hentikan();

        function tanganiGagal(galat) {
            hentikan();
            var ramah = galatRamah(galat);
            if (typeof opsiAktif.onGagal === 'function') { opsiAktif.onGagal(ramah); }
            if (opsiAktif.tanpaCadangan !== true) { bukaInputManual(opsiAktif); }
        }

        if (! didukung()) {
            tanganiGagal(new Error('Kamera tidak tersedia (perlu HTTPS atau perangkat berkamera).'));
            return;
        }

        suntikGaya();

        var lapisan = buatElemen('div');
        lapisan.className = 'bcs-overlay';
        var panel = buatElemen('div');
        panel.className = 'bcs-panel';

        var kepala = buatElemen('div');
        kepala.className = 'bcs-kepala';
        var judul = buatElemen('div');
        var h = buatElemen('h3', '', opsiAktif.judul || 'Pindai barcode barang');
        var sub = buatElemen('p', '', 'Cukup arahkan barcode ke kamera \u2014 tanpa animasi, otomatis terbaca.');
        judul.appendChild(h);
        judul.appendChild(sub);
        var tombolTutup = buatElemen('button', '', '\u00d7');
        tombolTutup.type = 'button';
        tombolTutup.className = 'bcs-tutup';
        tombolTutup.title = 'Tutup pemindai';
        tombolTutup.addEventListener('click', hentikan);
        kepala.appendChild(judul);
        kepala.appendChild(tombolTutup);

        var wadah = buatElemen('div');
        panel.appendChild(kepala);
        panel.appendChild(wadah);

        var barisBawah = buatElemen('div');
        barisBawah.className = 'bcs-aksi';
        var tombolManual = buatTombol('Ketik / tembak kode', '#334155');
        tombolManual.addEventListener('click', function () {
            var simpanan = opsiAktif;
            hentikan();
            bukaInputManual(simpanan);
        });
        barisBawah.appendChild(tombolManual);
        panel.appendChild(barisBawah);

        lapisan.appendChild(panel);
        lapisan.addEventListener('click', function (event) {
            if (event.target === lapisan) { hentikan(); }
        });
        document.body.appendChild(lapisan);

        var kontrol = pasang({
            kontainer: wadah,
            sekaliPakai: false,
            onStatus: opsiAktif.onStatus,
            onDeteksi: function (kode, format) {
                if (typeof opsiAktif.onDeteksi === 'function') { opsiAktif.onDeteksi(kode, format); }
                if (opsiAktif.sekaliPakai === true) { hentikan(); }
            }
        });

        overlayAktif = { lapisan: lapisan, kontrol: kontrol };

        kontrol.mulai().catch(function (galat) {
            // Kamera gagal: tutup jendela lalu buka cadangan input manual.
            tanganiGagal(galat);
        });
    }

    function hentikan() {
        if (overlayAktif) {
            try { overlayAktif.kontrol.hentikan(); } catch (galat) { /* abaikan */ }
            if (overlayAktif.lapisan && overlayAktif.lapisan.parentNode) {
                overlayAktif.lapisan.parentNode.removeChild(overlayAktif.lapisan);
            }
            overlayAktif = null;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Kotak ketik / tembak kode (cadangan tanpa kamera + alat USB)        */
    /* ------------------------------------------------------------------ */

    var lapisanManual = null;
    function tutupManual() {
        if (lapisanManual) { lapisanManual.remove(); lapisanManual = null; }
    }

    function bukaInputManual(opsi) {
        opsi = opsi || {};
        opsiAktif = opsi;
        hentikan();
        tutupManual();
        suntikGaya();

        lapisanManual = buatElemen('div', 'position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,.9);'
            + 'display:flex;align-items:center;justify-content:center;padding:16px;');

        var panel = buatElemen('div', 'width:100%;max-width:440px;background:#fff;border-radius:16px;padding:18px;'
            + 'box-shadow:0 20px 45px rgba(0,0,0,.35);');

        panel.appendChild(buatElemen('p', 'margin:0;font-size:16px;font-weight:700;color:#0f172a;',
            opsi.judul || 'Tembak atau ketik barcode'));
        panel.appendChild(buatElemen('p', 'margin:4px 0 12px;font-size:12px;color:#475569;',
            'Tembakkan alat pemindai ke kolom ini (otomatis terkirim), atau ketik kodenya lalu klik Gunakan kode.'));

        var isian = document.createElement('input');
        isian.type = 'text';
        isian.inputMode = 'text';
        isian.autocomplete = 'off';
        isian.placeholder = 'Contoh: 8991002100015';
        isian.style.cssText = 'width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cbd5f5;'
            + 'border-radius:10px;font-size:18px;letter-spacing:.04em;color:#0f172a;';

        var pesan = buatElemen('p', 'margin:8px 0 0;font-size:12px;color:#dc2626;min-height:16px;', '');
        var baris = buatElemen('div', 'margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;');

        var tombolPakai = buatTombol('Gunakan kode', '#047857');
        var tombolKamera = buatTombol('Coba kamera', '#1d4ed8');
        var tombolTutup = buatTombol('Tutup', '#334155');

        function kirim() {
            var kode = bersihkanKode(isian.value);
            if (! kode) { pesan.textContent = 'Kode masih kosong.'; return; }
            pesan.textContent = '';
            bunyikanBeep(true);
            if (typeof opsi.onDeteksi === 'function') { opsi.onDeteksi(kode); }
            if (opsi.sekaliPakai === true) { tutupManual(); return; }
            isian.value = '';
            isian.focus();
        }

        tombolPakai.addEventListener('click', kirim);
        tombolTutup.addEventListener('click', tutupManual);
        tombolKamera.addEventListener('click', function () {
            tutupManual();
            mulai(Object.assign({}, opsi, { tanpaCadangan: true }));
        });
        isian.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); kirim(); }
        });

        baris.appendChild(tombolPakai);
        baris.appendChild(tombolKamera);
        baris.appendChild(tombolTutup);
        panel.appendChild(isian);
        panel.appendChild(pesan);
        panel.appendChild(baris);
        lapisanManual.appendChild(panel);
        lapisanManual.addEventListener('click', function (event) {
            if (event.target === lapisanManual) { tutupManual(); }
        });
        document.body.appendChild(lapisanManual);
        isian.focus();
    }

    /* ------------------------------------------------------------------ */
    /* Pemasangan tombol otomatis [data-pindai]                            */
    /* ------------------------------------------------------------------ */

    function pasangTombol(akar) {
        var wadah = akar || document;
        var tombolTombol = wadah.querySelectorAll('[data-pindai]');
        Array.prototype.forEach.call(tombolTombol, function (tombol) {
            if (tombol.hasAttribute('data-pindai-terpasang') || tombol.hasAttribute('data-pindai-lewati')) { return; }
            tombol.setAttribute('data-pindai-terpasang', 'true');
            tombol.addEventListener('click', function () {
                var selektor = tombol.getAttribute('data-pindai-target');
                var sasaran = selektor ? document.querySelector(selektor) : null;
                mulai({
                    judul: tombol.getAttribute('data-pindai-judul') || 'Pindai barcode',
                    sekaliPakai: tombol.hasAttribute('data-pindai-sekali'),
                    onDeteksi: function (kode) {
                        if (sasaran) {
                            sasaran.value = kode;
                            sasaran.dispatchEvent(new Event('input', { bubbles: true }));
                            sasaran.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        tombol.dispatchEvent(new CustomEvent('barcode:terdeteksi', {
                            detail: { kode: kode }, bubbles: true,
                        }));
                        var kirim = tombol.getAttribute('data-pindai-submit');
                        if (kirim) {
                            var pengirim = document.querySelector(kirim);
                            if (pengirim) { pengirim.click(); }
                        }
                    },
                });
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Alat pemindai USB (keyboard wedge)                                  */
    /* ------------------------------------------------------------------ */

    function pantauAlatPindai(opsi) {
        opsi = opsi || {};
        var jedaMaks = opsi.jedaMaks || 90;
        var minPanjang = opsi.minPanjang || 4;
        var penyangga = '';
        var mulaiKetik = 0;
        var terakhirKetik = 0;

        function bolehTangkap() {
            var aktif = document.activeElement;
            if (! aktif) { return true; }
            if (aktif.isContentEditable) { return false; }
            var tag = (aktif.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                return aktif.hasAttribute('data-pindai-tangkap');
            }
            return true;
        }

        document.addEventListener('keydown', function (event) {
            if (event.ctrlKey || event.altKey || event.metaKey) { return; }
            if (! bolehTangkap()) { penyangga = ''; return; }
            var sekarang = Date.now();
            if (event.key === 'Enter') {
                var lamaKetik = sekarang - mulaiKetik;
                if (penyangga.length >= minPanjang && lamaKetik < (penyangga.length * jedaMaks + 200)) {
                    event.preventDefault();
                    var kode = bersihkanKode(penyangga);
                    penyangga = '';
                    if (kode && typeof opsi.onDeteksi === 'function') { opsi.onDeteksi(kode); }
                    return;
                }
                penyangga = '';
                return;
            }
            if (event.key.length !== 1) { return; }
            if (sekarang - terakhirKetik > 500) { penyangga = ''; mulaiKetik = sekarang; }
            terakhirKetik = sekarang;
            penyangga += event.key;
        }, true);
    }

    /* ------------------------------------------------------------------ */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { pasangTombol(document); });
    } else {
        pasangTombol(document);
    }

    window.BarcodeScanner = {
        didukung: didukung,
        mulai: mulai,
        hentikan: hentikan,
        pasang: pasang,
        bukaInputManual: bukaInputManual,
        pasangTombol: pasangTombol,
        pantauAlatPindai: pantauAlatPindai,
        pesanGalat: pesanGalat,
    };
})();

    </script>

    {{-- Modul antrean transaksi/katalog tetap terpisah karena bukan bagian mesin scanner. --}}
    <script src="{{ asset('js/pos-offline.js') }}"></script>

    <script>
        (function () {
            'use strict';

            var rute = {
                cari: '{{ route('pos.lookup') }}',
                checkout: '{{ route('pos.checkout') }}',
            };
            var csrf = '{{ csrf_token() }}';

            var keranjang = [];
            var kontrolScannerKasir = null;
            var pelangganManual = false;

            function el(id) {
                return document.getElementById(id);
            }

            function rupiah(nilai) {
                return 'Rp ' + Number(nilai || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }

            function amanTeks(teks) {
                var kotak = document.createElement('span');
                kotak.textContent = teks === null || teks === undefined ? '' : String(teks);

                return kotak.innerHTML;
            }

            function bersihkanKode(kode) {
                return String(kode || '').replace(/\s+/g, '');
            }

            /** Teks yang mirip barcode: cukup panjang, tanpa spasi. */
            function sepertiBarcode(teks) {
                var kode = String(teks || '');

                return /^[0-9A-Za-z\-\._]{6,}$/.test(kode) && ! /\s/.test(kode);
            }

            /* ------------------------------------------------------------ */
            /* Keranjang                                                     */
            /* ------------------------------------------------------------ */

            function hitung() {
                var subtotal = keranjang.reduce(function (jumlah, item) {
                    return jumlah + (Number(item.unit_price) * Number(item.quantity));
                }, 0);

                var diskon = Number(el('diskon').value || 0);
                var total = Math.max(0, subtotal - diskon);
                var bayar = Number(el('bayar').value || 0);

                el('v-subtotal').textContent = rupiah(subtotal);
                el('v-total').textContent = rupiah(total);
                el('v-kembali').textContent = rupiah(Math.max(0, bayar - total));

                return { subtotal: subtotal, total: total };
            }

            function gambarKeranjang() {
                var wadah = el('keranjang');
                wadah.innerHTML = '';

                var totalQty = keranjang.reduce(function (n, item) { return n + Number(item.quantity || 0); }, 0);
                var ringkas = el('keranjang-jumlah');
                if (ringkas) {
                    ringkas.textContent = totalQty + ' barang';
                }

                // Nama transaksi terisi otomatis dari barang pertama yang dipindai.
                var namaOtomatis = el('pelanggan');
                if (namaOtomatis && ! pelangganManual) {
                    namaOtomatis.value = keranjang.length ? keranjang[0].name : '';
                }

                if (keranjang.length === 0) {
                    el('kosong').classList.remove('hidden');
                    hitung();

                    return;
                }

                el('kosong').classList.add('hidden');

                keranjang.forEach(function (item, indeks) {
                    var hargaBerubah = Number(item.unit_price) !== Number(item.base_price);

                    var baris = document.createElement('div');
                    baris.className = 'rounded-lg border border-slate-200 p-3';
                    baris.innerHTML = ''
                        + '<div class="flex items-start justify-between gap-2">'
                        + '<div>'
                        + '<p class="text-sm font-semibold text-slate-900">' + amanTeks(item.name) + '</p>'
                        + '<p class="text-xs text-slate-500">' + amanTeks(item.sku)
                        + (item.barcode ? ' &middot; ' + amanTeks(item.barcode) : '')
                        + ' &middot; stok ' + amanTeks(item.stock) + ' ' + amanTeks(item.unit || '')
                        + '</p>'
                        + '</div>'
                        + '<button type="button" data-hapus="' + indeks + '" class="text-xs font-semibold text-red-600 hover:underline">Hapus</button>'
                        + '</div>'
                        + '<div class="mt-2 grid grid-cols-3 items-end gap-2">'
                        + '<label class="text-[11px] text-slate-500">Jumlah'
                        + '<input type="number" min="1" step="1" value="' + Number(item.quantity) + '" data-qty="' + indeks + '"'
                        + ' class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></label>'
                        + '<label class="text-[11px] text-slate-500">Harga satuan'
                        + '<input type="number" min="0" step="0.01" value="' + Number(item.unit_price) + '" data-harga="' + indeks + '"'
                        + ' class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></label>'
                        + '<div class="text-right text-[11px] text-slate-500">Subtotal'
                        + '<p class="mt-1 text-sm font-semibold text-slate-900">' + rupiah(Number(item.unit_price) * Number(item.quantity)) + '</p>'
                        + '</div>'
                        + '</div>'
                        + '<p class="mt-1 text-[11px] ' + (hargaBerubah ? 'text-amber-600' : 'text-emerald-600') + '">'
                        + (hargaBerubah
                            ? 'Harga diubah (harga tersimpan ' + rupiah(item.base_price) + ')'
                            : 'Harga tetap sama seperti yang tersimpan')
                        + '</p>';

                    wadah.appendChild(baris);
                });

                wadah.querySelectorAll('[data-qty]').forEach(function (isian) {
                    isian.addEventListener('input', function () {
                        var indeks = Number(isian.getAttribute('data-qty'));
                        keranjang[indeks].quantity = Math.max(1, Number(isian.value || 1));
                        hitung();
                        gambarKeranjang();
                    });
                });

                wadah.querySelectorAll('[data-harga]').forEach(function (isian) {
                    isian.addEventListener('input', function () {
                        var indeks = Number(isian.getAttribute('data-harga'));
                        keranjang[indeks].unit_price = Math.max(0, Number(isian.value || 0));
                        keranjang[indeks].harga_diubah = true;
                        hitung();
                    });
                });

                wadah.querySelectorAll('[data-hapus]').forEach(function (tombol) {
                    tombol.addEventListener('click', function () {
                        keranjang.splice(Number(tombol.getAttribute('data-hapus')), 1);
                        gambarKeranjang();
                    });
                });

                hitung();
            }

            /**
             * Menambah barang ke keranjang.
             * Harga & jumlah memakai nilai tersimpan pada barcode, dan tetap bisa
             * diubah kasir. Bila kasir sudah mengubah harga, harga itu dipertahankan.
             */
            function tambah(produk, jumlah) {
                var tambahan = Math.max(1, Number(jumlah || produk.default_quantity || 1));
                var hargaTersimpan = Number(produk.sell_price || 0);
                var ada = null;

                keranjang.forEach(function (item) {
                    if (item.id === produk.id) {
                        ada = item;
                    }
                });

                if (ada) {
                    ada.quantity += tambahan;

                    if (! ada.harga_diubah) {
                        ada.unit_price = hargaTersimpan;
                        ada.base_price = Number(produk.base_price !== undefined && produk.base_price !== null
                            ? produk.base_price
                            : hargaTersimpan);
                    }
                } else {
                    keranjang.push({
                        id: produk.id,
                        sku: produk.sku,
                        name: produk.name,
                        unit: produk.unit,
                        stock: produk.stock,
                        barcode: produk.matched_barcode || produk.barcode || null,
                        base_price: Number(produk.base_price !== undefined && produk.base_price !== null
                            ? produk.base_price
                            : hargaTersimpan),
                        unit_price: hargaTersimpan,
                        quantity: tambahan,
                        harga_diubah: false,
                    });
                }

                gambarKeranjang();
                tampilkanInfoPindai(produk, tambahan);
            }

            /** Banner informasi barang hasil pemindaian. */
            function tampilkanInfoPindai(produk, jumlah) {
                var kotak = el('info-pindai');
                var sumber = produk.price_source === 'barcode'
                    ? 'harga khusus barcode'
                    : 'harga master barang';

                kotak.classList.remove('hidden');
                kotak.innerHTML = '<strong>' + amanTeks(produk.name) + '</strong> masuk keranjang &middot; '
                    + rupiah(produk.sell_price) + ' (' + sumber + ') &middot; ' + jumlah + ' ' + amanTeks(produk.unit || '')
                    + ' &middot; stok ' + amanTeks(produk.stock)
                    + '<br><span class="text-[11px]">Harga &amp; jumlah masih bisa diubah pada baris keranjang.</span>';
            }

            /* ------------------------------------------------------------ */
            /* Pencarian & pemindaian untuk penjualan                        */
            /* ------------------------------------------------------------ */

            function tampilkanHasil(daftar) {
                var wadah = el('hasil');
                wadah.innerHTML = '';

                if (! daftar || daftar.length === 0) {
                    wadah.innerHTML = '<p class="text-xs text-slate-400">Barang tidak ditemukan.</p>';

                    return;
                }

                daftar.forEach(function (produk) {
                    var tombol = document.createElement('button');
                    tombol.type = 'button';
                    tombol.className = 'flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left hover:bg-slate-50';
                    tombol.innerHTML = '<span>'
                        + '<span class="block text-sm font-medium text-slate-900">' + amanTeks(produk.name) + '</span>'
                        + '<span class="block text-xs text-slate-500">' + amanTeks(produk.sku)
                        + (produk.barcode ? ' &middot; ' + amanTeks(produk.barcode) : '')
                        + ' &middot; stok ' + amanTeks(produk.stock) + '</span>'
                        + '</span>'
                        + '<span class="text-sm font-semibold text-slate-900">' + rupiah(produk.sell_price) + '</span>';

                    tombol.addEventListener('click', function () {
                        tambah(produk, produk.default_quantity);
                    });

                    wadah.appendChild(tombol);
                });
            }

            /** Mengubah hasil katalog lokal menjadi bentuk yang sama dengan server. */
            function dariKatalogLokal(hasil) {
                var produk = hasil.produk;
                var alias = hasil.alias;

                return {
                    id: produk.id,
                    sku: produk.sku,
                    name: produk.name,
                    unit: produk.unit,
                    stock: produk.stock,
                    barcode: alias ? alias.code : produk.barcode,
                    matched_barcode: alias ? alias.code : produk.barcode,
                    base_price: produk.base_price !== undefined && produk.base_price !== null
                        ? produk.base_price
                        : produk.sell_price,
                    sell_price: alias && alias.price !== null && alias.price !== undefined
                        ? alias.price
                        : produk.sell_price,
                    price_source: alias ? (alias.price_source || 'barcode') : 'master',
                    default_quantity: alias && alias.quantity ? alias.quantity : (produk.default_quantity || 1),
                    barcodes: produk.barcodes || [],
                };
            }

            function cariLokal(kataKunci) {
                if (! window.PosOffline) {
                    tampilkanHasil([]);

                    return;
                }

                window.PosOffline.cariKatalogLokal(kataKunci).then(function (hasil) {
                    tampilkanHasil(hasil.map(function (produk) {
                        return dariKatalogLokal({ produk: produk, alias: null });
                    }));
                });
            }

            /** Pencarian manual maupun hasil pemindaian. */
            function cari(kataKunci) {
                var kata = String(kataKunci || '').trim();

                if (kata === '') {
                    el('hasil').innerHTML = '';

                    return;
                }

                if (! navigator.onLine) {
                    pindaiOffline(kata);

                    return;
                }

                fetch(rute.cari + '?q=' + encodeURIComponent(kata), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (respons) {
                        return respons.json();
                    })
                    .then(function (isi) {
                        var daftar = isi.data || [];
                        var kode = isi.scanned_barcode;

                        // Barcode terdaftar: barang langsung masuk keranjang.
                        if (kode && isi.barcode_registered && daftar.length > 0) {
                            var cocok = null;

                            daftar.forEach(function (produk) {
                                if (! cocok && (bersihkanKode(produk.matched_barcode) === kode || bersihkanKode(produk.barcode) === kode)) {
                                    cocok = produk;
                                }
                            });

                            if (cocok) {
                                tambah(cocok, cocok.default_quantity);
                                el('cari').value = '';
                                el('hasil').innerHTML = '';

                                return;
                            }
                        }

                        // Barcode belum terdaftar: arahkan pendaftaran ke menu Daftar Produk.
                        if (kode && ! isi.barcode_registered && daftar.length === 0 && sepertiBarcode(kata)) {
                            tampilkanPesan('Barcode ' + amanTeks(kode) + ' belum terdaftar. Daftarkan melalui menu Daftar Produk.', 'text-amber-600');
                            el('cari').value = '';

                            return;
                        }

                        tampilkanHasil(daftar);
                    })
                    .catch(function () {
                        pindaiOffline(kata);
                    });
            }

            /** Pemindaian/pencarian ketika jaringan mati (katalog IndexedDB). */
            function pindaiOffline(kata) {
                if (! window.PosOffline) {
                    tampilkanHasil([]);

                    return;
                }

                window.PosOffline.cariBarcodeLokal(kata).then(function (hasil) {
                    if (hasil) {
                        var produk = dariKatalogLokal(hasil);
                        tambah(produk, produk.default_quantity);
                        el('cari').value = '';
                        el('hasil').innerHTML = '';

                        return;
                    }

                    if (sepertiBarcode(kata)) {
                        tampilkanPesan('Barcode belum dikenal di katalog offline. Daftarkan melalui menu Daftar Produk saat tersedia.', 'text-amber-600');
                        el('cari').value = '';

                        return;
                    }

                    cariLokal(kata);
                });
            }

            /** Satu pintu untuk kamera tertanam, scanner USB, dan input manual. */
            function tanganiPindai(kode) {
                var bersih = bersihkanKode(kode);
                if (! bersih) { return; }

                el('cari').value = bersih;
                cari(bersih);
            }

            function statusScanner(teks, aktif, galat) {
                el('status-scanner-kasir').textContent = teks || '';
                var indikator = el('indikator-scanner-kasir');
                indikator.textContent = aktif ? 'Aktif' : (galat ? 'Perlu izin' : 'Nonaktif');
                indikator.className = 'rounded-full px-2.5 py-1 text-[11px] font-semibold '
                    + (aktif ? 'bg-emerald-100 text-emerald-700' : (galat ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600'));
                el('kamera-aktifkan').disabled = !! aktif;
                el('kamera-nonaktifkan').disabled = ! aktif;
            }

            function siapkanScannerKasir() {
                if (kontrolScannerKasir || ! window.BarcodeScanner || ! window.BarcodeScanner.pasang) { return; }
                kontrolScannerKasir = window.BarcodeScanner.pasang({
                    kontainer: 'scanner-kasir',
                    sekaliPakai: false,
                    onDeteksi: tanganiPindai,
                    onStatus: function (teks) {
                        el('status-scanner-kasir').textContent = teks || 'Scanner siap.';
                    },
                });
            }

            function aktifkanKamera() {
                siapkanScannerKasir();
                if (! kontrolScannerKasir) {
                    statusScanner('Scanner tidak tersedia. Gunakan scanner USB atau pencarian.', false, true);
                    return;
                }
                statusScanner('Meminta akses kamera…', false, false);
                el('kamera-aktifkan').disabled = true;
                kontrolScannerKasir.mulai().then(function () {
                    statusScanner('Kamera aktif — arahkan barcode ke kamera.', true, false);
                }).catch(function (galat) {
                    var pesan = galat && galat.message ? galat.message : 'Kamera tidak dapat diaktifkan.';
                    statusScanner(pesan, false, true);
                    el('kamera-aktifkan').disabled = false;
                });
            }

            function nonaktifkanKamera() {
                if (kontrolScannerKasir) { kontrolScannerKasir.hentikan(); }
                statusScanner('Kamera dinonaktifkan. Scanner USB tetap dapat digunakan.', false, false);
            }

            /* Simpan transaksi                                              */
            /* ------------------------------------------------------------ */

            function tampilkanPesan(teks, kelas) {
                var kotak = el('pesan');
                kotak.className = 'text-xs ' + (kelas || 'text-slate-600');
                kotak.innerHTML = teks || '';
            }

            function muatanTransaksi() {
                return {
                    customer_name: el('pelanggan').value || null,
                    payment_method: el('metode').value,
                    discount: Number(el('diskon').value || 0),
                    paid_amount: Number(el('bayar').value || 0),
                    note: el('catatan').value || null,
                    items: keranjang.map(function (item) {
                        return {
                            product_id: item.id,
                            quantity: Number(item.quantity),
                            unit_price: Number(item.unit_price),
                            discount: 0,
                        };
                    }),
                };
            }

            function bersihkanForm() {
                keranjang = [];
                pelangganManual = false;
                el('pelanggan').value = '';
                el('diskon').value = 0;
                el('bayar').value = 0;
                el('catatan').value = '';
                el('cari').value = '';
                el('hasil').innerHTML = '';
                el('info-pindai').classList.add('hidden');
                gambarKeranjang();
            }

            function simpanOffline() {
                if (! window.PosOffline) {
                    tampilkanPesan('Mode offline belum siap.', 'text-red-600');

                    return;
                }

                window.PosOffline.antrikanTransaksi(muatanTransaksi()).then(function () {
                    tampilkanPesan('Transaksi disimpan lokal dan akan dikirim otomatis saat jaringan kembali.', 'text-amber-600');
                    bersihkanForm();
                });
            }

            function simpan() {
                if (keranjang.length === 0) {
                    tampilkanPesan('Keranjang masih kosong.', 'text-red-600');

                    return;
                }

                if (! navigator.onLine) {
                    simpanOffline();

                    return;
                }

                tampilkanPesan('Menyimpan transaksi...', 'text-slate-500');

                fetch(rute.checkout, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(muatanTransaksi()),
                })
                    .then(function (respons) {
                        return respons.json().then(function (isi) {
                            return { ok: respons.ok, isi: isi };
                        });
                    })
                    .then(function (hasil) {
                        if (! hasil.ok) {
                            tampilkanPesan(hasil.isi.message || 'Transaksi gagal disimpan.', 'text-red-600');

                            return;
                        }

                        tampilkanPesan('Transaksi ' + amanTeks(hasil.isi.invoice_no) + ' tersimpan. Kembali '
                            + rupiah(hasil.isi.change_amount)
                            + ' &middot; <a class="font-semibold underline" target="_blank" href="'
                            + hasil.isi.receipt_url + '">buka struk</a>', 'text-emerald-600');

                        bersihkanForm();
                    })
                    .catch(function () {
                        simpanOffline();
                    });
            }

            /* ------------------------------------------------------------ */
            /* Panel offline                                                 */
            /* ------------------------------------------------------------ */

            function gambarStatus(online, antrian, barcode) {
                el('status-jaringan').textContent = online ? 'Online' : 'Offline';
                el('status-jaringan').className = 'font-medium ' + (online ? 'text-emerald-600' : 'text-amber-600');

                if (antrian !== undefined && antrian !== null) {
                    el('jumlah-antrian').textContent = antrian;
                }

                if (barcode !== undefined && barcode !== null) {
                    el('jumlah-antrian-barcode').textContent = barcode;
                }
            }

            function perbaruiInfoKatalog() {
                if (! window.PosOffline) {
                    return;
                }

                window.PosOffline.infoKatalog().then(function (info) {
                    if (! info) {
                        el('info-katalog').textContent = 'Katalog offline belum diunduh.';

                        return;
                    }

                    var waktu = new Date(info.nilai);
                    el('info-katalog').textContent = info.jumlah + ' barang tersimpan untuk mode offline ('
                        + waktu.toLocaleString('id-ID') + ').';
                });
            }

            /* ------------------------------------------------------------ */
            /* ------------------------------------------------------------ */
            /* Scanner tertanam, pencarian, dan pintasan                      */
            /* ------------------------------------------------------------ */

            el('kamera-aktifkan').addEventListener('click', aktifkanKamera);
            el('kamera-nonaktifkan').addEventListener('click', nonaktifkanKamera);

            el('cari').addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    cari(el('cari').value);
                }
            });

            var jedaCari = null;
            el('cari').addEventListener('input', function () {
                var kata = el('cari').value;

                if (jedaCari) {
                    clearTimeout(jedaCari);
                }

                jedaCari = setTimeout(function () {
                    if (kata.trim().length >= 2 && ! sepertiBarcode(kata.trim())) {
                        cari(kata);
                    }
                }, 300);
            });

            el('diskon').addEventListener('input', hitung);
            el('bayar').addEventListener('input', hitung);

            // Nama transaksi bisa diketik manual bila scan bermasalah. Bila kasir
            // mengetik sendiri, isian manualnya tidak akan ditimpa pengisian otomatis.
            el('pelanggan').addEventListener('input', function () {
                pelangganManual = el('pelanggan').value.trim() !== '';
            });
            el('simpan').addEventListener('click', simpan);
            el('kosongkan').addEventListener('click', bersihkanForm);

            // Lipat/buka detail keranjang. Secara bawaan keranjang tidak menampilkan
            // semua barang; klik "Lihat detail" untuk membukanya ke bawah.
            (function () {
                var tombolKeranjang = el('keranjang-toggle');
                var detailKeranjang = el('keranjang-detail');
                var teksKeranjang = el('keranjang-toggle-teks');
                var ikonKeranjang = el('keranjang-toggle-ikon');

                if (tombolKeranjang && detailKeranjang) {
                    tombolKeranjang.addEventListener('click', function () {
                        var terbuka = detailKeranjang.classList.toggle('hidden') === false;
                        tombolKeranjang.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
                        if (teksKeranjang) {
                            teksKeranjang.textContent = terbuka ? 'Sembunyikan detail' : 'Lihat detail';
                        }
                        if (ikonKeranjang) {
                            ikonKeranjang.classList.toggle('rotate-180', terbuka);
                        }
                    });
                }
            })();

            el('kirim-antrian').addEventListener('click', function () {
                if (window.PosOffline) {
                    window.PosOffline.kirimAntrian();
                }
            });

            el('kirim-antrian-barcode').addEventListener('click', function () {
                if (window.PosOffline) {
                    window.PosOffline.kirimAntrianBarcode();
                }
            });

            el('segarkan-katalog').addEventListener('click', function () {
                if (window.PosOffline) {
                    window.PosOffline.segarkanKatalog().then(perbaruiInfoKatalog);
                }
            });

            // Alat pemindai USB: ketikan cepat + Enter langsung diproses,
            // walau kursor tidak berada di kolom pencarian.
            if (window.BarcodeScanner) {
                window.BarcodeScanner.pantauAlatPindai({ onDeteksi: tanganiPindai });
            }

            // F3 mengaktifkan kamera tertanam; F2 menyimpan transaksi.
            document.addEventListener('keydown', function (event) {
                if (event.key === 'F3') {
                    event.preventDefault();
                    aktifkanKamera();
                }

                if (event.key === 'F2') {
                    event.preventDefault();
                    simpan();
                }
            });

            window.addEventListener('pos-offline:status', function (event) {
                gambarStatus(event.detail.online, event.detail.antrian, event.detail.barcode);
            });

            window.addEventListener('pos-offline:antrian', function (event) {
                gambarStatus(navigator.onLine, event.detail.jumlah, null);
            });

            window.addEventListener('pos-offline:antrian-barcode', function (event) {
                gambarStatus(navigator.onLine, null, event.detail.jumlah);
            });

            window.addEventListener('pos-offline:katalog', perbaruiInfoKatalog);

            window.addEventListener('pos-offline:sinkron-selesai', function (event) {
                tampilkanPesan(event.detail.dikirim + ' transaksi terkirim, ' + event.detail.gagal + ' perlu ditinjau.',
                    event.detail.gagal ? 'text-amber-600' : 'text-emerald-600');
            });

            window.addEventListener('pos-offline:barcode-selesai', function (event) {
                tampilkanPesan(event.detail.dikirim + ' barcode terkirim ke server, ' + event.detail.gagal + ' gagal.',
                    event.detail.gagal ? 'text-amber-600' : 'text-emerald-600');
            });

            gambarStatus(navigator.onLine, 0, 0);
            gambarKeranjang();
            perbaruiInfoKatalog();
            siapkanScannerKasir();
            aktifkanKamera();

            window.addEventListener('pagehide', function () {
                if (kontrolScannerKasir) { kontrolScannerKasir.hentikan(); }
            });

            if (! window.matchMedia || window.matchMedia('(pointer: fine)').matches) {
                el('cari').focus();
            }
        })();
    </script>
@endsection
