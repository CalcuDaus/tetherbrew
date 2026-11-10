<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_admin_or_owner();

$id = $_GET['id'] ?? null;
if(!$id) redirect('/admin/chats');

$stmt = $pdo->prepare("SELECT c.*, cust.name as customer_name, cust.phone as customer_phone, rid.name as rider_name, cart.name as cart_name 
    FROM conversations c 
    JOIN users cust ON c.customer_id = cust.id 
    JOIN users rid ON c.rider_id = rid.id 
    LEFT JOIN carts cart ON c.cart_id = cart.id 
    WHERE c.id = ?");
$stmt->execute([$id]);
$conv = $stmt->fetch();

if(!$conv) redirect('/admin/chats');

$pageTitle = 'Monitoring Chat: ' . e($conv['customer_name']) . ' & ' . e($conv['rider_name']);
ob_start();
?>
<div class="card" style="max-width: 900px; margin: 0 auto; height: 80vh; display: flex; flex-direction: column;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding: 14px 20px; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="/admin/chats" class="btn btn-outline-secondary btn-sm" style="text-decoration:none; padding:4px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg> Kembali
            </a>
            <div>
                <h3 class="card-title" style="margin:0; font-size:16px; font-weight:700; color:var(--text-color, #f8fafc);">
                    <?= e($conv['customer_name']) ?> <span style="font-weight:normal; color:var(--text-muted); font-size:13px;">⇄</span> <?= e($conv['rider_name']) ?>
                </h3>
                <div style="font-size:12px; color:var(--text-muted, #94a3b8); margin-top:2px;">
                    Gerobak: <strong><?= e($conv['cart_name'] ?? '-') ?></strong>
                    <?php if(!empty($conv['customer_phone'])): ?>
                        | HP: <?= e($conv['customer_phone']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div>
            <button onclick="sendQris()" class="btn btn-outline-primary btn-sm" style="border-radius:6px; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="7" y="7" width="3" height="3"></rect><rect x="14" y="7" width="3" height="3"></rect><rect x="7" y="14" width="3" height="3"></rect><rect x="14" y="14" width="3" height="3"></rect></svg> Kirim QRIS
            </button>
        </div>
    </div>

    <!-- Chat Messages Window -->
    <div class="card-body" id="chat-window" style="flex:1; overflow-y:auto; padding:20px; background: var(--bg-secondary, #0f172a); display:flex; flex-direction:column-reverse; gap:12px;">
        <div style="text-align:center; color:var(--text-muted); padding:20px;">Memuat riwayat pesan...</div>
    </div>

    <!-- Chat Input Form -->
    <div style="padding:14px 20px; background: var(--bg-card, #1e293b); border-top:1px solid var(--border-color, rgba(255,255,255,0.08));">
        <form id="chat-form" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" id="conv_id" value="<?= $id ?>">
            <label title="Lampirkan File/Gambar" style="cursor:pointer; display:flex; align-items:center; justify-content:center; background:var(--bg-secondary, rgba(255,255,255,0.05)); border:1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius:8px; width:40px; height:40px; color:var(--text-muted);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                <input type="file" id="attachment" style="display:none;" accept="image/*,application/pdf">
            </label>
            <input type="text" id="msg_body" class="form-input" style="flex:1; border-radius:20px; padding:10px 18px; font-size:14px;" placeholder="Tulis balasan sebagai Admin/Owner..." autocomplete="off">
            <button type="submit" class="btn btn-primary" style="border-radius:20px; padding:0 22px; height:40px; font-weight:600;">
                Kirim
            </button>
        </form>
    </div>
</div>

<script>
const convId = document.getElementById('conv_id').value;
const chatWindow = document.getElementById('chat-window');
const myId = <?= auth_id() ?>;

async function loadMessages() {
    try {
        let res = await fetch(`/api/chat/messages?conversation_id=${convId}`);
        if(!res.ok) return;
        let data = await res.json();
        let html = '';

        if(!data.data || data.data.length === 0) {
            html = `<div style="text-align:center; padding:40px; color:var(--text-muted);">Belum ada pesan dalam obrolan ini.</div>`;
        } else {
            data.data.forEach(m => {
                let isMe = (m.sender_id == myId);
                let isCustomer = (m.sender_role === 'customer');
                let isRider = (m.sender_role === 'rider');

                let align = 'flex-start';
                let bg = 'rgba(255,255,255,0.08)';
                let color = 'var(--text-color, #f8fafc)';
                let roleLabel = escapeHtml(m.sender_name) + ' (' + escapeHtml(m.sender_role) + ')';

                if (isMe) {
                    align = 'flex-end';
                    bg = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
                    color = '#ffffff';
                } else if (isCustomer) {
                    align = 'flex-start';
                    bg = 'rgba(245, 158, 11, 0.15)';
                    color = '#fef08a';
                    roleLabel = escapeHtml(m.sender_name) + ' (Pelanggan)';
                } else if (isRider) {
                    align = 'flex-start';
                    bg = 'rgba(59, 130, 246, 0.15)';
                    color = '#93c5fd';
                    roleLabel = escapeHtml(m.sender_name) + ' (Rider)';
                }

                let attachHtml = '';
                if(m.attachment_path) {
                    if(m.attachment_type === 'image' || m.attachment_path.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                        attachHtml = `<div style="margin-bottom:8px;"><a href="${m.attachment_path}" target="_blank"><img src="${m.attachment_path}" style="max-width:240px; max-height:200px; border-radius:8px; object-fit:cover; display:block; border:1px solid rgba(255,255,255,0.1);"></a></div>`;
                    } else {
                        attachHtml = `<div style="margin-bottom:6px;"><a href="${m.attachment_path}" target="_blank" style="color:inherit; text-decoration:underline; font-size:13px; display:inline-flex; align-items:center; gap:4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg> Unduh Lampiran</a></div>`;
                    }
                }

                let timeStr = new Date(m.created_at).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});

                html += `
                <div style="display:flex; flex-direction:column; align-items:${align}; margin-bottom:4px;">
                    <div style="font-size:11px; color:var(--text-muted, #94a3b8); margin-bottom:3px; padding:0 4px;">${roleLabel}</div>
                    <div style="background:${bg}; color:${color}; padding:10px 16px; border-radius:14px; max-width:75%; box-shadow:0 1px 3px rgba(0,0,0,0.2); word-break:break-word;">
                        ${attachHtml}
                        ${m.body ? `<div>${escapeHtml(m.body)}</div>` : ''}
                        <div style="font-size:10px; opacity:0.75; text-align:right; margin-top:4px;">${timeStr}</div>
                    </div>
                </div>`;
            });
        }

        chatWindow.innerHTML = html;

        // Mark as read for admin
        fetch(`/api/chat/read`, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`conversation_id=${convId}`
        });

    } catch(err) {
        console.error('Error loading messages:', err);
    }
}

document.getElementById('chat-form').onsubmit = async function(e) {
    e.preventDefault();
    let bodyInput = document.getElementById('msg_body');
    let fileInput = document.getElementById('attachment');
    let body = bodyInput.value.trim();
    let file = fileInput.files[0];
    if(!body && !file) return;

    let fd = new FormData();
    fd.append('conversation_id', convId);
    fd.append('body', body);
    if(file) fd.append('attachment', file);

    bodyInput.value = '';
    fileInput.value = '';

    await fetch('/api/chat/send', {method:'POST', body:fd});
    loadMessages();
};

async function sendQris() {
    if(confirm('Kirim gambar QRIS ke percakapan ini?')) {
        let fd = new FormData();
        fd.append('conversation_id', convId);
        await fetch('/api/chat/send-qris', {method:'POST', body:fd});
        loadMessages();
    }
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

loadMessages();
setInterval(loadMessages, 5000);
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
