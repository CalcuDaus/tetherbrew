<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('customer');
$id = $_GET['id'] ?? null; if(!$id) redirect('/customer/chats');

$stmt = $pdo->prepare("SELECT c.*, u.name as rider_name FROM conversations c JOIN users u ON c.rider_id = u.id WHERE c.id=? AND c.customer_id=?");
$stmt->execute([$id, auth_id()]); $conv = $stmt->fetch();
if(!$conv) redirect('/customer/chats');

$pageTitle = 'Chat: ' . e($conv['rider_name']);
ob_start();
?>
<div class="card" style="max-width:800px; margin:0 auto; display:flex; flex-direction:column; height:80vh;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div><a href="/customer/chats" style="text-decoration:none; margin-right:12px; color:inherit; display:inline-flex; align-items:center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></a><span class="card-title"><?= e($conv['rider_name']) ?></span></div>
    </div>
    <div class="card-body" id="chat-window" style="flex:1; overflow-y:auto; padding:16px; background:#f0f2f5; display:flex; flex-direction:column-reverse;">
        <!-- Messages loaded here -->
    </div>
    <div style="padding:12px; background:white; border-top:1px solid var(--border-color);">
        <form id="chat-form" style="display:flex; gap:8px;">
            <input type="hidden" id="conv_id" value="<?= $id ?>">
            <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; background:var(--bg-secondary); border-radius:8px; padding:0 12px; color:var(--text-muted);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg><input type="file" id="attachment" style="display:none;" accept="image/*,application/pdf">
            </label>
            <input type="text" id="msg_body" class="form-input" style="flex:1; border-radius:20px; padding-left:16px;" placeholder="Ketik pesan..." autocomplete="off">
            <button type="submit" class="btn btn-primary" style="border-radius:20px; padding:0 20px;">Kirim</button>
        </form>
    </div>
</div>
<script>
const convId = document.getElementById('conv_id').value;
const chatWindow = document.getElementById('chat-window');
const myId = <?= auth_id() ?>;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function loadMessages() {
    let res = await fetch(`<?= url('/api/chat/messages') ?>?conversation_id=${convId}`);
    let data = await res.json();
    let html = '';
    data.data.forEach(m => {
        let isMe = m.sender_id == myId;
        let align = isMe ? 'flex-end' : 'flex-start';
        let bg = isMe ? 'var(--accent-green)' : 'white';
        let color = isMe ? 'white' : 'var(--text-color)';
        let attach = '';
        if(m.attachment_path) {
            let attachSrc = escapeHtml(m.attachment_path);
            if(m.attachment_type === 'image') attach = `<img src="${attachSrc}" style="max-width:200px; border-radius:8px; margin-bottom:4px; display:block;">`;
            else attach = `<a href="${attachSrc}" target="_blank" style="color:${color}; text-decoration:underline;">Lihat File</a>`;
        }
        let time = new Date(m.created_at).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
        
        html += `<div style="display:flex; flex-direction:column; align-items:${align}; margin-bottom:12px;">
            <div style="background:${bg}; color:${color}; padding:10px 14px; border-radius:16px; max-width:70%; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                ${attach}
                <div style="word-wrap:break-word;">${escapeHtml(m.body)}</div>
                <div style="font-size:10px; opacity:0.7; text-align:right; margin-top:4px;">${time}</div>
            </div>
        </div>`;
    });
    chatWindow.innerHTML = html;
    fetch(`<?= url('/api/chat/read') ?>`, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`conversation_id=${convId}`});
}

document.getElementById('chat-form').onsubmit = async function(e) {
    e.preventDefault();
    let body = document.getElementById('msg_body').value;
    let file = document.getElementById('attachment').files[0];
    if(!body && !file) return;
    
    let fd = new FormData();
    fd.append('conversation_id', convId);
    fd.append('body', body);
    if(file) fd.append('attachment', file);
    
    document.getElementById('msg_body').value = '';
    document.getElementById('attachment').value = '';
    
    await fetch('<?= url('/api/chat/send') ?>', {method:'POST', body:fd});
    loadMessages();
};

loadMessages();
setInterval(loadMessages, 5000);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
