/**
 * Tether Brew — PWA Install Manager
 * Mengelola deteksi instalasi PWA, tombol Install di navbar/topbar, dan popup banner install.
 * 
 * Aturan Validasi:
 * Ketika user sudah install (standalone, iOS standalone, appinstalled, atau tanda storage),
 * maka TIDAK AKAN ADA popup ataupun tombol untuk install app.
 */
(function() {
    'use strict';

    let deferredPrompt = null;

    /**
     * Memeriksa apakah web app sedang dibuka sebagai PWA yang terinstall
     * atau pernah diinstall sebelumnya di perangkat ini.
     */
    function isPWAInstalled() {
        // 1. Cek mode standalone display (Chrome, Edge, Samsung Internet, dll.)
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            return true;
        }

        // 2. Cek mode minimal-ui atau fullscreen
        if (window.matchMedia && (window.matchMedia('(display-mode: minimal-ui)').matches || window.matchMedia('(display-mode: fullscreen)').matches)) {
            return true;
        }

        // 3. Cek navigator.standalone (Safari iOS)
        if (window.navigator && window.navigator.standalone === true) {
            return true;
        }

        // 4. Cek query parameter peluncuran PWA (?source=pwa)
        try {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('source') === 'pwa' || urlParams.get('mode') === 'standalone') {
                return true;
            }
        } catch (e) {}

        // 5. Cek referrer jika diluncurkan dari Android app wrapper (TWA)
        if (document.referrer && document.referrer.includes('android-app://')) {
            return true;
        }

        // 6. Cek tanda persistensi di localStorage
        if (localStorage.getItem('pwa_installed') === 'true') {
            return true;
        }

        return false;
    }

    // Jika terdeteksi standalone aktif, tandai di storage agar persist
    if (isPWAInstalled()) {
        try {
            localStorage.setItem('pwa_installed', 'true');
        } catch (e) {}
    }

    /**
     * Sembunyikan seluruh tombol dan popup/banner instalasi dari DOM
     */
    function hideAllInstallPromotions() {
        const triggers = document.querySelectorAll('.pwa-install-trigger, [data-pwa-install], #pwa-install-btn-nav, #pwa-install-btn-topbar');
        triggers.forEach(el => {
            el.style.setProperty('display', 'none', 'important');
        });

        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.classList.remove('show');
            banner.style.setProperty('display', 'none', 'important');
        }
    }

    /**
     * Tampilkan tombol instalasi (Hanya jika BELUM terinstall)
     */
    function showInstallPromotions() {
        if (isPWAInstalled()) {
            hideAllInstallPromotions();
            return;
        }

        // Tampilkan tombol di navbar/topbar
        const triggers = document.querySelectorAll('.pwa-install-trigger, [data-pwa-install], #pwa-install-btn-nav, #pwa-install-btn-topbar');
        triggers.forEach(el => {
            el.style.removeProperty('display');
            el.style.display = 'inline-flex';
        });

        // Tampilkan popup/banner jika belum ditutup di sesi ini
        const isDismissed = sessionStorage.getItem('pwa_banner_dismissed') === 'true';
        const banner = document.getElementById('pwa-install-banner');
        if (banner && !isDismissed) {
            setTimeout(() => {
                if (!isPWAInstalled() && sessionStorage.getItem('pwa_banner_dismissed') !== 'true') {
                    banner.style.display = 'block';
                    banner.offsetHeight; // trigger reflow
                    banner.classList.add('show');
                }
            }, 1200);
        }
    }

    // 1. Tangkap event beforeinstallprompt (Chromium / Android / Desktop)
    window.addEventListener('beforeinstallprompt', (e) => {
        // Jika sudah diinstall, abaikan sepenuhnya dan sembunyikan semua UI
        if (isPWAInstalled()) {
            hideAllInstallPromotions();
            return;
        }

        // Cegah prompt default browser
        e.preventDefault();
        deferredPrompt = e;

        // Tampilkan tombol & popup custom
        showInstallPromotions();
    });

    // 2. Tangkap event appinstalled saat proses instalasi selesai
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        try {
            localStorage.setItem('pwa_installed', 'true');
        } catch (e) {}
        hideAllInstallPromotions();
        console.log('[Tether Brew PWA] Aplikasi berhasil dipasang ke perangkat.');
    });

    /**
     * Panduan instalasi manual untuk pengguna iOS Safari
     */
    function showIOSGuide() {
        if (window.Swal) {
            Swal.fire({
                title: 'Install Tether Brew di iPhone',
                html: `
                    <div style="text-align: left; font-size: 13px; line-height: 1.6; color: var(--text-primary, #333);">
                        <p style="margin-bottom: 10px;">Aplikasi dapat dipasang ke layar depan HP tanpa App Store:</p>
                        <ol style="padding-left: 18px; margin: 0;">
                            <li style="margin-bottom: 8px;">Ketuk tombol <strong>Bagikan (Share)</strong> <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline-block; vertical-align:middle;"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> di bilah bawah Safari.</li>
                            <li>Pilih <strong>"Tambah ke Layar Utama" (Add to Home Screen)</strong>.</li>
                        </ol>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Mengerti',
                confirmButtonColor: '#22c55e'
            });
        } else {
            alert('Untuk menginstall di iPhone/iPad:\n1. Ketuk ikon Bagikan (Share) di bilah bawah Safari.\n2. Pilih "Tambah ke Layar Utama" (Add to Home Screen).');
        }
    }

    /**
     * Eksekusi klik tombol Install
     */
    async function triggerInstall(e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();

        // Validasi: jika sudah terinstall, tidak melakukan apa-apa dan pastikan UI hilang
        if (isPWAInstalled()) {
            hideAllInstallPromotions();
            return;
        }

        // Jika prompt browser Chromium tersedia
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                try {
                    localStorage.setItem('pwa_installed', 'true');
                } catch (err) {}
                hideAllInstallPromotions();
            }
            deferredPrompt = null;
            return;
        }

        // Cek apakah perangkat iOS Safari
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIOS) {
            showIOSGuide();
            return;
        }

        // Fallback untuk browser desktop yang promptnya tersimpan di bar URL
        alert('Untuk menginstall aplikasi Tether Brew:\nKlik ikon install (+) di bilah alamat browser Anda, atau buka menu browser lalu pilih "Install Tether Brew" / "Tambahkan ke Layar Utama".');
    }

    // Inisialisasi saat halaman selesai dimuat
    document.addEventListener('DOMContentLoaded', () => {
        // Cek pertama: jika sudah install, bersihkan semua sekarang juga
        if (isPWAInstalled()) {
            hideAllInstallPromotions();
            return;
        }

        // Cek iOS Safari
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIOS) {
            showInstallPromotions();
        }

        // Pasang event listener ke seluruh elemen pemicu install
        document.querySelectorAll('.pwa-install-trigger, [data-pwa-install], #pwa-install-btn-nav, #pwa-install-btn-topbar, #pwa-banner-install').forEach(btn => {
            btn.addEventListener('click', triggerInstall);
        });

        // Tombol tutup banner popup
        const closeBtn = document.getElementById('pwa-banner-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                try {
                    sessionStorage.setItem('pwa_banner_dismissed', 'true');
                } catch (err) {}
                const banner = document.getElementById('pwa-install-banner');
                if (banner) {
                    banner.classList.remove('show');
                    setTimeout(() => { banner.style.display = 'none'; }, 300);
                }
            });
        }
    });

    // Global helper jika dibutuhkan modul lain
    window.__pwa = {
        isInstalled: isPWAInstalled,
        install: triggerInstall,
        hide: hideAllInstallPromotions
    };
})();

