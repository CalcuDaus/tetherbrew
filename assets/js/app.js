/**
 * Tether Brew - App JavaScript
 * Umum untuk halaman admin
 */

// Sidebar toggle handler (Desktop collapse & Mobile drawer)
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const closeBtn = document.getElementById('sidebar-close-mobile');

    // Sync desktop collapsed state from html to body if pre-applied in head
    if (document.documentElement.classList.contains('sidebar-collapsed')) {
        document.body.classList.add('sidebar-collapsed');
        document.documentElement.classList.remove('sidebar-collapsed');
    } else if (window.innerWidth > 768 && localStorage.getItem('sidebar-collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }

    function toggleSidebar() {
        if (!sidebar) return;
        if (window.innerWidth <= 768) {
            // Mobile: toggle drawer overlay
            const isOpen = sidebar.classList.toggle('open');
            if (backdrop) {
                backdrop.classList.toggle('active', isOpen);
            }
            document.body.style.overflow = isOpen ? 'hidden' : '';
        } else {
            // Desktop: toggle collapsed state
            const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
        }
    }

    function closeMobileSidebar() {
        if (sidebar && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            if (backdrop) {
                backdrop.classList.remove('active');
            }
            document.body.style.overflow = '';
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeMobileSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeMobileSidebar();
        });
    }

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                closeMobileSidebar();
            }
        }
    });

    // Close mobile sidebar when pressing Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    // Close mobile drawer when clicking navigation links inside sidebar
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-nav a').forEach(function(navLink) {
            navLink.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeMobileSidebar();
                }
            });
        });
    }

    // Handle screen resize cleanly
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileSidebar();
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        }
    });
    
    // Sidebar submenu toggle (collapsible sections)
    document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const arrow = this.querySelector('.dropdown-arrow');
            
            if (submenu && submenu.classList.contains('nav-submenu')) {
                const isNowCollapsed = submenu.classList.toggle('collapsed');
                if (isNowCollapsed) {
                    submenu.style.display = 'none';
                    if (arrow) arrow.classList.remove('rotated');
                } else {
                    submenu.style.display = 'flex';
                    if (arrow) arrow.classList.add('rotated');
                }
            }
        });
    });
    
    // Auto-hide flash messages after 5 seconds
    const flash = document.getElementById('flash-message');
    if (flash) {
        setTimeout(function() {
            flash.style.transition = 'opacity 0.3s, transform 0.3s';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(function() { flash.remove(); }, 300);
        }, 5000);
    }
    
    // Initialize flatpickr on date inputs
    document.querySelectorAll('.datepicker').forEach(function(input) {
        flatpickr(input, {
            dateFormat: 'Y-m-d',
            allowInput: true,
        });
    });
    
    // Initialize Select2 on select elements inside page content only (avoid SweetAlert conflicts)
    if (typeof window.jQuery !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
        $('.page-content select, .card select').not('.swal2-container select, .modal select').select2({
            width: '100%',
            placeholder: 'Pilih...',
        });
    }

    // Global keyboard navigation for matrix tables (horizontal & vertical)
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' && e.target.type === 'number' && e.target.closest('table')) {
            const currentInput = e.target;
            const currentTd = currentInput.closest('td');
            const currentTr = currentTd.closest('tr');
            
            if (!currentTd || !currentTr) return;

            const tds = Array.from(currentTr.children);
            const colIndex = tds.indexOf(currentTd);
            let nextInput = null;

            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                switch (e.key) {
                    case 'ArrowLeft':
                        const prevTd = tds[colIndex - 1];
                        if (prevTd) nextInput = prevTd.querySelector('input[type="number"]');
                        break;
                    case 'ArrowRight':
                        const nextTd = tds[colIndex + 1];
                        if (nextTd) nextInput = nextTd.querySelector('input[type="number"]');
                        break;
                    case 'ArrowUp':
                        let prevTr = currentTr.previousElementSibling;
                        while (prevTr && (!prevTr.children[colIndex] || !prevTr.children[colIndex].querySelector('input[type="number"]'))) {
                            prevTr = prevTr.previousElementSibling;
                        }
                        if (prevTr && prevTr.children[colIndex]) {
                            nextInput = prevTr.children[colIndex].querySelector('input[type="number"]');
                        }
                        break;
                    case 'ArrowDown':
                        let nextTr = currentTr.nextElementSibling;
                        while (nextTr && (!nextTr.children[colIndex] || !nextTr.children[colIndex].querySelector('input[type="number"]'))) {
                            nextTr = nextTr.nextElementSibling;
                        }
                        if (nextTr && nextTr.children[colIndex]) {
                            nextInput = nextTr.children[colIndex].querySelector('input[type="number"]');
                        }
                        break;
                }

                if (nextInput) {
                    e.preventDefault(); // Prevent native scroll / cursor move / number increment
                    nextInput.focus();
                    nextInput.select(); // Highlight text for quick rewrite
                }
            }
        }
    });

    // Global Anti Double-Submit untuk seluruh form POST standar
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // 1. Abaikan jika submit dibatalkan (misal sedang menunggu dialog data-confirm SweetAlert2 atau validasi custom)
        if (e.defaultPrevented || form.hasAttribute('data-confirm')) return;

        // 2. Abaikan form GET (filter laporan / pencarian) dan form yang punya atribut data-no-lock
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'POST' || form.hasAttribute('data-no-lock')) return;

        // 3. Cegah submit kedua jika form sudah dikunci
        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';

        const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitButtons.forEach(function(btn) {
            btn.disabled = true;
            btn.style.opacity = '0.7';
            btn.style.cursor = 'not-allowed';
            if (btn.tagName === 'BUTTON' && !btn.dataset.origHtml) {
                btn.dataset.origHtml = btn.innerHTML;
                // Hanya ganti teks jika tombol cukup lebar (bukan tombol ikon kecil di tabel)
                if (btn.offsetWidth >= 90) {
                    btn.innerHTML = '⏳ Memproses...';
                }
            }
        });
    });
});

// ===== Universal App Modal Helpers =====
function openModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    el.classList.add('active');
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function() {
        const input = el.querySelector('input:not([type=hidden]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([readonly]):not([disabled])');
        if (input) input.focus();
    }, 150);
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    el.classList.remove('active');
    setTimeout(function() {
        if (!el.classList.contains('active')) {
            el.style.display = 'none';
        }
        // Only reset overflow if no other modal is active
        if (!document.querySelector('.app-modal-overlay.active, .modal-overlay.active, .modal-overlay-animate.active')) {
            document.body.style.overflow = '';
        }
    }, 200);
}

// Global listeners for closing modals via backdrop click, [data-close-modal], and ESC key
document.addEventListener('DOMContentLoaded', function() {
    // Backdrop click
    document.querySelectorAll('.app-modal-overlay, .modal-overlay, .modal-overlay-animate').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeModal(overlay.id);
            }
        });
    });

    // Close button click
    document.querySelectorAll('[data-close-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = btn.getAttribute('data-close-modal');
            if (targetId) {
                closeModal(targetId);
            } else {
                const parentModal = btn.closest('.app-modal-overlay, .modal-overlay, .modal-overlay-animate');
                if (parentModal) closeModal(parentModal.id);
            }
        });
    });

    // ESC key closes active modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.app-modal-overlay.active, .modal-overlay.active, .modal-overlay-animate.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
});

// Confirm delete helper
function confirmDelete(formId, message) {
    message = message || 'Apakah Anda yakin ingin menghapus data ini?';
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById(formId).submit();
        }
    });
}

// Format number as Rupiah
function formatRupiah(amount) {
    return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
}

// Toast notification
function showToast(type, message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}
