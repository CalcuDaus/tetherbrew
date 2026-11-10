<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_admin_or_owner();

$pageTitle = 'Monitoring Chat';
ob_start();
?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 class="card-title" style="margin:0; font-size:18px; font-weight:700; color:var(--text-color, #f8fafc); display:flex; align-items:center;">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Monitoring Chat Pelanggan & Rider
            </h2>
            <p style="margin:4px 0 0 0; font-size:13px; color:var(--text-muted, #94a3b8);">
                Pantau seluruh percakapan aktif antara pelanggan dan rider secara real-time.
            </p>
        </div>
        <div>
            <input type="text" id="chat-search" class="form-input" placeholder="Cari nama pelanggan / rider..." oninput="filterConversations()" style="padding: 8px 14px; font-size: 13px; border-radius: 8px; min-width: 240px;">
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div id="chat-list-container" class="table-container" style="min-height:300px;">
            <div style="text-align:center; padding:50px; color:var(--text-muted);">
                <div class="spinner-border text-primary" role="status" style="margin-bottom:10px;"></div>
                <div>Memuat daftar obrolan...</div>
            </div>
        </div>
    </div>
</div>

<script>
let allConversations = [];

async function loadConversations() {
    try {
        let res = await fetch('/api/chat/conversations');
        if(!res.ok) throw new Error('HTTP ' + res.status);
        let data = await res.json();
        allConversations = data.conversations || [];
        renderConversations(allConversations);
    } catch(err) {
        console.error('Error fetching conversations:', err);
    }
}

function filterConversations() {
    let q = document.getElementById('chat-search').value.toLowerCase().trim();
    if(!q) {
        renderConversations(allConversations);
        return;
    }
    let filtered = allConversations.filter(c => 
        (c.customer_name && c.customer_name.toLowerCase().includes(q)) ||
        (c.rider_name && c.rider_name.toLowerCase().includes(q)) ||
        (c.cart_name && c.cart_name.toLowerCase().includes(q))
    );
    renderConversations(filtered);
}

function renderConversations(list) {
    let container = document.getElementById('chat-list-container');
    if(!list || list.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:60px 20px; color:var(--text-muted, #94a3b8);">
                <div style="margin-bottom:12px; color:var(--text-muted); opacity:0.6;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h4 style="margin:0; font-size:16px; font-weight:600; color:var(--text-color, #f8fafc);">Belum ada obrolan</h4>
                <p style="font-size:13px; margin-top:4px;">Obrolan antara pelanggan dan rider akan muncul di sini.</p>
            </div>`;
        return;
    }

    let html = `
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="border-bottom: 2px solid var(--border-color, rgba(255,255,255,0.08));">
                <th style="padding:14px 16px; text-align:left;">Pelanggan</th>
                <th style="padding:14px 16px; text-align:left;">Rider / Gerobak</th>
                <th style="padding:14px 16px; text-align:left;">Pesan Terakhir</th>
                <th style="padding:14px 16px; text-align:left;">Waktu</th>
                <th style="padding:14px 16px; text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody>`;

    list.forEach(c => {
        let unreadBadge = c.unread_count > 0 ? 
            `<span style="background:var(--accent-red, #ef4444); color:white; font-size:11px; font-weight:bold; padding:2px 8px; border-radius:12px; margin-left:6px;">${c.unread_count} baru</span>` : '';
        
        let cartInfo = c.cart_name ? `<span style="background:rgba(59, 130, 246, 0.15); color:#60a5fa; font-size:11px; padding:2px 8px; border-radius:6px; margin-left:4px;">${escapeHtml(c.cart_name)}</span>` : '<span style="color:var(--text-muted); font-size:11px;">-</span>';
        
        let lastMsg = c.last_message ? escapeHtml(c.last_message) : '<em style="color:var(--text-muted);">[Lampiran / Gambar]</em>';
        
        let timeStr = c.last_message_at ? new Date(c.last_message_at).toLocaleString('id-ID', {
            day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'
        }) : '-';

        html += `
        <tr style="border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.05)); transition: background 0.2s;" onmouseover="this.style.background='var(--bg-hover, rgba(255,255,255,0.02))'" onmouseout="this.style.background='transparent'">
            <td style="padding:14px 16px; font-weight:600;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg, #f59e0b, #d97706); display:inline-flex; align-items:center; justify-content:center; color:white; font-size:13px; font-weight:bold;">
                        ${escapeHtml(c.customer_name ? c.customer_name.charAt(0).toUpperCase() : 'C')}
                    </span>
                    <div>
                        <div style="font-size:14px; color:var(--text-color, #f8fafc);">${escapeHtml(c.customer_name)} ${unreadBadge}</div>
                    </div>
                </div>
            </td>
            <td style="padding:14px 16px;">
                <div style="font-size:14px; font-weight:500; color:var(--accent-blue, #3b82f6);">${escapeHtml(c.rider_name)}</div>
                <div style="margin-top:2px;">${cartInfo}</div>
            </td>
            <td style="padding:14px 16px; font-size:13px; color:var(--text-muted, #94a3b8); max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                ${lastMsg}
            </td>
            <td style="padding:14px 16px; font-size:12px; color:var(--text-muted, #94a3b8); white-space:nowrap;">
                ${timeStr}
            </td>
            <td style="padding:14px 16px; text-align:center;">
                <a href="/admin/chats/show?id=${c.id}" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; padding:6px 12px; text-decoration:none; font-size:12px;">
                    <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    Lihat Chat
                </a>
            </td>
        </tr>`;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;
}

function escapeHtml(text) {
    if(!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

loadConversations();
setInterval(loadConversations, 10000);
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
