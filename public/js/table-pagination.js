/* Server tables use Laravel pagination; editable in-memory rows use 10-row pages.
   Hidden form rows stay enabled and are included when the entire document is saved. */
(() => {'use strict';
const states=new WeakMap();
function attach(table){
 if(states.has(table)||!table.tBodies.length)return;
 let region=table.parentElement;
 if(!region.matches('.table-scroll,.overflow-x-auto,.table-container')){const wrap=document.createElement('div');wrap.className='table-container';table.before(wrap);wrap.append(table);region=wrap;}
 region.setAttribute('tabindex','0');region.setAttribute('role','region');region.setAttribute('aria-label','Tabel data, geser ke samping untuk kolom lainnya');
 const hint=document.createElement('p');hint.className='table-overflow-hint no-print';hint.textContent='Geser tabel ke samping untuk melihat semua kolom.';region.after(hint);
 const resize=()=>{hint.hidden=region.scrollWidth<=region.clientWidth+2;};new ResizeObserver(resize).observe(region);
 if(table.hasAttribute('data-server-table')){const serverNav=region.querySelector(':scope > [data-server-pagination]');if(serverNav)hint.after(serverNav);states.set(table,{});resize();return;}
 const nav=document.createElement('nav');nav.className='table-pagination no-print';nav.setAttribute('aria-label','Pagination tabel');
 const summary=document.createElement('span');summary.className='pagination-summary';const actions=document.createElement('div');actions.className='pagination-actions';
 const prev=document.createElement('button'),next=document.createElement('button'),label=document.createElement('span');prev.type=next.type='button';prev.className=next.className='page-button';prev.textContent='Sebelumnya';next.textContent='Berikutnya';actions.append(prev,label,next);nav.append(summary,actions);hint.after(nav);
 const state={page:1,count:0};states.set(table,state);
 function render(){const rows=[...table.tBodies[0].rows].filter(row=>!(row.cells.length===1&&row.cells[0].colSpan>1));const pages=Math.max(1,Math.ceil(rows.length/10));if(rows.length>state.count&&table.closest('form'))state.page=pages;state.count=rows.length;state.page=Math.min(Math.max(1,state.page),pages);rows.forEach((row,i)=>row.classList.toggle('page-hidden',i<(state.page-1)*10||i>=state.page*10));summary.textContent=(rows.length?(state.page-1)*10+1:0)+'–'+Math.min(state.page*10,rows.length)+' dari '+rows.length+' data · 10 / halaman';label.textContent='Hal. '+state.page+' / '+pages;prev.disabled=state.page===1;next.disabled=state.page===pages;resize();}
 prev.onclick=()=>{state.page--;render();};next.onclick=()=>{state.page++;render();};
 new MutationObserver(render).observe(table.tBodies[0],{childList:true,subtree:true});render();
 // Native validation must reveal an invalid field on another editable row page.
 table.closest('form')?.addEventListener('invalid',e=>{const rows=[...table.tBodies[0].rows];const i=rows.indexOf(e.target.closest('tr'));if(i>=0){state.page=Math.floor(i/10)+1;render();}},{capture:true});
}
function scan(){document.querySelectorAll('.app-page table').forEach(attach)}scan();
})();
