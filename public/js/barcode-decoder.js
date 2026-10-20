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
