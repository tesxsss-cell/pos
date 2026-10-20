/* POS online. Tidak ada IndexedDB, localStorage, antrean, atau auto-sync. */
(() => {
'use strict';
const cfg=window.POS_ONLINE_CONFIG, byId=id=>document.getElementById(id);
let cart=[], pending=null, busy=false;
const money=n=>'Rp '+Number(n).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:2});
function message(text){byId('pos-message').textContent=text;}
function uuid(){if(crypto.randomUUID)return crypto.randomUUID();const b=crypto.getRandomValues(new Uint8Array(16));b[6]=(b[6]&15)|64;b[8]=(b[8]&63)|128;return [...b].map((v,i)=>([4,6,8,10].includes(i)?'-':'')+v.toString(16).padStart(2,'0')).join('');}
function total(){return cart.reduce((sum,p)=>sum+p.quantity*p.sell_price,0);}
function lock(locked){
 document.querySelectorAll('#checkout-form input,#checkout-form select,#checkout-form button,#search-form input,#search-form button,#cart-body button,#cart-body input,#close-shift-form input,#close-shift-form button').forEach(el=>el.disabled=locked);
 document.querySelectorAll('#camera-start,#clear-cart').forEach(el=>el.disabled=locked);
 byId('retry-button').classList.toggle('hidden',!pending||busy);
}
function draw(){
 const body=byId('cart-body');body.replaceChildren();
 if(!cart.length){const tr=body.insertRow();const td=tr.insertCell();td.colSpan=5;td.textContent='Belum ada barang.';td.className='py-4 text-slate-500';}
 cart.forEach((p,index)=>{
  const tr=body.insertRow();tr.className='border-t';
  const nameCell=tr.insertCell();nameCell.textContent=p.name;nameCell.dataset.label='Barang';nameCell.className='cart-product-name';const priceCell=tr.insertCell();priceCell.textContent=money(p.sell_price);priceCell.dataset.label='Harga';
  const input=document.createElement('input');input.type='number';input.min=1;input.max=p.stock;input.value=p.quantity;input.className='field w-20';input.setAttribute('aria-label','Jumlah '+p.name);
  input.addEventListener('change',()=>{if(pending)return;const quantity=Number(input.value);if(!Number.isInteger(quantity)||quantity<1||quantity>p.stock){message('Jumlah melebihi stok atau tidak valid.');input.value=p.quantity;return;}p.quantity=quantity;draw();});const quantityCell=tr.insertCell();quantityCell.dataset.label='Jumlah';quantityCell.append(input);
  const subCell=tr.insertCell();subCell.dataset.label='Subtotal';subCell.textContent=money(p.quantity*p.sell_price);
  const del=document.createElement('button');del.type='button';del.textContent='Hapus';del.className='text-red-700 underline';del.addEventListener('click',()=>{if(pending)return;cart.splice(index,1);draw();});const actionCell=tr.insertCell();actionCell.className='cart-actions';actionCell.append(del);
 });byId('cart-total').textContent=money(total());byId('cart-count').textContent=cart.length+' barang';updateChange();lock(Boolean(pending));
}
function add(product){
 if(pending||busy)return;
 const old=cart.find(p=>p.id===product.id);if((old?.quantity||0)+1>product.stock){message('Stok tidak cukup.');return;}
 if(old)old.quantity++;else cart.push({...product,quantity:1});draw();byId('product-search').value='';byId('search-results').replaceChildren();byId('product-search').focus();
}
let searching=false;
async function search(term,page=1,targetId='search-results',choose=add,auto=true){
 const response=await fetch(cfg.lookup+'?q='+encodeURIComponent(term)+'&page='+page,{headers:{Accept:'application/json'},cache:'no-store'});
 if(!response.ok)throw new Error('Pencarian gagal. Periksa koneksi atau sesi login.');
 const result=await response.json(),data=result.data,target=byId(targetId);target.replaceChildren();
 const exact=data.find(p=>p.barcode===term||p.matched_barcode===term||p.sku===term);
 if(auto&&exact){choose(exact);return;}
 if(!data.length){target.textContent='Barang tidak ditemukan. Tambahkan barcode jika kode belum terdaftar.';if(auto){const link=document.createElement('a');const url=new URL(cfg.registerPage,location.origin);url.searchParams.set('barcode',term);link.href=url;link.className='text-link';link.textContent=' Buka halaman Daftarkan barcode';target.append(link);}return;}
 const list=document.createElement('div');list.className='search-result-list';target.append(list);
 data.forEach(p=>{const button=document.createElement('button');button.type='button';button.textContent=p.name+' · '+money(p.sell_price)+' · stok '+p.stock+' '+p.unit;button.disabled=auto&&p.stock<=0;button.addEventListener('click',()=>{if(!pending)choose(p);});list.append(button);});
 const nav=document.createElement('nav');nav.className='table-pagination search-pager';nav.setAttribute('aria-label','Halaman hasil pencarian');const info=document.createElement('span');info.textContent='Hal. '+result.current_page+' / '+result.last_page+' · '+result.total+' barang · 10 / halaman';nav.append(info);
 for(const [text,n,disabled] of [['Sebelumnya',page-1,page<=1],['Berikutnya',page+1,page>=result.last_page]]){const btn=document.createElement('button');btn.type='button';btn.className='page-button';btn.textContent=text;btn.disabled=disabled;btn.onclick=()=>search(term,n,targetId,choose,auto).catch(e=>message(e.message));nav.append(btn);}target.append(nav);
}
byId('search-form').addEventListener('submit',async event=>{event.preventDefault();if(pending||busy||searching)return;const term=byId('product-search').value.trim();if(!term)return;searching=true;try{await search(term);}catch(e){message(e.message);}finally{searching=false;}});
byId('cart-toggle').onclick=()=>{const detail=byId('cart-detail');detail.hidden=!detail.hidden;byId('cart-toggle').setAttribute('aria-expanded',String(!detail.hidden));byId('cart-toggle-label').textContent=detail.hidden?'Lihat detail':'Tutup detail';};
byId('clear-cart').onclick=()=>{if(pending||busy)return;if(cart.length&&confirm('Kosongkan seluruh keranjang?')){cart=[];draw();}};
function updateChange(){const amount=Number(byId('paid-amount').value||0);byId('change-preview').textContent=byId('payment-method').value==='tunai'?money(Math.max(0,amount-total())):'Rp 0 (non-tunai)';}
byId('paid-amount').addEventListener('input',updateChange);
// Same embedded camera scanner as the first ZIP; decoding is local, saving stays online.
let camera=null;
const detected=code=>{if(pending||busy)return;byId('product-search').value=String(code).trim();byId('search-form').requestSubmit();};
byId('camera-start').onclick=async()=>{if(pending||busy)return;const start=byId('camera-start');start.disabled=true;try{if(!window.BarcodeScanner)throw new Error('Pemindai belum tersedia. Gunakan scanner USB atau pencarian.');camera??=BarcodeScanner.pasang({kontainer:'scanner-kasir',sekaliPakai:false,onDeteksi:detected,onStatus:t=>byId('scanner-status').textContent=t});await camera.mulai();byId('scanner-state').textContent='Aktif';byId('camera-stop').disabled=false;}catch(e){byId('scanner-status').textContent=e.message;byId('scanner-state').textContent='Perlu izin';start.disabled=false;}};
byId('camera-stop').onclick=()=>{camera?.hentikan();byId('scanner-state').textContent='Nonaktif';byId('scanner-status').textContent='Kamera berhenti. Scanner USB tetap dapat digunakan.';byId('camera-stop').disabled=true;byId('camera-start').disabled=Boolean(pending);};
// USB scan works in the search input; do not intercept payment/quantity typing.
if(window.BarcodeScanner)BarcodeScanner.pantauAlatPindai({onDeteksi:detected});
addEventListener('pagehide',()=>camera?.hentikan());
byId('payment-method').addEventListener('change',()=>{const cash=byId('payment-method').value==='tunai';byId('cash-field').classList.toggle('hidden',!cash);byId('noncash-field').classList.toggle('hidden',cash);byId('paid-amount').required=cash;byId('payment-confirmed').required=!cash;byId('payment-confirmed').checked=false;updateChange();});
async function send(){
 if(!pending||busy)return;
 if(!navigator.onLine){message('Jaringan terputus. Belum ada konfirmasi server; tidak disimpan offline. Sambungkan jaringan lalu periksa/kirim ulang.');lock(true);return;}
 busy=true;lock(true);message('Meminta konfirmasi server…');
 const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),20000);
 try{
  const response=await fetch(cfg.checkout,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':cfg.csrf},body:JSON.stringify(pending),signal:controller.signal});
  let data;try{data=await response.json();}catch{throw new Error('Respons server tidak dapat dibaca.');}
  if(!response.ok){
   // 422 berarti permintaan ditolak/di-rollback; boleh koreksi keranjang.
   if(response.status===422){pending=null;message(data.message+' '+Object.values(data.errors||{}).flat().join(' '));return;}
   throw new Error(data.message||'Respons belum dapat dikonfirmasi.');
  }
  pending=null;cart=[];byId('checkout-form').reset();byId('payment-method').dispatchEvent(new Event('change'));draw();
  message('Tersimpan: '+data.invoice_no+'. Total '+money(data.total)+'. Kembalian '+money(data.change_amount)+'. ');
  const link=document.createElement('a');link.href=data.receipt_url;link.textContent='Buka / cetak struk';link.target='_blank';link.rel='noopener';link.className='font-semibold text-brand-700 underline';byId('pos-message').append(link);
  const cash=byId('shift-expected');if(cash)cash.textContent=money(data.expected_cash);
  const history=document.createElement('a');history.href=cfg.history;history.className='text-link';history.textContent=' Lihat riwayat transaksi';byId('pos-message').append(history);
 }catch(error){message('Status transaksi BELUM DIKETAHUI. Jangan buat transaksi baru. Sambungkan jaringan lalu gunakan Periksa / kirim ulang; ID yang sama mencegah transaksi ganda. ('+error.message+')');}
 finally{clearTimeout(timeout);busy=false;lock(Boolean(pending));}
}
byId('checkout-form').addEventListener('submit',event=>{
 event.preventDefault();if(busy||pending)return;
 if(!navigator.onLine){message('Tidak dapat menyimpan: jaringan terputus. Tidak ada mode offline.');return;}
 if(!cart.length){message('Keranjang kosong.');return;}
 const method=byId('payment-method').value,paid=Number(byId('paid-amount').value||0);
 if(method==='tunai'&&paid<total()){message('Pembayaran tunai kurang.');return;}
 if(method!=='tunai'&&!byId('payment-confirmed').checked){message('Periksa pembayaran non-tunai dahulu.');return;}
 pending={request_id:uuid(),cashier_shift_id:cfg.shift,customer_name:byId('customer-name').value||null,payment_method:method,paid_amount:method==='tunai'?paid:null,note:byId('sale-note').value||null,items:cart.map(p=>({product_id:p.id,quantity:p.quantity,expected_price:p.sell_price}))};
 if(method!=='tunai')pending.payment_confirmed=true;
 send();
});
byId('retry-button').addEventListener('click',send);
byId('close-shift-form').addEventListener('submit',event=>{if(pending||cart.length){event.preventDefault();message('Selesaikan status transaksi dan kosongkan keranjang sebelum tutup shift.');return;}if(!confirm('Sudah menghitung kas fisik dan menyelesaikan semua pembatalan? Tutup shift?'))event.preventDefault();});
document.addEventListener('click',event=>{const link=event.target.closest('a[href]');if(!link||link.target==='_blank'||(!pending&&!cart.length))return;const url=new URL(link.href,location.href);if(url.origin===location.origin&&(url.pathname!==location.pathname||url.search!==location.search)){event.preventDefault();message('Selesaikan transaksi atau kosongkan keranjang sebelum pindah halaman.');}});
byId('refresh-shift').addEventListener('click',event=>{if(pending||cart.length){event.preventDefault();message('Selesaikan transaksi sebelum menyegarkan halaman.');}});
addEventListener('beforeunload',event=>{if(pending||cart.length){event.preventDefault();event.returnValue='';}});
draw();
})();
