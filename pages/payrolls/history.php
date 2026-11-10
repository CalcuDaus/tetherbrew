<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$branchId = activeBranchId();
$riderId = $_GET['rider_id'] ?? ''; $status = $_GET['status'] ?? '';

$riders = $pdo->prepare("SELECT id, name FROM users WHERE role='rider' AND branch_id=?"); $riders->execute([$branchId]); $riders = $riders->fetchAll();

$query = "SELECT p.*, r.name as rider_name FROM payroll_records p JOIN users r ON p.rider_id=r.id WHERE p.branch_id=?";
$params = [$branchId];
if($riderId) { $query .= " AND p.rider_id=?"; $params[] = $riderId; }
if($status) { $query .= " AND p.status=?"; $params[] = $status; }
$query .= " ORDER BY p.created_at DESC";

$pagination = paginate($pdo, $query, $params, 15);
$records = $pagination['data'];
$pageTitle = 'Riwayat Slip Gaji';
ob_start();
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0; min-width:200px; flex:1 1 200px; max-width:280px;">
                <label class="form-label">Rider</label>
                <select name="rider_id" class="form-input" style="width:100%;">
                    <option value="">Semua Rider</option>
                    <?php foreach($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $riderId==$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:200px; flex:1 1 200px; max-width:280px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-input" style="width:100%;">
                    <option value="">Semua Status</option>
                    <option value="draft" <?= $status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="confirmed" <?= $status=='confirmed'?'selected':'' ?>>Confirmed</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="height:42px; padding:0 20px;">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg> Filter
            </button>
            <?php if($riderId || $status): ?>
                <a href="<?= url('/payroll/history') ?>" class="btn btn-outline-secondary" style="height:42px; line-height:38px; padding:0 16px;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card"><div class="card-body" style="padding:0;">
    <?php if(empty($records)):?><div style="text-align:center; padding:40px; color:var(--text-muted);"><h3>Belum ada riwayat slip gaji</h3></div>
    <?php else:?>
    <div class="table-container"><table><thead><tr><th>Tgl Dibuat</th><th>Rider</th><th>Periode</th><th>Tipe</th><th>Gaji Bersih</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach($records as $r): ?>
        <tr>
            <td style="font-size:13px;"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
            <td style="font-weight:600;"><?= e($r['rider_name']) ?></td>
            <td style="font-size:13px;"><?= date('d/m', strtotime($r['period_start'])) ?> - <?= date('d/m', strtotime($r['period_end'])) ?></td>
            <td><span class="badge badge-info"><?= ucfirst($r['type']) ?></span></td>
            <td style="font-weight:bold; color:var(--accent-green);"><?= formatRupiah($r['net_income']) ?></td>
            <td>
                <?php if($r['status']==='draft'): ?><span class="badge badge-warning">Draft</span>
                <?php else: ?><span class="badge badge-success">Confirmed</span><?php endif; ?>
            </td>
            <td><a href="<?= url('/payroll/show?id=' . $r['id']) ?>" class="btn btn-primary btn-sm">Lihat Detail</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div style="padding:16px;"><?= render_pagination($pagination) ?></div>
    <?php endif; ?>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
