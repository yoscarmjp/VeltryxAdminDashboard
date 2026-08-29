<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin', 'manager'])) {
    header("Location: login");
    exit();
}

// form_questions and job_statuses are now managed in DB
// (tables: form_questions, job_statuses)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajax_update_status'])) {
        header('Content-Type: application/json');
        try {
            csrf_verify();
            $job    = clean_input($_POST['job'] ?? '');
            $status = clean_input($_POST['status'] ?? 'closed');
            $by     = $_SESSION['username'] ?? 'unknown';

            if (in_array($job, ['helper','builder','dev','youtuber','tiktoker','streamer'])
                && in_array($status, ['open','closed'])) {
                $stmt = $conn->prepare(
                    "INSERT INTO job_statuses (job, status, updated_by)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE status=VALUES(status), updated_by=VALUES(updated_by)"
                );
                $stmt->execute([$job, $status, $by]);
                echo json_encode(['success' => true, 'job' => $job, 'status' => $status]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos de postulación no válidos.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    } elseif (isset($_POST['json_data'])) {
        csrf_verify();
        $data = json_decode($_POST['json_data'], true);
        if ($data && isset($data['staff_questions'])) {
            try {
                $conn->beginTransaction();
                $conn->exec("DELETE FROM form_questions");
                $ins = $conn->prepare(
                    "INSERT INTO form_questions (id, label, type, required, sort_order, conditional_on, conditional_value)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                foreach ($data['staff_questions'] as $i => $q) {
                    $q_id   = preg_replace('/[^a-z0-9_]/i', '_', trim($q['id']));
                    $q_type = in_array($q['type'], ['text','textarea','number','yes_no']) ? $q['type'] : 'text';
                    $ins->execute([
                        $q_id,
                        substr(trim($q['label']), 0, 500),
                        $q_type,
                        empty($q['required']) ? 0 : 1,
                        $i,
                        $q['conditional_on'] ?? null,
                        $q['conditional_value'] ?? null,
                    ]);
                }
                $conn->commit();
                $success = "Configuración del formulario guardada con éxito.";
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Error al guardar: " . $e->getMessage();
            }
        } else {
            $error = "Datos de formulario inválidos.";
        }
    }
}

// Load questions from DB → rebuild array compatible with frontend JS
$fq_rows    = $conn->query("SELECT * FROM form_questions ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$form_questions_arr = array_map(function($r) {
    $q = ['id' => $r['id'], 'label' => $r['label'], 'type' => $r['type'], 'required' => (bool)$r['required']];
    if ($r['conditional_on'])    $q['conditional_on']    = $r['conditional_on'];
    if ($r['conditional_value']) $q['conditional_value'] = $r['conditional_value'];
    return $q;
}, $fq_rows);
// Encode for the JS builder
$json_content = json_encode(['staff_questions' => $form_questions_arr], JSON_UNESCAPED_UNICODE);

// Load statuses from DB
$rows     = $conn->query("SELECT job, status FROM job_statuses")->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses = array_merge(
    ['helper'=>'closed','builder'=>'closed','dev'=>'closed','youtuber'=>'closed','tiktoker'=>'closed','streamer'=>'closed'],
    $rows
);

require_once 'includes/header.php';
?>
<style>
    .builder-container { padding: 2rem; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); }
    .q-card { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; display: flex; gap: 1rem; align-items: flex-start; }
    .q-card input, .q-card select { padding: 0.8rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 8px; font-family: inherit; }
    .q-card input[type="text"] { flex-grow: 1; font-size: 1.1rem; }
    .btn-action-form { padding: 0.8rem 1.5rem; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; }
    .btn-action-form:hover { opacity: 0.8; transform: translateY(-2px); }
    .btn-action-form.danger { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.4); }
    .btn-action-form.danger:hover { background: #ef4444; color: #fff; }
    .btn-add { background: rgba(16,185,129,0.1); color: var(--accent); border: 1px solid rgba(16,185,129,0.3); padding: 1rem 2rem; border-radius: 12px; font-weight: bold; cursor: pointer; margin-top: 1rem; transition: 0.3s; width: 100%; font-size: 1.1rem; }
    .btn-add:hover { background: var(--accent); color: #fff; }

    /* Interactive Status Toggles */
    .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
    .status-toggle-card {
        background: rgba(18, 27, 41, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 1.2rem 1.5rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        position: relative;
        user-select: none;
    }
    .status-toggle-card:hover {
        border-color: rgba(255, 255, 255, 0.15);
        background: rgba(30, 45, 65, 0.4);
    }
    .status-toggle-card.is-open {
        border-color: rgba(16, 185, 129, 0.3);
        background: rgba(16, 185, 129, 0.04);
    }
    .status-toggle-card.is-open:hover {
        border-color: rgba(16, 185, 129, 0.5);
        background: rgba(16, 185, 129, 0.08);
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.05);
    }
    .status-toggle-card .status-text {
        color: #64748b;
        transition: color 0.3s ease;
    }
    .status-toggle-card.is-open .status-text {
        color: #10b981;
    }

    /* Switch track and thumb */
    .switch-track {
        width: 46px;
        height: 24px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50px;
        position: relative;
        transition: background 0.3s ease;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .status-toggle-card.is-open .switch-track {
        background: #10b981;
        border-color: #10b981;
    }
    .switch-thumb {
        width: 18px;
        height: 18px;
        background: #fff;
        border-radius: 50%;
        position: absolute;
        top: 2px;
        left: 2px;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    .status-toggle-card.is-open .switch-thumb {
        transform: translateX(22px);
    }
</style>

<div class="header">
    <div>
        <h1>Editor de Preguntas</h1>
        <p>Añade, edita o elimina las preguntas del formulario de postulación.</p>
    </div>
</div>

<?php if(isset($success)) echo "<div class='stat-card' style='border-color: #10b981; margin-bottom: 2rem; padding: 1.5rem;'><p style='color:#10b981; font-weight: bold;'><i class='fa-solid fa-check'></i> $success</p></div>"; ?>
<?php if(isset($error)) echo "<div class='stat-card' style='border-color: #ef4444; margin-bottom: 2rem; padding: 1.5rem;'><p style='color:#ef4444; font-weight: bold;'><i class='fa-solid fa-xmark'></i> $error</p></div>"; ?>

<div class="builder-container" style="margin-bottom: 3rem;">
    <h2>Estado de las Postulaciones</h2>
    <p style="color:var(--text-muted); margin-bottom: 2rem; font-size: 1rem;">Haz clic sobre la tarjeta de cualquier rango para abrir o cerrar sus postulaciones instantáneamente (se guarda automáticamente).</p>
    
    <h3 style="margin-bottom: 1.2rem; color: #fff; font-size: 1.2rem;">Postulaciones Staff</h3>
    <div class="status-grid">
        <!-- Ayudante (Helper) -->
        <div class="status-toggle-card <?php echo $statuses['helper'] === 'open' ? 'is-open' : ''; ?>" data-job="helper" onclick="toggleJobStatus('helper')">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem;"><i class="fa-solid fa-user-shield" style="margin-right: 0.5rem; color: var(--primary);"></i> Ayudante (Helper)</div>
                    <div class="status-text" style="font-size: 0.85rem; font-weight: 600;"><?php echo $statuses['helper'] === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas'; ?></div>
                </div>
                <div class="switch-track">
                    <div class="switch-thumb"></div>
                </div>
            </div>
        </div>

        <!-- Constructor -->
        <div class="status-toggle-card <?php echo $statuses['builder'] === 'open' ? 'is-open' : ''; ?>" data-job="builder" onclick="toggleJobStatus('builder')">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem;"><i class="fa-solid fa-hammer" style="margin-right: 0.5rem; color: var(--accent);"></i> Constructor (Builder)</div>
                    <div class="status-text" style="font-size: 0.85rem; font-weight: 600;"><?php echo $statuses['builder'] === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas'; ?></div>
                </div>
                <div class="switch-track">
                    <div class="switch-thumb"></div>
                </div>
            </div>
        </div>

        <!-- Desarrollador -->
        <div class="status-toggle-card <?php echo $statuses['dev'] === 'open' ? 'is-open' : ''; ?>" data-job="dev" onclick="toggleJobStatus('dev')">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem;"><i class="fa-solid fa-code" style="margin-right: 0.5rem; color: #ef4444;"></i> Desarrollador (Dev)</div>
                    <div class="status-text" style="font-size: 0.85rem; font-weight: 600;"><?php echo $statuses['dev'] === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas'; ?></div>
                </div>
                <div class="switch-track">
                    <div class="switch-thumb"></div>
                </div>
            </div>
        </div>
    </div>

    <h3 style="margin-bottom: 1.2rem; color: #fff; font-size: 1.2rem;">Postulaciones de Creadores (Media)</h3>
    <div class="status-grid">
        <!-- Youtuber -->
        <div class="status-toggle-card <?php echo $statuses['youtuber'] === 'open' ? 'is-open' : ''; ?>" data-job="youtuber" onclick="toggleJobStatus('youtuber')">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem;"><i class="fa-brands fa-youtube" style="margin-right: 0.5rem; color: #ff0000;"></i> Youtuber</div>
                    <div class="status-text" style="font-size: 0.85rem; font-weight: 600;"><?php echo $statuses['youtuber'] === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas'; ?></div>
                </div>
                <div class="switch-track">
                    <div class="switch-thumb"></div>
                </div>
            </div>
        </div>

        <!-- TikToker -->
        <div class="status-toggle-card <?php echo $statuses['tiktoker'] === 'open' ? 'is-open' : ''; ?>" data-job="tiktoker" onclick="toggleJobStatus('tiktoker')">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem;"><i class="fa-brands fa-tiktok" style="margin-right: 0.5rem; color: #00f2fe;"></i> TikToker</div>
                    <div class="status-text" style="font-size: 0.85rem; font-weight: 600;"><?php echo $statuses['tiktoker'] === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas'; ?></div>
                </div>
                <div class="switch-track">
                    <div class="switch-thumb"></div>
                </div>
            </div>
        </div>

        <!-- Streamer -->
        <div class="status-toggle-card <?php echo $statuses['streamer'] === 'open' ? 'is-open' : ''; ?>" data-job="streamer" onclick="toggleJobStatus('streamer')">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem;"><i class="fa-brands fa-twitch" style="margin-right: 0.5rem; color: #9146ff;"></i> Streamer</div>
                    <div class="status-text" style="font-size: 0.85rem; font-weight: 600;"><?php echo $statuses['streamer'] === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas'; ?></div>
                </div>
                <div class="switch-track">
                    <div class="switch-thumb"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="builder-container">
    <div id="questions-list"></div>
    
    <button type="button" class="btn-add" onclick="addQuestion()"><i class="fa-solid fa-plus"></i> Añadir Nueva Pregunta</button>

    <form method="POST" action="" id="save-form" style="margin-top: 3rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2rem; text-align: right;">
        <input type="hidden" name="json_data" id="json_data">
        <button type="button" class="btn-action-form" style="font-size: 1.1rem; padding: 1rem 3rem;" onclick="saveForm()"><i class="fa-solid fa-save"></i> Guardar Formulario</button>
    </form>
</div>

<script>
    let formConfig = <?php echo $json_content; ?>;
    if(!formConfig.staff_questions) formConfig.staff_questions = [];
    let questions = formConfig.staff_questions;

    function render() {
        const list = document.getElementById('questions-list');
        list.innerHTML = '';
        questions.forEach((q, index) => {
            let html = `
            <div class="q-card">
                <div style="font-weight: 900; color: #3b82f6; font-size: 1.5rem; padding-top:0.3rem;">${index + 1}.</div>
                <div style="flex-grow:1; display:flex; flex-direction:column; gap:0.5rem;">
                    <input type="text" value="${q.label}" onchange="updateQ(${index}, 'label', this.value)" placeholder="Pregunta / Etiqueta">
                    <div style="display:flex; gap:1rem; margin-top:0.5rem; flex-wrap: wrap;">
                        <select onchange="updateQ(${index}, 'type', this.value)">
                            <option value="text" ${q.type=='text'?'selected':''}>Texto Corto</option>
                            <option value="textarea" ${q.type=='textarea'?'selected':''}>Texto Largo</option>
                            <option value="number" ${q.type=='number'?'selected':''}>Número</option>
                            <option value="yes_no" ${q.type=='yes_no'?'selected':''}>Sí / No</option>
                        </select>
                        <label style="display:flex; align-items:center; gap:0.5rem; color:#cbd5e1; cursor:pointer;">
                            <input type="checkbox" ${q.required?'checked':''} onchange="updateQ(${index}, 'required', this.checked)"> Obligatorio
                        </label>
                        <input type="text" value="${q.id}" onchange="updateQ(${index}, 'id', this.value)" placeholder="ID Interno (sin espacios)" style="width: 200px;">
                    </div>
                </div>
                <button class="btn-action-form danger" title="Eliminar" onclick="removeQ(${index})"><i class="fa-solid fa-trash"></i></button>
            </div>
            `;
            list.innerHTML += html;
        });
    }

    function updateQ(index, field, value) {
        questions[index][field] = value;
    }

    function removeQ(index) {
        if(confirm("¿Seguro que deseas eliminar esta pregunta?")) {
            questions.splice(index, 1);
            render();
        }
    }

    function addQuestion() {
        questions.push({
            id: 'pregunta_' + Date.now(),
            label: 'Nueva Pregunta',
            type: 'text',
            required: true
        });
        render();
    }

    function saveForm() {
        let ids = new Set();
        for(let i=0; i<questions.length; i++) {
            if(!questions[i].id || questions[i].id.trim() === '') {
                alert('Error: Todas las preguntas deben tener un ID interno.');
                return;
            }
            if(ids.has(questions[i].id)) {
                alert('Error: Los IDs internos deben ser únicos. Duplicado: ' + questions[i].id);
                return;
            }
            ids.add(questions[i].id);
        }

        document.getElementById('json_data').value = JSON.stringify({staff_questions: questions});
        document.getElementById('save-form').submit();
    }

    render();

    // ──────────────────────────────────────────
    //  Toggle de postulaciones (AJAX, sin reload)
    // ──────────────────────────────────────────
    const CSRF_TOKEN = '<?php echo htmlspecialchars(csrf_token()); ?>';

    function showToast(message, type = 'success') {
        let toast = document.getElementById('status-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'status-toast';
            toast.style.cssText = `
                position: fixed; bottom: 2rem; right: 2rem; z-index: 9999;
                padding: 1rem 1.5rem; border-radius: 12px; font-weight: 700;
                font-size: 0.95rem; display: flex; align-items: center; gap: 0.75rem;
                backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.4);
                transform: translateY(10px); opacity: 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
            `;
            document.body.appendChild(toast);
        }

        if (type === 'success') {
            toast.style.background = 'rgba(16,185,129,0.15)';
            toast.style.border = '1px solid rgba(16,185,129,0.4)';
            toast.style.color = '#10b981';
            toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + message;
        } else {
            toast.style.background = 'rgba(239,68,68,0.15)';
            toast.style.border = '1px solid rgba(239,68,68,0.4)';
            toast.style.color = '#ef4444';
            toast.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + message;
        }

        // Animate in
        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        });

        // Animate out after 2.5s
        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => {
            toast.style.transform = 'translateY(10px)';
            toast.style.opacity = '0';
        }, 2500);
    }

    function toggleJobStatus(job) {
        const card = document.querySelector(`.status-toggle-card[data-job="${job}"]`);
        if (!card) return;

        const isCurrentlyOpen = card.classList.contains('is-open');
        const newStatus = isCurrentlyOpen ? 'closed' : 'open';

        // Optimistic UI update
        card.classList.toggle('is-open', !isCurrentlyOpen);
        const statusText = card.querySelector('.status-text');
        if (statusText) {
            statusText.textContent = newStatus === 'open' ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas';
        }

        // AJAX call
        const formData = new FormData();
        formData.append('ajax_update_status', '1');
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('job', job);
        formData.append('status', newStatus);

        fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const label = newStatus === 'open' ? 'abierta' : 'cerrada';
                    showToast(`Postulación de <strong>${job}</strong> ${label} exitosamente.`, 'success');
                } else {
                    // Revert on failure
                    card.classList.toggle('is-open', isCurrentlyOpen);
                    if (statusText) {
                        statusText.textContent = isCurrentlyOpen ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas';
                    }
                    showToast('Error al guardar: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(() => {
                // Revert on network error
                card.classList.toggle('is-open', isCurrentlyOpen);
                if (statusText) {
                    statusText.textContent = isCurrentlyOpen ? 'Postulaciones Abiertas' : 'Postulaciones Cerradas';
                }
                showToast('Error de red. Intenta nuevamente.', 'error');
            });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
