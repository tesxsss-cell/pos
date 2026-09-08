/**
 * barcode-scanner.js
 * Pemindaian barcode/QR memakai kamera peramban (HF-04).
 *
 * Memakai API bawaan peramban: BarcodeDetector + getUserMedia, sehingga tidak
 * perlu pustaka pihak ketiga maupun koneksi internet.
 *
 * Catatan penting:
 *  - Kamera hanya bisa diakses lewat HTTPS atau http://localhost.
 *  - BarcodeDetector tersedia di Chrome/Edge/Opera desktop & Chrome Android.
 *    Pada Safari/Firefox fungsi mundur otomatis ke pemindai mode keyboard.
 *
 * Pemakaian:
 *   BarcodeScanner.mulai({ onDeteksi: function (kode) { ... } });
 */
(function () {
    'use strict';

    var didukung = 'BarcodeDetector' in window
        && navigator.mediaDevices
        && typeof navigator.mediaDevices.getUserMedia === 'function';

    var FORMAT = [
        'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128',
        'code_39', 'code_93', 'itf', 'codabar', 'qr_code',
    ];

    var lapisan = null;
    var video = null;
    var aliran = null;
    var detektor = null;
    var berjalan = false;
    var kodeTerakhir = '';
    var waktuTerakhir = 0;
    var daftarKamera = [];
    var indeksKamera = 0;
    var panggilBalik = null;
    var elemenPesan = null;

    function gaya(elemen, aturan) {
        Object.keys(aturan).forEach(function (kunci) {
            elemen.style[kunci] = aturan[kunci];
        });

        return elemen;
    }

    function buatTombol(teks, warna) {
        var tombol = document.createElement('button');
        tombol.type = 'button';
        tombol.textContent = teks;

        return gaya(tombol, {
            background: warna,
            color: '#fff',
            border: 'none',
            borderRadius: '8px',
            padding: '10px 16px',
            fontSize: '14px',
            fontWeight: '600',
            cursor: 'pointer',
        });
    }

    /** Bunyi "beep" singkat sebagai tanda pindai berhasil. */
    function bunyikanBeep() {
        try {
            var Konteks = window.AudioContext || window.webkitAudioContext;

            if (! Konteks) {
                return;
            }

            var konteks = new Konteks();
            var osilator = konteks.createOscillator();
            var penguat = konteks.createGain();

            osilator.type = 'square';
            osilator.frequency.value = 1200;
            penguat.gain.value = 0.06;
            osilator.connect(penguat).connect(konteks.destination);
            osilator.start();

            setTimeout(function () {
                osilator.stop();
                konteks.close();
            }, 110);
        } catch (error) {
            /* bunyi bersifat opsional */
        }
    }

    function bangunLapisan() {
        lapisan = gaya(document.createElement('div'), {
            position: 'fixed',
            inset: '0',
            zIndex: '9999',
            background: 'rgba(15,23,42,0.92)',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '12px',
            padding: '16px',
        });

        var judul = document.createElement('p');
        judul.textContent = 'Arahkan barcode ke dalam kotak';
        gaya(judul, { color: '#e2e8f0', fontSize: '14px', margin: '0' });

        var bingkai = gaya(document.createElement('div'), {
            position: 'relative',
            width: '100%',
            maxWidth: '520px',
            aspectRatio: '4 / 3',
            borderRadius: '14px',
            overflow: 'hidden',
            background: '#000',
            boxShadow: '0 0 0 2px rgba(148,163,184,0.4)',
        });

        video = document.createElement('video');
        video.setAttribute('playsinline', 'true');
        video.muted = true;
        gaya(video, { width: '100%', height: '100%', objectFit: 'cover' });

        var pemandu = gaya(document.createElement('div'), {
            position: 'absolute',
            top: '25%',
            left: '8%',
            width: '84%',
            height: '50%',
            border: '3px solid rgba(16,185,129,0.9)',
            borderRadius: '10px',
            pointerEvents: 'none',
        });

        bingkai.appendChild(video);
        bingkai.appendChild(pemandu);

        elemenPesan = document.createElement('p');
        gaya(elemenPesan, { color: '#facc15', fontSize: '13px', margin: '0', minHeight: '18px', textAlign: 'center' });

        var barisTombol = gaya(document.createElement('div'), {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '8px',
            justifyContent: 'center',
        });

        var tombolGanti = buatTombol('Ganti kamera', '#334155');
        tombolGanti.addEventListener('click', gantiKamera);

        var tombolLampu = buatTombol('Lampu', '#334155');
        tombolLampu.addEventListener('click', alihkanLampu);

        var tombolTutup = buatTombol('Tutup', '#dc2626');
        tombolTutup.addEventListener('click', hentikan);

        barisTombol.appendChild(tombolGanti);
        barisTombol.appendChild(tombolLampu);
        barisTombol.appendChild(tombolTutup);

        lapisan.appendChild(judul);
        lapisan.appendChild(bingkai);
        lapisan.appendChild(elemenPesan);
        lapisan.appendChild(barisTombol);

        lapisan.addEventListener('click', function (event) {
            if (event.target === lapisan) {
                hentikan();
            }
        });

        document.body.appendChild(lapisan);
    }

    function tampilkanPesan(teks) {
        if (elemenPesan) {
            elemenPesan.textContent = teks || '';
        }
    }

    function nyalakanKamera() {
        var pilihan = { audio: false, video: { facingMode: { ideal: 'environment' } } };

        if (daftarKamera.length > 1 && daftarKamera[indeksKamera]) {
            pilihan.video = { deviceId: { exact: daftarKamera[indeksKamera].deviceId } };
        }

        return navigator.mediaDevices.getUserMedia(pilihan).then(function (media) {
            aliran = media;
            video.srcObject = media;

            return video.play();
        });
    }

    function matikanKamera() {
        if (aliran) {
            aliran.getTracks().forEach(function (jalur) {
                jalur.stop();
            });
            aliran = null;
        }
    }

    function gantiKamera() {
        if (daftarKamera.length < 2) {
            tampilkanPesan('Hanya satu kamera yang terdeteksi.');

            return;
        }

        indeksKamera = (indeksKamera + 1) % daftarKamera.length;
        matikanKamera();
        nyalakanKamera().catch(function (error) {
            tampilkanPesan('Kamera tidak dapat dibuka: ' + error.message);
        });
    }

    function alihkanLampu() {
        if (! aliran) {
            return;
        }

        var jalur = aliran.getVideoTracks()[0];
        var kemampuan = jalur.getCapabilities ? jalur.getCapabilities() : {};

        if (! kemampuan.torch) {
            tampilkanPesan('Perangkat ini tidak mendukung lampu kamera.');

            return;
        }

        var nyala = ! (jalur.getSettings().torch === true);
        jalur.applyConstraints({ advanced: [{ torch: nyala }] }).catch(function () {
            tampilkanPesan('Lampu kamera gagal diaktifkan.');
        });
    }

    function pindaiTerus() {
        if (! berjalan) {
            return;
        }

        detektor
            .detect(video)
            .then(function (hasil) {
                if (hasil.length > 0) {
                    var kode = String(hasil[0].rawValue || '').trim();
                    var sekarang = Date.now();
                    var kembar = kode === kodeTerakhir && (sekarang - waktuTerakhir) < 1500;

                    if (kode !== '' && ! kembar) {
                        kodeTerakhir = kode;
                        waktuTerakhir = sekarang;
                        bunyikanBeep();
                        tampilkanPesan('Terbaca: ' + kode);

                        if (typeof panggilBalik === 'function') {
                            panggilBalik(kode);
                        }
                    }
                }
            })
            .catch(function () {
                /* satu bingkai gagal dibaca, lanjutkan saja */
            })
            .then(function () {
                if (berjalan) {
                    setTimeout(pindaiTerus, 180);
                }
            });
    }

    function tanganiTombolKeyboard(event) {
        if (event.key === 'Escape') {
            hentikan();
        }
    }

    /** Membuka jendela pemindai. Mengembalikan Promise yang gagal bila tidak didukung. */
    function mulai(opsi) {
        opsi = opsi || {};
        panggilBalik = opsi.onDeteksi;

        if (berjalan) {
            return Promise.resolve();
        }

        if (! didukung) {
            var pesan = 'Peramban ini belum mendukung pemindaian kamera. '
                + 'Gunakan Chrome/Edge (desktop atau Android), atau pakai alat pemindai mode keyboard.';

            if (typeof opsi.onGagal === 'function') {
                opsi.onGagal(pesan);
            } else {
                alert(pesan);
            }

            return Promise.reject(new Error(pesan));
        }

        if (! window.isSecureContext) {
            var pesanAman = 'Kamera hanya dapat diakses melalui HTTPS atau http://localhost.';

            if (typeof opsi.onGagal === 'function') {
                opsi.onGagal(pesanAman);
            } else {
                alert(pesanAman);
            }

            return Promise.reject(new Error(pesanAman));
        }

        berjalan = true;
        bangunLapisan();
        document.addEventListener('keydown', tanganiTombolKeyboard);
        detektor = new window.BarcodeDetector({ formats: opsi.formats || FORMAT });

        return nyalakanKamera()
            .then(function () {
                return navigator.mediaDevices.enumerateDevices();
            })
            .then(function (perangkat) {
                daftarKamera = perangkat.filter(function (item) {
                    return item.kind === 'videoinput';
                });

                pindaiTerus();
            })
            .catch(function (error) {
                var keterangan = error.name === 'NotAllowedError'
                    ? 'Izin kamera ditolak. Aktifkan izin kamera pada peramban lalu coba lagi.'
                    : 'Kamera tidak dapat dibuka: ' + error.message;

                tampilkanPesan(keterangan);

                if (typeof opsi.onGagal === 'function') {
                    opsi.onGagal(keterangan);
                }
            });
    }

    /** Menutup jendela pemindai dan melepas kamera. */
    function hentikan() {
        berjalan = false;
        document.removeEventListener('keydown', tanganiTombolKeyboard);
        matikanKamera();

        if (lapisan && lapisan.parentNode) {
            lapisan.parentNode.removeChild(lapisan);
        }

        lapisan = null;
        video = null;
        elemenPesan = null;
        kodeTerakhir = '';
    }

    window.addEventListener('pagehide', hentikan);

    window.BarcodeScanner = {
        didukung: didukung,
        mulai: mulai,
        hentikan: hentikan,
    };
})();
