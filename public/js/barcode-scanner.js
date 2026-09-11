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
    var KONFIRMASI = 2;         // jumlah pembacaan sama berturut-turut sebelum diterima
    var JEDA_ULANG = 2000;      // ms: cegah kode sama terbaca berkali-kali
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
        var kodeTerakhir = '', waktuTerakhir = 0, kodeSebelum = '', hitungSama = 0;

        function lapor(teks) { if (typeof setStatus === 'function') { setStatus(teks); } }

        function terima(nilai, format) {
            var kode = bersihkanKode(nilai);
            if (! kode) { return; }
            var sekarang = Date.now();
            if (kode === kodeTerakhir && (sekarang - waktuTerakhir) < JEDA_ULANG) { return; }
            if (kode === kodeSebelum) { hitungSama += 1; } else { kodeSebelum = kode; hitungSama = 1; }
            if (hitungSama < KONFIRMASI) { return; }
            if (! cekDigitEANUPC(kode)) { hitungSama = 0; kodeSebelum = ''; return; }
            kodeTerakhir = kode; waktuTerakhir = sekarang; hitungSama = 0; kodeSebelum = '';
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
                    pakaiOffline = !! window.OfflineBarcode && (! detector || ! adaRitel);
                }).catch(function () {
                    detector = buat(null);
                    pakaiOffline = !! window.OfflineBarcode && ! detector;
                });
            }
            if (adaDetector) {
                detector = buat(null);
                pakaiOffline = !! window.OfflineBarcode && ! detector;
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
                : { facingMode: { ideal: 'environment' } };
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
            kodeSebelum = ''; hitungSama = 0;
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
