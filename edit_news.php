<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    header("Location: login");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: news");
    exit();
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM news WHERE id=?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    header("Location: news");
    exit();
}

$news_item = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_news'])) {
    csrf_verify();

    $title    = clean_input($_POST['title']);
    $badge    = clean_input($_POST['badge']);
    $raw_content = $_POST['content'] ?? '';
    $content  = sanitize_html($raw_content);
    $image_url = $news_item['image_url'];

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
        $tmp_name  = $_FILES['image_file']['tmp_name'];
        $file_size = $_FILES['image_file']['size'];

        if ($file_size > 5 * 1024 * 1024) {
            $upload_error = "La imagen no puede superar los 5MB.";
        } else {
            $mime = mime_content_type($tmp_name);

            if (extension_loaded('gd') && function_exists('imagecreatefrompng')) {
                $image = null;
                if ($mime == 'image/jpeg') $image = imagecreatefromjpeg($tmp_name);
                elseif ($mime == 'image/png')  $image = imagecreatefrompng($tmp_name);
                elseif ($mime == 'image/webp') $image = imagecreatefromwebp($tmp_name);
                elseif ($mime == 'image/gif')  $image = imagecreatefromgif($tmp_name);

                if ($image !== null) {
                    $orig_w = imagesx($image);
                    $orig_h = imagesy($image);
                    $max_w  = 1920;

                    if ($orig_w > $max_w) {
                        $ratio   = $max_w / $orig_w;
                        $new_w   = $max_w;
                        $new_h   = (int)($orig_h * $ratio);
                        $resized = imagecreatetruecolor($new_w, $new_h);
                        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
                        imagedestroy($image);
                        $image = $resized;
                    }

                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);

                    $filename    = 'news_' . time() . '_' . rand(1000, 9999) . '.webp';
                    $temp_target_file = sys_get_temp_dir() . '/' . $filename;

                    imagewebp($image, $temp_target_file, 82);
                    imagedestroy($image);

                    $upload_res = upload_to_web_api($temp_target_file, $filename);
                    if (file_exists($temp_target_file)) {
                        unlink($temp_target_file); // Eliminar archivo temporal local
                    }

                    if ($upload_res['success']) {
                        if (strpos($news_item['image_url'], 'uploads/news/') === 0) {
                            delete_from_web_api($news_item['image_url']);
                        }
                        $image_url = $upload_res['image_url'];
                    } else {
                        $upload_error = "No se pudo subir la imagen al servidor web: " . htmlspecialchars($upload_res['error']);
                    }
                }
            } else {
                // Fallback sin GD: subida directa manteniendo extensión original
                $ext = 'jpg';
                if ($mime == 'image/png') $ext = 'png';
                elseif ($mime == 'image/webp') $ext = 'webp';
                elseif ($mime == 'image/gif') $ext = 'gif';

                $filename    = 'news_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

                $upload_res = upload_to_web_api($tmp_name, $filename);
                if ($upload_res['success']) {
                    if (strpos($news_item['image_url'], 'uploads/news/') === 0) {
                        delete_from_web_api($news_item['image_url']);
                    }
                    $image_url = $upload_res['image_url'];
                } else {
                    $upload_error = "No se pudo subir la imagen al servidor web: " . htmlspecialchars($upload_res['error']);
                }
            }
        }
    }

    if (!isset($upload_error)) {
        $stmt_upd = $conn->prepare("UPDATE news SET title=?, badge=?, image_url=?, content=? WHERE id=?");
        $stmt_upd->execute([$title, $badge, $image_url, $content, $id]);

        log_action("EDIT_NEWS", "Editó la noticia: $title");
        header("Location: news");
        exit();
    }
}

require_once 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<style>
    .ql-toolbar { background: rgba(255,255,255,0.05); border: 1px solid var(--border) !important; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    .ql-container { border: 1px solid var(--border) !important; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; background: rgba(0,0,0,0.3); min-height: 400px; font-family: 'Outfit', sans-serif; font-size: 1.1rem; }
    .ql-editor { color: #fff; }
    .ql-stroke { stroke: #fff !important; }
    .ql-fill { fill: #fff !important; }
    .ql-picker { color: #fff !important; }
</style>

<div style="margin-bottom: 2rem;">
    <a href="news" style="color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; font-weight:800; transition:0.3s;" onmouseover="this.style.color='#fff'; this.style.transform='translateX(-5px)'" onmouseout="this.style.color='var(--text-muted)'; this.style.transform='none'"><i class="fa-solid fa-arrow-left"></i> Volver a Noticias</a>
</div>

<div class="header">
    <div>
        <h1>Editar Noticia</h1>
        <p>Actualiza la información de la noticia.</p>
    </div>
</div>

<?php if (isset($upload_error)): ?>
<div style="background:rgba(239,68,68,0.1); color:#ef4444; padding:1rem; border-radius:10px; margin-bottom:1.5rem; border:1px solid rgba(239,68,68,0.2);">
    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($upload_error); ?>
</div>
<?php endif; ?>

<div style="background:var(--bg-card); padding:3rem; border-radius:20px; border:1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    <form method="POST" action="" enctype="multipart/form-data" id="newsForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="edit_news" value="1">

        <div style="display:flex; gap:2rem; flex-wrap:wrap; margin-bottom:2rem;">
            <div style="flex:1; min-width:300px;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Título de la Noticia</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($news_item['title']); ?>" required maxlength="255" style="width:100%; padding:1rem; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff; font-size:1.1rem;">
            </div>
            <div style="width:250px;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Categoría / Etiqueta</label>
                <input type="text" name="badge" value="<?php echo htmlspecialchars($news_item['badge']); ?>" required maxlength="50" style="width:100%; padding:1rem; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff; font-size:1.1rem;">
            </div>
        </div>

        <div style="margin-bottom:2rem;">
            <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Imagen Principal <span style="color:var(--text-muted); font-weight:400;">(Opcional — máx. 5MB)</span></label>
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" style="width:100%; padding:1rem; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff;">
            <div style="margin-top: 1rem; color:var(--text-muted); font-size:0.9rem;">
                <img src="<?php echo rtrim(WEB_PUBLIC_URL, '/') . '/' . htmlspecialchars($news_item['image_url']); ?>" style="width:100px; border-radius:8px; vertical-align:middle; margin-right:10px;" onerror="this.style.display='none'">
                Imagen actual
            </div>
        </div>

        <div style="margin-bottom:2rem;">
            <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Contenido Completo</label>
            <input type="hidden" name="content" id="contentInput">
            <div id="editor"></div>
        </div>

        <button type="submit" style="width:100%; padding:1.2rem; background:var(--primary); color:#fff; border:none; border-radius:12px; font-weight:800; font-size:1.2rem; cursor:pointer; box-shadow:0 10px 20px rgba(59,130,246,0.3); transition:0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        }
    });

    // Load existing content after Quill initializes
    quill.clipboard.dangerouslyPasteHTML(0, <?php echo json_encode($news_item['content']); ?>);

    document.getElementById('newsForm').onsubmit = function() {
        var content = document.querySelector('.ql-editor').innerHTML;
        if (content === '<p><br></p>') {
            alert('El contenido no puede estar vacío.');
            return false;
        }
        document.getElementById('contentInput').value = content;
        return true;
    };
</script>

<?php require_once 'includes/footer.php'; ?>
