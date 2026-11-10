<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('rider');
$pageTitle = 'Pesan Pelanggan';
ob_start();
?>
<div class="card" style="max-width:800px; margin:0 auto;"><div class="card-header"><div class="card-title">Pesan & Obrolan</div></div><div class="card-body" style="padding:0;">
    <div id="chat-list" style="min-height:300px;">
        <div style="text-align:center; padding:40px; color:var(--text-muted);">Memuat percakapan...</div>
    </div>
</div></div>
<script>
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function loadConversations() {
    let res = await fetch('<?= url('/api/chat/conversations') ?>');
    let data = await res.json();
    let html = '';
    if(!data.conversations || data.conversations.length === 0) {
        html = '<div style="text-align:center; padding:40px; color:var(--text-muted);">Belum ada obrolan</div>';
    } else {
        data.conversations.forEach(c => {
            let bold = c.unread_count > 0 ? 'font-weight:bold;' : '';
            let dot = c.unread_count > 0 ? `<span style="background:var(--accent-red); color:white; font-size:10px; padding:2px 6px; border-radius:10px; margin-left:8px;">${c.unread_count}</span>` : '';
            let cartName = c.cart_name ? `(${escapeHtml(c.cart_name)})` : '';
            html += `<a href="<?= url('/rider/chat_detail') ?>?id=${c.id}" style="display:flex; justify-content:space-between; align-items:center; padding:16px; border-bottom:1px solid var(--border-color); text-decoration:none; color:inherit; transition:background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                <div style="flex:1;">
                    <div style="font-size:16px; margin-bottom:4px; ${bold}">${escapeHtml(c.customer_name)} <small style="color:var(--text-muted);">${cartName}</small>${dot}</div>
                    <div style="font-size:13px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:250px;">${escapeHtml(c.last_message || 'Lampiran')}</div>
                </div>
                <div style="font-size:11px; color:var(--text-muted);">${new Date(c.last_message_at).toLocaleDateString('id-ID', {day:'numeric', month:'short'})}</div>
            </a>`;
        });
    }
    document.getElementById('chat-list').innerHTML = html;
}
loadConversations();
setInterval(loadConversations, 10000); // Poll every 10s
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
