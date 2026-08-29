<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    header("Location: login");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_news'])) {
    csrf_verify();

    $title    = clean_input($_POST['title']);
    $badge    = clean_input($_POST['badge']);
    $raw_content = $_POST['content'] ?? '';
    $content  = sanitize_html($raw_content);
    $image_url = '';

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
                        $image_url = $upload_res['image_url'];
                    } else {
                        $upload_error = "No se pudo subir la imagen al servidor web: " . htmlspecialchars($upload_res['error']);
                    }
                } else {
                    $image_url = 'https://images.unsplash.com/photo-1607513837943-e69d7bdf92eb?w=800&q=80';
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
                    $image_url = $upload_res['image_url'];
                } else {
                    $upload_error = "No se pudo subir la imagen al servidor web: " . htmlspecialchars($upload_res['error']);
                }
            }
        }
    } else {
        $image_url = 'https://images.unsplash.com/photo-1607513837943-e69d7bdf92eb?w=800&q=80';
    }

    if (!isset($upload_error)) {
        $stmt = $conn->prepare("INSERT INTO news (title, badge, image_url, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $badge, $image_url, $content]);

        log_action("CREATE_NEWS", "Creó la noticia: $title");
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
        <h1>Crear Noticia</h1>
        <p>Utiliza el editor avanzado para redactar el artículo.</p>
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
        <input type="hidden" name="create_news" value="1">

        <div style="display:flex; gap:2rem; flex-wrap:wrap; margin-bottom:2rem;">
            <div style="flex:1; min-width:300px;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Título de la Noticia</label>
                <input type="text" name="title" required maxlength="255" placeholder="Ej. Gran Apertura Veltryx..." style="width:100%; padding:1rem; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff; font-size:1.1rem;">
            </div>
            <div style="width:250px;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Categoría / Etiqueta</label>
                <input type="text" name="badge" required maxlength="50" placeholder="Ej. Actualización" style="width:100%; padding:1rem; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff; font-size:1.1rem;">
            </div>
        </div>

        <div style="margin-bottom:2rem;">
            <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Imagen Principal <span style="color:var(--text-muted); font-weight:400;">(Máx. 5MB — se convierte a WebP automáticamente)</span></label>
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" required style="width:100%; padding:1rem; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff;">
        </div>

        <div style="margin-bottom:2rem;">
            <label style="display:block; margin-bottom:0.5rem; font-weight:bold; color:var(--text-muted);">Contenido Completo</label>
            <input type="hidden" name="content" id="contentInput">
            <div id="editor"></div>
        </div>

        <button type="submit" style="width:100%; padding:1.2rem; background:var(--primary); color:#fff; border:none; border-radius:12px; font-weight:800; font-size:1.2rem; cursor:pointer; box-shadow:0 10px 20px rgba(59,130,246,0.3); transition:0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'"><i class="fa-solid fa-paper-plane"></i> Publicar Artículo</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Escribe el contenido aquí...',
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
