<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'manager', 'admin'])) {
    header("Location: dashboard");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard");
    exit();
}

$app_id = (int)$_GET['id'];
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE id=?");
$app_stmt->execute([$app_id]);
$app_details = $app_stmt->fetch();

if (!$app_details) {
    header("Location: dashboard");
    exit();
}

$back_view = isset($_GET['back']) ? clean_input($_GET['back']) : 'staff';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $app_name = $app_details['minecraft_name'];
    if ($action == 'approve') {
        $upd = $conn->prepare("UPDATE applications SET status='approved' WHERE id=?");
        $upd->execute([$app_id]);
        log_action("APPROVE_APP", "Aprobó la postulación de $app_name desde Detalles");
    } elseif ($action == 'reject') {
        $upd = $conn->prepare("UPDATE applications SET status='rejected' WHERE id=?");
        $upd->execute([$app_id]);
        log_action("REJECT_APP", "Rechazó la postulación de $app_name desde Detalles");
    }
    header("Location: app_details?id=$app_id&back=$back_view");
    exit();
}

// Parse experience field (formatted as **Label**\nAnswer\n)
$qa_pairs = [];
$raw_exp = $app_details['experience'];
if (trim($raw_exp) !== '') {
    // Split by ** markers
    $parts = preg_split('/\*\*(.*?)\*\*/s', $raw_exp, -1, PREG_SPLIT_DELIM_CAPTURE);
    // parts: [before, label, answer, label, answer, ...]
    for ($i = 1; $i < count($parts); $i += 2) {
        $label  = trim($parts[$i]);
        $answer = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';
        if ($label !== '') {
            $qa_pairs[] = ['label' => $label, 'answer' => $answer];
        }
    }
}

// Job type icons and colors
$job_meta = [
    'helper'   => ['icon' => 'fa-user-shield',   'color' => '#3b82f6'],
    'builder'  => ['icon' => 'fa-hammer',         'color' => '#10b981'],
    'dev'      => ['icon' => 'fa-code',           'color' => '#ef4444'],
    'youtuber' => ['icon' => 'fa-youtube',        'color' => '#ff0000', 'brand' => true],
    'tiktoker' => ['icon' => 'fa-tiktok',         'color' => '#00f2fe', 'brand' => true],
    'streamer' => ['icon' => 'fa-twitch',         'color' => '#9146ff', 'brand' => true],
];
$jm = $job_meta[$app_details['job_type']] ?? ['icon' => 'fa-user', 'color' => '#3b82f6'];
$icon_class = ($jm['brand'] ?? false) ? 'fa-brands' : 'fa-solid';

require_once 'includes/header.php';
?>

<style>
    .app-detail-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }

    /* Header banner */
    .app-header-banner {
        padding: 2.5rem 3rem;
        background: linear-gradient(135deg, rgba(13,20,33,0.95), rgba(8,12,20,0.95));
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    .app-header-banner::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(59,130,246,0.06), transparent 60%);
        pointer-events: none;
    }

    .app-job-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
    }

    /* Info tiles */
    .info-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1px;
        background: var(--border);
        border-bottom: 1px solid var(--border);
    }
    .info-tile {
        background: rgba(8,12,20,0.8);
        padding: 1.5rem 2rem;
    }
    .info-tile-label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #475569;
        margin-bottom: 0.5rem;
    }
    .info-tile-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
    }

    /* Q&A section */
    .qa-section {
        padding: 2.5rem 3rem;
    }
    .qa-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 3px;
        color: #334155;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .qa-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    .qa-item {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
        margin-bottom: 0.75rem;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.04);
        transition: border-color 0.2s ease;
    }
    .qa-item:hover {
        border-color: rgba(59,130,246,0.15);
    }
    .qa-question {
        background: rgba(59,130,246,0.05);
        padding: 1rem 1.5rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: #60a5fa;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid rgba(59,130,246,0.08);
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .qa-question::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        background: #3b82f6;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .qa-answer {
        background: rgba(0,0,0,0.25);
        padding: 1rem 1.5rem;
        font-size: 1rem;
        color: #e2e8f0;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .qa-answer.empty {
        color: #334155;
        font-style: italic;
        font-size: 0.9rem;
    }

    .action-btns a {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.8rem 1.8rem;
        border-radius: 10px;
        font-weight: 800;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-approve-lg {
        background: rgba(16,185,129,0.12);
        color: #10b981;
        border: 1px solid rgba(16,185,129,0.25);
    }
    .btn-approve-lg:hover {
        background: #10b981;
        color: #fff;
        box-shadow: 0 8px 20px rgba(16,185,129,0.3);
        transform: translateY(-2px);
    }
    .btn-reject-lg {
        background: rgba(239,68,68,0.12);
        color: #ef4444;
        border: 1px solid rgba(239,68,68,0.25);
    }
    .btn-reject-lg:hover {
        background: #ef4444;
        color: #fff;
        box-shadow: 0 8px 20px rgba(239,68,68,0.3);
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .app-header-banner, .qa-section { padding: 1.5rem; }
        .info-tile { padding: 1.2rem 1.5rem; }
    }
</style>

<!-- Back link -->
<div style="margin-bottom: 1.5rem;">
    <a href="<?php echo $back_view; ?>" style="color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; font-weight:700; font-size:0.9rem; transition:all 0.2s;" onmouseover="this.style.color='#fff';this.style.transform='translateX(-4px)'" onmouseout="this.style.color='var(--text-muted)';this.style.transform='none'">
        <i class="fa-solid fa-arrow-left"></i> Volver al listado
    </a>
</div>

<div class="app-detail-card">

    <!-- Header Banner -->
    <div class="app-header-banner">
        <div style="display:flex; align-items:center; gap:1.5rem;">
            <div class="app-job-icon" style="background: <?php echo $jm['color']; ?>1a; color: <?php echo $jm['color']; ?>;">
                <i class="<?php echo $icon_class; ?> <?php echo $jm['icon']; ?>"></i>
            </div>
            <div>
                <h2 style="font-size:1.8rem; font-weight:900; color:#fff; margin-bottom:0.5rem;">
                    Postulación de <?php echo htmlspecialchars($app_details['minecraft_name']); ?>
                </h2>
                <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                    <span style="background:<?php echo $jm['color']; ?>20; color:<?php echo $jm['color']; ?>; border:1px solid <?php echo $jm['color']; ?>40; padding:0.3rem 0.9rem; border-radius:8px; font-size:0.8rem; font-weight:800; letter-spacing:1px;">
                        <?php echo strtoupper($app_details['job_type']); ?>
                    </span>
                    <span class="status <?php echo $app_details['status']; ?>">
                        <?php echo $app_details['status'] === 'pending' ? 'Pendiente' : ($app_details['status'] === 'approved' ? 'Aprobada' : 'Rechazada'); ?>
                    </span>
                    <span style="color:#475569; font-size:0.85rem; display:flex; align-items:center; gap:0.4rem;">
                        <i class="fa-solid fa-clock"></i>
                        <?php echo date("d M, Y — H:i", strtotime($app_details['created_at'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if($app_details['status'] == 'pending'): ?>
        <div class="action-btns" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <a href="?id=<?php echo $app_details['id']; ?>&action=approve&back=<?php echo $back_view; ?>" class="btn-approve-lg">
                <i class="fa-solid fa-check"></i> Aprobar
            </a>
            <a href="?id=<?php echo $app_details['id']; ?>&action=reject&back=<?php echo $back_view; ?>" class="btn-reject-lg">
                <i class="fa-solid fa-xmark"></i> Rechazar
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Info Tiles -->
    <div class="info-tiles">
        <div class="info-tile">
            <div class="info-tile-label"><i class="fa-solid fa-gamepad" style="margin-right:0.4rem;"></i> Usuario de Minecraft</div>
            <div class="info-tile-value"><?php echo htmlspecialchars($app_details['minecraft_name']); ?></div>
        </div>
        <div class="info-tile">
            <div class="info-tile-label"><i class="fa-brands fa-discord" style="margin-right:0.4rem; color:#5865f2;"></i> Discord Tag</div>
            <div class="info-tile-value" style="color: #60a5fa;"><?php echo htmlspecialchars($app_details['discord_tag']); ?></div>
        </div>
        <?php if($app_details['age'] > 0): ?>
        <div class="info-tile">
            <div class="info-tile-label"><i class="fa-solid fa-cake-candles" style="margin-right:0.4rem;"></i> Edad</div>
            <div class="info-tile-value"><?php echo $app_details['age']; ?> años</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Q&A Section -->
    <div class="qa-section">
        <?php if (!empty($qa_pairs)): ?>
        <div class="qa-section-title">Respuestas del Formulario</div>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($qa_pairs as $i => $qa): ?>
            <div class="qa-item">
                <div class="qa-question">
                    <?php echo htmlspecialchars($qa['label']); ?>
                </div>
                <div class="qa-answer <?php echo empty($qa['answer']) ? 'empty' : ''; ?>">
                    <?php echo empty($qa['answer']) ? 'Sin respuesta' : htmlspecialchars($qa['answer']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php elseif (trim($raw_exp) !== ''): ?>
        <!-- Fallback for media/old format -->
        <div class="qa-section-title">Información Adicional</div>
        <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border); border-radius:14px; padding:1.5rem; color:#e2e8f0; font-size:1rem; line-height:1.7; white-space:pre-wrap; word-break:break-word;">
            <?php echo htmlspecialchars($raw_exp); ?>
        </div>

        <?php else: ?>
        <div class="qa-section-title">Respuestas del Formulario</div>
        <div style="text-align:center; padding:3rem 2rem; color:#334155;">
            <i class="fa-solid fa-inbox" style="font-size:2.5rem; margin-bottom:1rem; display:block; opacity:0.4;"></i>
            <p style="font-style:italic;">No hay respuestas adicionales registradas.</p>
        </div>
        <?php endif; ?>

        <?php if($app_details['status'] != 'pending'): ?>
        <div style="margin-top:2.5rem; padding-top:2rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:0.75rem;" class="action-btns">
            <a href="?id=<?php echo $app_details['id']; ?>&action=approve&back=<?php echo $back_view; ?>" class="btn-approve-lg" style="<?php echo $app_details['status'] === 'approved' ? 'opacity:0.4;pointer-events:none;' : ''; ?>">
                <i class="fa-solid fa-check"></i> Aprobar
            </a>
            <a href="?id=<?php echo $app_details['id']; ?>&action=reject&back=<?php echo $back_view; ?>" class="btn-reject-lg" style="<?php echo $app_details['status'] === 'rejected' ? 'opacity:0.4;pointer-events:none;' : ''; ?>">
                <i class="fa-solid fa-xmark"></i> Rechazar
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
