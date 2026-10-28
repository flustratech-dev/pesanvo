import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import '../css/tur-pengenalan.css';

/**
 * Tur pengenalan untuk pendaftar baru.
 *
 * Dua hal yang membuat tur seperti ini gagal, dan keduanya dijaga di sini:
 *
 * 1. **Menunjuk elemen yang tidak ada.** Menu berbeda antar pengguna — "Saldo"
 *    hanya muncul untuk workspace pay as you go, dan sidebar berbeda di layar
 *    kecil. Langkah yang targetnya tidak ada disaring, bukan dibiarkan
 *    menunjuk sudut kosong layar.
 *
 * 2. **Muncul lagi setelah ditutup.** Penandanya dikirim SEKALI, dari mana pun
 *    tur berakhir — selesai, dilewati, atau ditutup dengan Escape. Tur yang
 *    kembali muncul terbaca sebagai kerusakan, dan orang berhenti mempercayai
 *    antarmuka yang tampak tidak mengingat apa pun.
 */
function mulaiTur() {
    const data = document.querySelector('script[data-tur-pengenalan]');

    if (! data) return;

    let konfigurasi;

    try {
        konfigurasi = JSON.parse(data.textContent);
    } catch (e) {
        return;
    }

    const langkah = (konfigurasi.langkah || [])
        .filter((l) => ! l.target || document.querySelector(l.target))
        .map((l) => ({
            // Tanpa `element`, driver.js menampilkannya sebagai kotak di tengah
            // layar — bentuk yang benar untuk langkah pembuka dan penutup.
            element: l.target || undefined,
            popover: { title: l.judul, description: l.isi },
        }));

    if (langkah.length === 0) return;

    let sudahDicatat = false;

    const catat = (status) => {
        if (sudahDicatat) return;
        sudahDicatat = true;

        fetch(konfigurasi.ackUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ status, version: konfigurasi.versi }),
            // Supaya pencatatannya tetap terkirim walau tur ditutup bersamaan
            // dengan berpindah halaman.
            keepalive: true,
        }).catch(() => {});
    };

    const tur = driver({
        showProgress: true,
        allowClose: true,
        overlayOpacity: 0.6,
        nextBtnText: 'Lanjut',
        prevBtnText: 'Kembali',
        doneBtnText: 'Selesai',
        progressText: '{{current}} dari {{total}}',
        steps: langkah,
        onDoneClick: () => {
            catat('completed');
            tur.destroy();
        },
        // Menangkap SEMUA cara tur berakhir — tombol silang, Escape, dan klik
        // di luar. `onDoneClick` di atas sudah mencatat lebih dulu, dan
        // `sudahDicatat` menjaga supaya tidak terkirim dua kali.
        onDestroyed: () => catat('skipped'),
    });

    tur.drive();
}

document.addEventListener('DOMContentLoaded', mulaiTur);
