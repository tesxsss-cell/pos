(() => {
'use strict';
const sidebar=document.getElementById('app-sidebar'),overlay=document.getElementById('app-sidebar-overlay'),workspace=document.getElementById('app-workspace'),opener=document.querySelector('[data-sidebar-open]');
let lastFocus=null;
function toggle(open){
 if(!sidebar||!overlay)return;
 if(open)lastFocus=document.activeElement;
 sidebar.classList.toggle('is-open',open);overlay.hidden=!open;document.body.classList.toggle('drawer-visible',open);opener?.setAttribute('aria-expanded',String(open));
 if(workspace)workspace.inert=open;
 if(open)sidebar.querySelector('[data-sidebar-close]')?.focus();else if(lastFocus?.isConnected)lastFocus.focus();
}
opener?.addEventListener('click',()=>toggle(true));document.querySelector('[data-sidebar-close]')?.addEventListener('click',()=>toggle(false));overlay?.addEventListener('click',()=>toggle(false));
addEventListener('keydown',event=>{
 if(!sidebar?.classList.contains('is-open'))return;
 if(event.key==='Escape'){toggle(false);return;}
 if(event.key==='Tab'){
  const links=[...sidebar.querySelectorAll('a[href],button,input,select')].filter(el=>!el.disabled&&el.getClientRects().length);const first=links[0],last=links.at(-1);
  if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
 }
});
matchMedia('(min-width:1024px)').addEventListener('change',event=>{if(event.matches)toggle(false);});
const label=document.getElementById('network-status');
function status(){if(!label)return;label.classList.toggle('is-disconnected',!navigator.onLine);label.lastElementChild.textContent=navigator.onLine?'Online':'Terputus';label.title=navigator.onLine?'Aplikasi memerlukan koneksi ke server.':'Tidak ada mode offline. Transaksi belum dapat dikonfirmasi server.';}
addEventListener('online',status);addEventListener('offline',status);status();
// Pensiunkan SW lama; jangan mendaftarkan PWA atau menghapus antrean historis.
if('serviceWorker' in navigator)navigator.serviceWorker.getRegistrations().then(async registrations=>{
 for(const registration of registrations){const worker=registration.active||registration.waiting||registration.installing;if(worker&&new URL(worker.scriptURL).pathname==='/sw.js'){await registration.update().catch(()=>{});await registration.unregister();}}
}).catch(()=>{});
if('caches' in window)caches.keys().then(keys=>Promise.all(keys.filter(key=>/^(kopdes-|pos-)/.test(key)).map(key=>caches.delete(key)))).catch(()=>{});
})();
