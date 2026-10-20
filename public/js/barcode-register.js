/* Camera registration page only. Product/price stays server-controlled; no offline queue. */
(() => {
'use strict';
const cfg=window.BARCODE_PAGE_CONFIG, id=name=>document.getElementById(name);
if(!cfg||!id('barcode-dialog'))return;
const dialog=id('barcode-dialog'),form=id('barcode-registration-form'),code=id('registration-code');
let selected=null,scanner=null,saving=false,opening=false,opener=null,generation=0;
const money=value=>'Rp '+Number(value).toLocaleString('id-ID');
function setMessage(text){id('registration-message').textContent=text;}
function lock(value){saving=value;form.querySelectorAll('input,button').forEach(el=>el.disabled=value);id('barcode-dialog-close').disabled=value;}
function close(){if(saving)return;generation++;scanner?.hentikan();dialog.close();}
dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
dialog.addEventListener('close',()=>{generation++;scanner?.hentikan();document.body.classList.remove('barcode-modal-open');opener?.focus();});
id('barcode-dialog-close').onclick=close;id('registration-cancel').onclick=close;
async function open(button){
 if(saving||opening||dialog.open)return;
 const product=cfg.products[button.dataset.product];if(!product)return;
 opener=button;selected=product;setMessage('');code.value=cfg.initialCode||'';
 id('registration-product-name').textContent=product.name;id('registration-product-sku').textContent=product.sku;
 id('registration-product-price').textContent=money(product.price);id('registration-product-stock').textContent=product.stock+' '+product.unit;
 dialog.showModal();document.body.classList.add('barcode-modal-open');
 const token=++generation;opening=true;
 try{
  if(!window.BarcodeScanner)throw new Error('Scanner belum tersedia. Ketik atau tembak kode pada kolom barcode.');
  scanner??=BarcodeScanner.pasang({kontainer:'registration-scanner',sekaliPakai:true,onDeteksi:value=>{if(!dialog.open||saving)return;code.value=String(value).trim();code.dispatchEvent(new Event('input'));id('registration-camera-status').textContent='Barcode terbaca. Periksa kode lalu tekan Simpan barcode.';code.focus();},onStatus:text=>{id('registration-camera-status').textContent=text;}});
  await scanner.mulai();
 }catch(error){if(dialog.open&&token===generation)id('registration-camera-status').textContent=error.message+' Kolom ketik/scanner USB tetap dapat digunakan.';}
 finally{opening=false;if(token!==generation||!dialog.open)scanner?.hentikan();}
}
document.querySelectorAll('.barcode-open').forEach(button=>button.addEventListener('click',()=>open(button)));
// Keyboard-wedge scanners type directly into this input; Enter never saves unreviewed scans.
code.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();id('registration-save').focus();}});
form.addEventListener('submit',async event=>{
 event.preventDefault();if(saving||!selected)return;if(!navigator.onLine){setMessage('Jaringan terputus. Sambungkan jaringan sebelum menyimpan barcode.');return;}
 const value=code.value.trim();if(!value||!form.reportValidity())return;
 scanner?.hentikan();lock(true);setMessage('Menyimpan barcode di server…');
 const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),20000);
 try{
  const response=await fetch(cfg.saveUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':cfg.csrf},body:JSON.stringify({barcode:value,product_id:selected.id}),signal:controller.signal});
  let result;try{result=await response.json();}catch{throw new Error('Respons server tidak dapat dibaca. Periksa koneksi/sesi login lalu coba lagi dengan kode yang sama.');}
  if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Barcode gagal disimpan.');
  if(!selected.codes.includes(result.barcode))selected.codes.push(result.barcode);
  document.querySelectorAll('[data-code-list]').forEach(list=>{if(Number(list.dataset.codeList)!==Number(selected.id))return;list.replaceChildren();selected.codes.forEach(value=>{const tag=document.createElement('span');tag.textContent=value;list.append(tag);});});
  document.querySelectorAll('.barcode-open').forEach(button=>{if(Number(button.dataset.product)===Number(selected.id))button.textContent='Tambah barcode';});
  const feedback=id('register-feedback');feedback.hidden=false;feedback.textContent=result.message+' Barang: '+selected.name+'. Kode: '+result.barcode;
  cfg.initialCode='';lock(false);close();feedback.scrollIntoView({block:'nearest'});
 }catch(error){setMessage(error.name==='AbortError'?'Server belum mengonfirmasi. Periksa jaringan lalu simpan ulang kode yang sama; kode yang sama pada barang yang sama tidak diduplikasi.':error.message);}
 finally{clearTimeout(timeout);lock(false);}
});
addEventListener('pagehide',()=>{generation++;scanner?.hentikan();});
addEventListener('beforeunload',event=>{if(saving){event.preventDefault();event.returnValue='';}});
})();
