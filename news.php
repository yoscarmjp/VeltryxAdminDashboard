<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin'])) {
    header("Location: dashboard");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action == 'delete_news') {
        $stmt = $conn->prepare("SELECT title, image_url FROM news WHERE id=?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if ($res) {
            $title = $res['title'];
            $img = $res['image_url'];
            if (strpos($img, 'uploads/news/') === 0) {
                delete_from_web_api($img);
            }
            log_action("DELETE_NEWS", "Eliminó la noticia: $title");
        }
        $del = $conn->prepare("DELETE FROM news WHERE id=?");
        $del->execute([$id]);
    }
    header("Location: news");
    exit();
}

$news_stmt = $conn->query("SELECT * FROM news ORDER BY created_at DESC");
$news_list = $news_stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header">
    <div>
        <h1>Gestión de Noticias</h1>
        <p>Crea, edita o elimina las noticias del portal principal.</p>
    </div>
    <a href="create_news" style="background:var(--primary); color:#fff; text-decoration:none; display:inline-block; padding:0.8rem 1.5rem; border-radius:10px; font-weight:800; transition:0.3s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'"><i class="fa-solid fa-plus"></i> Nueva Noticia</a>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Noticias Publicadas</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Título</th>
                <th>Categoría</th>
                <th>Fecha</th>
                <th style="text-align: right;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($news_list as $n): ?>
            <tr>
                <td style="font-weight:bold;">
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <img src="<?php echo rtrim(WEB_PUBLIC_URL, '/') . '/' . htmlspecialchars($n['image_url']); ?>" style="width:40px; height:40px; border-radius:8px; object-fit:cover;" onerror="this.src='<?php echo htmlspecialchars($n['image_url']); ?>'">
                        <?php echo htmlspecialchars($n['title']); ?>
                    </div>
                </td>
                <td><span class="role-badge"><?php echo htmlspecialchars($n['badge']); ?></span></td>
                <td style="color:#94a3b8;"><?php echo date("d M, Y", strtotime($n['created_at'])); ?></td>
                <td style="text-align: right; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <a href="edit_news.php?id=<?php echo $n['id']; ?>" class="btn-action approve" title="Editar Noticia"><i class="fa-solid fa-pen-to-square"></i></a>
                    <a href="?action=delete_news&id=<?php echo $n['id']; ?>" class="btn-action reject" title="Eliminar Noticia" onclick="return confirm('¿Seguro que deseas eliminar esta noticia?');"><i class="fa-solid fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(count($news_list) == 0): ?>
            <tr>
                <td colspan="4">
                    <div class="empty-state">
                        <i class="fa-solid fa-newspaper"></i>
                        <h3>No hay noticias</h3>
                        <p>Crea tu primera noticia para que aparezca en el portal web.</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
