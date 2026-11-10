<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('customer');
$pageTitle = 'Chats - Tether Brew';
ob_start();
?>
<style>
    .chats-header {
        padding: 20px;
        background: white;
        position: sticky;
        top: 0;
        z-index: 40;
        border-bottom: 1px solid #f1f5f9;
    }
    .chats-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .conversations-list {
        display: flex;
        flex-direction: column;
    }

    .conversation-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        background: white;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        color: inherit;
        gap: 15px;
    }

    .conversation-item:active {
        background: #f8fafc;
    }

    .chat-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #8b5c2a, #6b4420);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .chat-info {
        flex: 1;
        overflow: hidden;
    }

    .chat-name {
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 2px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-preview {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .unread-badge {
        background: #ef4444;
        color: white;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }
    
    .chat-empty {
        text-align: center; padding: 40px 20px; color: #64748b;
    }
</style>

<div class="chats-header">
    <h1 class="chats-title">Chat Rider</h1>
</div>

<div class="conversations-list" id="chat-list">
    <div class="chat-empty">Memuat percakapan...</div>
</div>

<script>
function escapeHtml(unsafe) {
    return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function timeSince(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const seconds = Math.floor((new Date() - date) / 1000);
    let interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + " tahun";
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + " bulan";
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + " hari";
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + " jam";
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + " mnt";
    return "Baru saja";
}

async function loadConversations() {
    try {
        let res = await fetch('<?= url('/api/chat/conversations') ?>');
        let data = await res.json();
        let html = '';
        if(!data.conversations || data.conversations.length === 0) {
            html = '<div class="chat-empty">Belum ada percakapan. Temukan gerobak di Beranda atau Menu untuk mulai memesan!</div>';
        } else {
            data.conversations.forEach(c => {
                const initial = c.rider_name ? c.rider_name.charAt(0).toUpperCase() : 'R';
                const preview = c.last_message ? escapeHtml(c.last_message) : 'Lampiran foto';
                const timeStr = timeSince(c.last_message_at);
                const unread = c.unread_count > 0 ? `<span class="unread-badge">${c.unread_count}</span>` : '';
                
                html += `
                <a href="<?= url('/customer/chat_detail?id=') ?>${c.id}" class="conversation-item">
                    <div class="chat-avatar">
                        ${initial}
                    </div>
                    <div class="chat-info">
                        <h3 class="chat-name">${escapeHtml(c.rider_name)}</h3>
                        <p class="chat-preview">${preview}</p>
                    </div>
                    <div class="chat-meta">
                        <span>${timeStr}</span>
                        ${unread}
                    </div>
                </a>`;
            });
        }
        document.getElementById('chat-list').innerHTML = html;
    } catch (err) {
        console.error("Error loading conversations", err);
    }
}
loadConversations();
setInterval(loadConversations, 10000);
</script>

<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../../layouts/customer.php';
?>
