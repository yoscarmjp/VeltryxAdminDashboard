<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin', 'moderator'])) {
    header("Location: dashboard");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_action'])) {
    csrf_verify();
    $form_action = $_POST['form_action'];
    $report_id = (int)$_POST['report_id'];

    if ($form_action === 'attend' && in_array($_SESSION['role'], ['owner', 'admin', 'moderator'])) {
        $action_taken  = in_array($_POST['action_taken'], ['ban', 'kick', 'blacklist', 'mute', 'none']) ? $_POST['action_taken'] : 'none';
        $action_reason = strip_tags(trim($_POST['action_reason']));
        $attended_by   = (string)($_SESSION['user_id'] ?? 'admin');
        $evidence      = isset($_POST['evidence_url']) ? strip_tags(trim($_POST['evidence_url'])) : '';

        $stmt = $conn->prepare("UPDATE reports SET status='accepted', action_taken=?, action_reason=?, attended_by=?, evidence_url=? WHERE id=?");
        $stmt->execute([$action_taken, $action_reason, $attended_by, $evidence, $report_id]);

        $labels = ['ban' => 'Baneado', 'kick' => 'Expulsado', 'blacklist' => 'Blacklisteado', 'mute' => 'Muteado', 'none' => 'Dejado Impune'];
        log_action("ATTEND_REPORT", "Atendió el reporte #$report_id — Acción: " . ($labels[$action_taken] ?? $action_taken));
        $success = "El reporte ha sido marcado como atendido.";

    } elseif ($form_action === 'reject' && in_array($_SESSION['role'], ['owner', 'admin'])) {
        $upd = $conn->prepare("UPDATE reports SET status='rejected' WHERE id=?");
        $upd->execute([$report_id]);
        log_action("REJECT_REPORT", "Rechazó el reporte #$report_id");
        $success = "El reporte ha sido rechazado.";
    }
}

$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['pending', 'accepted', 'rejected']) ? $_GET['filter'] : '';
$search = isset($_GET['q']) ? strip_tags(trim($_GET['q'])) : '';

$where  = [];
$params = [];
if ($filter) {
    $where[]  = "status = ?";
    $params[] = $filter;
}
if ($search) {
    $where[]  = "(reported_user LIKE ? OR reporting_user LIKE ? OR server_name LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reports_stmt = $conn->prepare("SELECT * FROM reports $whereSQL ORDER BY created_at DESC");
$reports_stmt->execute($params);
$reports_list = $reports_stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header">
    <div>
        <h1>Sistema de Reportes</h1>
        <p>Revisa y gestiona los reportes enviados desde el servidor de Minecraft.</p>
    </div>
</div>

<?php if($error): ?>
<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="reports-filters">
    <form method="GET" action="" class="filter-form">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Buscar por jugador o servidor..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-tabs">
            <a href="reports" class="filter-tab <?php echo !$filter ? 'active' : ''; ?>">Todos</a>
            <a href="reports?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>"><span class="dot dot-pending"></span>Pendientes</a>
            <a href="reports?filter=accepted" class="filter-tab <?php echo $filter === 'accepted' ? 'active' : ''; ?>"><span class="dot dot-accepted"></span>Atendidos</a>
            <a href="reports?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>"><span class="dot dot-rejected"></span>Rechazados</a>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Reportes <span class="badge-count"><?php echo count($reports_list); ?></span></h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Servidor</th>
                <th>Reportado</th>
                <th>Reportador</th>
                <th>Motivo</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th style="text-align:right;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reports_list as $r): ?>
            <tr class="report-row <?php echo $r['status'] === 'pending' ? 'row-pending' : ''; ?>" onclick="openModal(<?php echo htmlspecialchars(json_encode($r)); ?>)" style="cursor:pointer;">
                <td style="color:#94a3b8; font-weight:bold;">#<?php echo $r['id']; ?></td>
                <td><span class="server-badge"><i class="fa-solid fa-server"></i> <?php echo htmlspecialchars($r['server_name']); ?></span></td>
                <td class="player-red"><?php echo htmlspecialchars($r['reported_user']); ?></td>
                <td class="player-blue"><?php echo htmlspecialchars($r['reporting_user']); ?></td>
                <td class="reason-cell"><?php echo htmlspecialchars(mb_substr($r['reason'], 0, 45)) . (mb_strlen($r['reason']) > 45 ? '…' : ''); ?></td>
                <td style="color:#94a3b8; font-size:0.85rem;"><?php echo date("d M Y H:i", strtotime($r['created_at'])); ?></td>
                <td><?php
                    $statusMap = ['pending' => ['label' => 'Pendiente', 'class' => 'pending'], 'accepted' => ['label' => 'Atendido', 'class' => 'approved'], 'rejected' => ['label' => 'Rechazado', 'class' => 'rejected'], 'revoked' => ['label' => 'Revocado', 'class' => 'rejected']];
                    $s = $statusMap[$r['status']] ?? ['label' => ucfirst($r['status']), 'class' => 'pending'];
                ?><span class="status <?php echo $s['class']; ?>"><?php echo $s['label']; ?></span></td>
                <td style="text-align:right;">
                    <button class="btn-action view" title="Ver Detalles"><i class="fa-solid fa-eye"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(count($reports_list) == 0): ?>
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fa-solid fa-flag"></i>
                        <h3>No hay reportes</h3>
                        <p>No se encontraron reportes con los filtros actuales.</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="reportModal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>

        <div class="modal-header-section">
            <div class="modal-title-row">
                <h2>Reporte <span id="m_id" class="highlight-id"></span></h2>
                <span id="m_status_badge" class="status"></span>
            </div>
            <p id="m_date" class="modal-date"></p>
        </div>

        <div class="modal-grid">
            <div class="modal-info-block">
                <p class="info-label"><i class="fa-solid fa-flag"></i> Reportador</p>
                <p id="m_reporting" class="info-value player-blue"></p>
            </div>
            <div class="modal-info-block">
                <p class="info-label"><i class="fa-solid fa-skull"></i> Reportado</p>
                <p id="m_reported" class="info-value player-red"></p>
            </div>
            <div class="modal-info-block" style="grid-column: span 2;">
                <p class="info-label"><i class="fa-solid fa-server"></i> Servidor</p>
                <p id="m_server" class="info-value"></p>
            </div>
        </div>

        <div class="modal-reason">
            <p class="info-label"><i class="fa-solid fa-comment-dots"></i> Motivo del Reporte</p>
            <p id="m_reason" class="reason-text"></p>
        </div>

        <div id="m_additional_block" class="modal-additional" style="display:none;">
            <p class="info-label"><i class="fa-solid fa-circle-info"></i> Información Adicional</p>
            <p id="m_additional" class="reason-text"></p>
        </div>

        <div id="m_evidence_container" class="modal-evidence" style="display:none;">
            <p class="info-label"><i class="fa-solid fa-link"></i> Evidencia Adjunta</p>
            <a id="m_evidence_link" href="#" target="_blank" class="evidence-link"></a>
        </div>

        <div id="m_action_result" class="modal-action-result" style="display:none;">
            <div class="result-grid">
                <div>
                    <p class="info-label"><i class="fa-solid fa-gavel"></i> Sanción Aplicada</p>
                    <p id="m_action_taken" class="info-value"></p>
                </div>
                <div>
                    <p class="info-label"><i class="fa-solid fa-user-shield"></i> Atendido por</p>
                    <p id="m_attended_by" class="info-value"></p>
                </div>
                <div style="grid-column: span 2;">
                    <p class="info-label"><i class="fa-solid fa-scroll"></i> Razón de la Sanción</p>
                    <p id="m_action_reason" class="reason-text"></p>
                </div>
            </div>
        </div>

        <?php if(in_array($_SESSION['role'], ['owner', 'admin', 'moderator'])): ?>
        <div id="action_forms" style="display:none;">
            <div class="modal-divider"></div>
            <h3 class="action-section-title"><i class="fa-solid fa-gavel"></i> Gestionar Reporte</h3>

            <form method="POST" action="" id="attendForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form_action" value="attend">
                <input type="hidden" name="report_id" class="m_report_id_input">

                <div class="action-choices">
                    <p class="info-label" style="margin-bottom:0.8rem;">¿Qué acción se tomó?</p>
                    <div class="action-pills">
                        <label class="action-pill ban"><input type="radio" name="action_taken" value="ban" required> <i class="fa-solid fa-ban"></i> Baneado</label>
                        <label class="action-pill kick"><input type="radio" name="action_taken" value="kick"> <i class="fa-solid fa-door-open"></i> Expulsado</label>
                        <label class="action-pill blacklist"><input type="radio" name="action_taken" value="blacklist"> <i class="fa-solid fa-list-xmark"></i> Blacklisteado</label>
                        <label class="action-pill mute"><input type="radio" name="action_taken" value="mute"> <i class="fa-solid fa-volume-xmark"></i> Muteado</label>
                        <label class="action-pill none"><input type="radio" name="action_taken" value="none"> <i class="fa-solid fa-circle-xmark"></i> Impune</label>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="info-label">Razón de la decisión <span style="color:var(--danger)">*</span></label>
                    <textarea name="action_reason" rows="3" placeholder="Explica por qué se tomó esa decisión..." class="modal-textarea" required></textarea>
                </div>

                <div class="form-group" style="margin-top:0.8rem;">
                    <label class="info-label">URL de Evidencia (opcional)</label>
                    <input type="url" name="evidence_url" placeholder="https://..." class="modal-input">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-attend"><i class="fa-solid fa-check"></i> Marcar como Atendido</button>
                    <?php if(in_array($_SESSION['role'], ['owner', 'admin'])): ?>
                    <button type="button" onclick="submitReject()" class="btn-reject"><i class="fa-solid fa-xmark"></i> Rechazar (Falso)</button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if(in_array($_SESSION['role'], ['owner', 'admin'])): ?>
            <form method="POST" action="" id="rejectForm" style="display:none;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form_action" value="reject">
                <input type="hidden" name="report_id" class="m_report_id_input">
            </form>
            <?php endif; ?>
        </div>

        <div id="status_text" class="status-closed" style="display:none;"></div>
        <?php endif; ?>
    </div>
</div>

<style>
.alert-danger { background: rgba(239,68,68,0.1); color: #ef4444; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid rgba(239,68,68,0.2); }
.alert-success { background: rgba(16,185,129,0.1); color: var(--accent); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid rgba(16,185,129,0.2); }

.reports-filters { margin-bottom: 1.5rem; }
.filter-form { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.search-box { display: flex; align-items: center; gap: 0.6rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 0.6rem 1rem; flex: 1; min-width: 220px; }
.search-box i { color: var(--text-muted); }
.search-box input { background: transparent; border: none; color: #fff; font-size: 0.9rem; width: 100%; outline: none; }
.filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.filter-tab { padding: 0.5rem 1rem; border-radius: 8px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-muted); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.2s; display: flex; align-items: center; gap: 0.4rem; }
.filter-tab.active, .filter-tab:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot-pending { background: #f59e0b; }
.dot-accepted { background: #10b981; }
.dot-rejected { background: #ef4444; }

.badge-count { background: var(--primary); color: #fff; font-size: 0.75rem; padding: 2px 8px; border-radius: 20px; margin-left: 0.5rem; vertical-align: middle; }
.server-badge { background: rgba(255,255,255,0.05); padding: 3px 10px; border-radius: 6px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; }
.server-badge i { color: var(--primary); }
.player-red { color: #ef4444; font-weight: bold; }
.player-blue { color: #3b82f6; font-weight: bold; }
.reason-cell { color: var(--text-muted); font-size: 0.88rem; max-width: 200px; }
.report-row:hover { background: rgba(255,255,255,0.03); }
.row-pending td:first-child { border-left: 3px solid #f59e0b; padding-left: 12px; }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(6px); overflow-y: auto; padding: 2rem 1rem; }
.modal-card { background: var(--bg-card); border: 1px solid var(--border); padding: 2rem; border-radius: 20px; width: 100%; max-width: 620px; position: relative; box-shadow: 0 25px 60px rgba(0,0,0,0.6); margin: auto; }
.modal-close { position: absolute; top: 1.2rem; right: 1.2rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-muted); cursor: pointer; font-size: 1rem; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.modal-close:hover { background: rgba(239,68,68,0.2); color: #ef4444; border-color: rgba(239,68,68,0.3); }
.modal-header-section { margin-bottom: 1.5rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--border); }
.modal-title-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.3rem; }
.modal-title-row h2 { margin: 0; font-size: 1.3rem; }
.highlight-id { color: var(--primary); }
.modal-date { color: var(--text-muted); font-size: 0.85rem; margin: 0; }
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.2rem; }
.modal-info-block { background: rgba(0,0,0,0.2); padding: 0.9rem 1rem; border-radius: 10px; border: 1px solid var(--border); }
.info-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 800; margin-bottom: 0.4rem; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.4rem; }
.info-value { font-weight: 700; font-size: 1rem; margin: 0; }
.modal-reason, .modal-additional { background: rgba(0,0,0,0.25); padding: 1rem 1.2rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 1rem; }
.reason-text { line-height: 1.6; margin: 0; color: #e2e8f0; }
.modal-evidence { padding: 0.8rem 1.2rem; background: rgba(16,185,129,0.05); border: 1px dashed rgba(16,185,129,0.3); border-radius: 10px; margin-bottom: 1rem; }
.evidence-link { color: #10b981; text-decoration: none; word-break: break-all; font-weight: 600; }
.evidence-link:hover { text-decoration: underline; }
.modal-action-result { background: rgba(139,92,246,0.06); border: 1px solid rgba(139,92,246,0.2); border-radius: 12px; padding: 1.2rem; margin-bottom: 1rem; }
.result-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.modal-divider { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
.action-section-title { margin: 0 0 1rem 0; font-size: 1rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; }
.action-choices .info-label { margin-bottom: 0.8rem; }
.action-pills { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.action-pill { display: flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1rem; border-radius: 8px; border: 1px solid var(--border); background: rgba(255,255,255,0.04); cursor: pointer; font-size: 0.85rem; font-weight: 700; transition: 0.2s; color: var(--text-muted); }
.action-pill input[type=radio] { display: none; }
.action-pill:has(input:checked) { border-color: var(--primary); background: rgba(139,92,246,0.2); color: #fff; }
.action-pill.ban:has(input:checked) { border-color: #ef4444; background: rgba(239,68,68,0.15); color: #ef4444; }
.action-pill.kick:has(input:checked) { border-color: #f59e0b; background: rgba(245,158,11,0.15); color: #f59e0b; }
.action-pill.blacklist:has(input:checked) { border-color: #8b5cf6; background: rgba(139,92,246,0.15); color: #8b5cf6; }
.action-pill.mute:has(input:checked) { border-color: #3b82f6; background: rgba(59,130,246,0.15); color: #3b82f6; }
.action-pill.none:has(input:checked) { border-color: #64748b; background: rgba(100,116,139,0.15); color: #94a3b8; }
.action-pill:hover { border-color: var(--primary); color: #fff; }
.modal-textarea { width: 100%; padding: 0.8rem; border-radius: 10px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff; font-size: 0.9rem; font-family: inherit; resize: vertical; transition: border-color 0.2s; box-sizing: border-box; }
.modal-textarea:focus { outline: none; border-color: var(--primary); }
.modal-input { width: 100%; padding: 0.75rem 1rem; border-radius: 10px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff; font-size: 0.9rem; transition: border-color 0.2s; box-sizing: border-box; }
.modal-input:focus { outline: none; border-color: var(--primary); }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-actions { display: flex; gap: 0.8rem; margin-top: 1.2rem; flex-wrap: wrap; }
.btn-attend { flex: 2; background: var(--accent); color: #fff; border: none; padding: 0.9rem; border-radius: 10px; font-weight: 800; cursor: pointer; transition: 0.2s; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.btn-attend:hover { opacity: 0.85; }
.btn-reject { flex: 1; background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); padding: 0.9rem; border-radius: 10px; font-weight: 800; cursor: pointer; transition: 0.2s; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.btn-reject:hover { background: var(--danger); color: #fff; border-color: var(--danger); }
.status-closed { text-align: center; padding: 1rem; background: rgba(255,255,255,0.04); border-radius: 10px; font-weight: bold; color: var(--text-muted); border: 1px solid var(--border); margin-top: 1rem; }

.action-tag { display: inline-flex; align-items: center; gap: 0.4rem; padding: 4px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; }
.action-tag.ban { background: rgba(239,68,68,0.15); color: #ef4444; }
.action-tag.kick { background: rgba(245,158,11,0.15); color: #f59e0b; }
.action-tag.blacklist { background: rgba(139,92,246,0.15); color: #8b5cf6; }
.action-tag.mute { background: rgba(59,130,246,0.15); color: #3b82f6; }
.action-tag.none { background: rgba(100,116,139,0.15); color: #94a3b8; }
</style>

<script>
const actionLabels = {
    ban: '<span class="action-tag ban"><i class="fa-solid fa-ban"></i> Baneado</span>',
    kick: '<span class="action-tag kick"><i class="fa-solid fa-door-open"></i> Expulsado</span>',
    blacklist: '<span class="action-tag blacklist"><i class="fa-solid fa-list-xmark"></i> Blacklisteado</span>',
    mute: '<span class="action-tag mute"><i class="fa-solid fa-volume-xmark"></i> Muteado</span>',
    none: '<span class="action-tag none"><i class="fa-solid fa-circle-xmark"></i> Dejado Impune</span>'
};

function openModal(report) {
    document.getElementById('m_id').innerText = '#' + report.id;
    document.getElementById('m_reporting').innerText = report.reporting_user;
    document.getElementById('m_reported').innerText = report.reported_user;
    document.getElementById('m_server').innerText = report.server_name;
    document.getElementById('m_reason').innerText = report.reason;

    const dt = new Date(report.created_at.replace(' ', 'T'));
    document.getElementById('m_date').innerText = dt.toLocaleDateString('es-ES', {day:'2-digit', month:'long', year:'numeric'}) + ' — ' + dt.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});

    const statusBadge = document.getElementById('m_status_badge');
    const statusMap = { pending: ['Pendiente', 'pending'], accepted: ['Atendido', 'approved'], rejected: ['Rechazado', 'rejected'], revoked: ['Revocado', 'rejected'] };
    const [label, cls] = statusMap[report.status] || ['Desconocido', 'pending'];
    statusBadge.className = 'status ' + cls;
    statusBadge.innerText = label;

    const additionalBlock = document.getElementById('m_additional_block');
    if (report.additional_info && report.additional_info.trim() !== '') {
        additionalBlock.style.display = 'block';
        document.getElementById('m_additional').innerText = report.additional_info;
    } else {
        additionalBlock.style.display = 'none';
    }

    const evContainer = document.getElementById('m_evidence_container');
    if (report.evidence_url && report.evidence_url.trim() !== '') {
        evContainer.style.display = 'block';
        document.getElementById('m_evidence_link').href = report.evidence_url;
        document.getElementById('m_evidence_link').innerText = report.evidence_url;
    } else {
        evContainer.style.display = 'none';
    }

    const actionResult = document.getElementById('m_action_result');
    if (report.action_taken && report.status === 'accepted') {
        actionResult.style.display = 'block';
        document.getElementById('m_action_taken').innerHTML = actionLabels[report.action_taken] || report.action_taken;
        document.getElementById('m_attended_by').innerText = report.attended_by || '—';
        document.getElementById('m_action_reason').innerText = report.action_reason || '—';
    } else {
        actionResult.style.display = 'none';
    }

    document.querySelectorAll('.m_report_id_input').forEach(el => el.value = report.id);

    const actionsDiv = document.getElementById('action_forms');
    const statusText = document.getElementById('status_text');
    if (actionsDiv && statusText) {
        if (report.status === 'pending') {
            actionsDiv.style.display = 'block';
            statusText.style.display = 'none';
        } else {
            actionsDiv.style.display = 'none';
            statusText.style.display = 'block';
            statusText.innerHTML = '<i class="fa-solid fa-lock"></i> Este reporte ya fue ' + label.toLowerCase() + '.';
        }
    }

    const overlay = document.getElementById('reportModal');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('reportModal').style.display = 'none';
    document.body.style.overflow = '';
}

function submitReject() {
    const rejectForm = document.getElementById('rejectForm');
    if (rejectForm) rejectForm.submit();
}

document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
