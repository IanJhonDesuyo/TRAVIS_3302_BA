<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

const ANNOUNCEMENT_UPLOAD_MAX_BYTES = 5242880;
const ANNOUNCEMENT_UPLOAD_RELATIVE_DIR = 'assets/uploads/announcements';

function cms_post(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}

function cms_types(): array {
    return ['traffic advisory','tmo activity','public notice','event','road closure','emergency notice'];
}

function cms_statuses(): array {
    return ['draft','published','archived'];
}

function cms_project_root(): string {
    return dirname(__DIR__, 2);
}

function cms_upload_dir(): string {
    return cms_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ANNOUNCEMENT_UPLOAD_RELATIVE_DIR);
}

function cms_image_url(?string $path): string {
    return $path ? '../../' . ltrim(str_replace('\\', '/', $path), '/') : '';
}

function cms_delete_image(?string $relativePath): void {
    if (!$relativePath) return;
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!str_starts_with($normalized, ANNOUNCEMENT_UPLOAD_RELATIVE_DIR . '/')) return;
    $absolute = cms_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (is_file($absolute)) @unlink($absolute);
}

function cms_upload_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if (($file['size'] ?? 0) <= 0 || (int)$file['size'] > ANNOUNCEMENT_UPLOAD_MAX_BYTES) throw new RuntimeException('Image must not exceed 5 MB.');

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid uploaded image.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Only JPG, PNG, and WebP images are allowed.');

    $dir = cms_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Unable to create upload directory.');

    $filename = 'announcement_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $filename)) throw new RuntimeException('Unable to save uploaded image.');

    return ANNOUNCEMENT_UPLOAD_RELATIVE_DIR . '/' . $filename;
}

function cms_find(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM public_announcements WHERE announcement_id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['announcement_id'] ?? 0);
        $existing = $action === 'update' ? cms_find($conn, $id) : null;
        $title = cms_post('title');
        $type = strtolower(cms_post('announcement_type'));
        $content = cms_post('content');
        $publishDate = cms_post('publish_date');
        $status = strtolower(cms_post('status', 'draft'));
        $newImage = null;

        try {
            if ($action === 'update' && !$existing) throw new RuntimeException('Announcement not found.');
            if ($title === '' || $content === '' || $publishDate === '') throw new RuntimeException('Please complete all required fields.');
            if (!in_array($type, cms_types(), true)) throw new RuntimeException('Invalid announcement type.');
            if (!in_array($status, cms_statuses(), true)) throw new RuntimeException('Invalid status.');

            $newImage = cms_upload_image($_FILES['image'] ?? []);
            $imagePath = $existing['image_path'] ?? null;
            if ($newImage !== null) $imagePath = $newImage;
            if (isset($_POST['remove_image']) && $newImage === null) $imagePath = null;

            if ($action === 'create') {
                $stmt = $conn->prepare('INSERT INTO public_announcements (title, announcement_type, content, image_path, publish_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssssi', $title, $type, $content, $imagePath, $publishDate, $status, $currentUserId);
            } else {
                $stmt = $conn->prepare('UPDATE public_announcements SET title=?, announcement_type=?, content=?, image_path=?, publish_date=?, status=? WHERE announcement_id=?');
                $stmt->bind_param('ssssssi', $title, $type, $content, $imagePath, $publishDate, $status, $id);
            }

            if (!$stmt->execute()) throw new RuntimeException('Unable to save the announcement.');

            if ($action === 'update' && $existing) {
                if ($newImage !== null && !empty($existing['image_path'])) cms_delete_image($existing['image_path']);
                if (isset($_POST['remove_image']) && $newImage === null && !empty($existing['image_path'])) cms_delete_image($existing['image_path']);
            }

            $message = $action === 'create' ? 'Announcement created successfully.' : 'Announcement updated successfully.';
            $messageType = 'success';
        } catch (Throwable $e) {
            if ($newImage) cms_delete_image($newImage);
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action === 'status') {
        $id = (int)($_POST['announcement_id'] ?? 0);
        $status = strtolower(cms_post('status'));
        if ($id > 0 && in_array($status, cms_statuses(), true)) {
            $stmt = $conn->prepare('UPDATE public_announcements SET status=? WHERE announcement_id=?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $message = 'Announcement status updated.';
            $messageType = 'success';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$typeFilter = strtolower(trim((string)($_GET['type'] ?? '')));
$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
$where = [];
$params = [];
$paramTypes = '';

if ($search !== '') {
    $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
    $like = "%{$search}%";
    array_push($params, $like, $like);
    $paramTypes .= 'ss';
}
if ($typeFilter !== '') { $where[] = 'a.announcement_type=?'; $params[] = $typeFilter; $paramTypes .= 's'; }
if ($statusFilter !== '') { $where[] = 'a.status=?'; $params[] = $statusFilter; $paramTypes .= 's'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT a.*, u.full_name AS created_by_name FROM public_announcements a LEFT JOIN users u ON u.user_id=a.created_by {$whereSql} ORDER BY a.publish_date DESC, a.announcement_id DESC LIMIT 100";
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $posts = fetch_all($sql);
}

$published = scalar("SELECT COUNT(*) FROM public_announcements WHERE status='published'", 0);
$drafts = scalar("SELECT COUNT(*) FROM public_announcements WHERE status='draft'", 0);
$archived = scalar("SELECT COUNT(*) FROM public_announcements WHERE status='archived'", 0);
$scheduled = scalar("SELECT COUNT(*) FROM public_announcements WHERE status='published' AND publish_date > NOW()", 0);

page_start('Public Website', 'public', 'Search public posts...');
?>
<style>
.cms-thumb{width:88px;height:60px;object-fit:cover;border-radius:10px;border:1px solid var(--border);background:#edf2f7}
.cms-placeholder{width:88px;height:60px;border-radius:10px;background:linear-gradient(135deg,#e8f3ff,#ddf7f3);display:grid;place-items:center;color:var(--primary);font-size:1.35rem;border:1px solid var(--border)}
.cms-table{max-height:620px;overflow:auto;border:1px solid var(--border);border-radius:14px}
.cms-table thead th{position:sticky;top:0;z-index:5;background:#f8fbff}
.cms-preview{width:100%;max-height:380px;object-fit:cover;border-radius:14px}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div><h3 class="page-title">Public Website CMS</h3><p class="page-sub">Create, upload, publish, edit, and archive announcements for the public TRAVIS website.</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1"></i>New Announcement</button>
</div>

<?php if ($message): feedback_notice($message, $messageType); endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-megaphone"></i></div><div class="stat-label">Published</div><div class="stat-value"><?= num($published) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-pencil-square"></i></div><div class="stat-label">Drafts</div><div class="stat-value"><?= num($drafts) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-clock-history"></i></div><div class="stat-label">Scheduled</div><div class="stat-value"><?= num($scheduled) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-danger"><i class="bi bi-archive"></i></div><div class="stat-label">Archived</div><div class="stat-value"><?= num($archived) ?></div></div></div>
</div>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div><h6 class="mb-0">Announcements</h6><small class="text-muted">Published posts will be displayed by the separate public website.</small></div>
    <form method="get" class="d-flex flex-wrap gap-2">
      <input class="form-control form-control-sm" name="search" value="<?= esc($search) ?>" placeholder="Search title or content...">
      <select class="form-select form-select-sm" name="type"><option value="">All Types</option><?php foreach(cms_types() as $type): ?><option value="<?= esc($type) ?>" <?= $typeFilter===$type?'selected':'' ?>><?= esc(ucwords($type)) ?></option><?php endforeach; ?></select>
      <select class="form-select form-select-sm" name="status"><option value="">All Statuses</option><?php foreach(cms_statuses() as $status): ?><option value="<?= esc($status) ?>" <?= $statusFilter===$status?'selected':'' ?>><?= esc(ucfirst($status)) ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-primary">Filter</button>
      <?php if ($search!=='' || $typeFilter!=='' || $statusFilter!==''): ?><a class="btn btn-sm btn-light" href="<?= esc(app_url('public-website.php')) ?>">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if (!$posts): ?><?php empty_state('No announcements matched the current filters.'); ?>
  <?php else: ?>
    <div class="cms-table"><table class="table table-hover align-middle mb-0"><thead><tr><th>Image</th><th>Title</th><th>Type</th><th>Publish Date</th><th>Status</th><th>Created By</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach($posts as $post): ?>
      <tr>
        <td><?php if(!empty($post['image_path'])): ?><img class="cms-thumb" src="<?= esc(cms_image_url($post['image_path'])) ?>" alt="<?= esc($post['title']) ?>"><?php else: ?><div class="cms-placeholder"><i class="bi bi-image"></i></div><?php endif; ?></td>
        <td><div class="fw-semibold"><?= esc($post['title']) ?></div><small class="text-muted"><?= esc(mb_strimwidth(strip_tags((string)$post['content']),0,95,'...')) ?></small></td>
        <td><?= esc(ucwords($post['announcement_type'])) ?></td>
        <td><?= esc($post['publish_date']) ?></td>
        <td><span class="tag <?= tag_class($post['status']) ?>"><?= esc(ucfirst($post['status'])) ?></span></td>
        <td><?= esc($post['created_by_name'] ?? 'Not recorded') ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#view<?= (int)$post['announcement_id'] ?>"><i class="bi bi-eye"></i></button>
          <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$post['announcement_id'] ?>"><i class="bi bi-pencil-square"></i></button>
          <?php if($post['status']!=='published'): ?><form method="post" class="d-inline"><input type="hidden" name="action" value="status"><input type="hidden" name="announcement_id" value="<?= (int)$post['announcement_id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-sm btn-success"><i class="bi bi-send-check"></i></button></form><?php else: ?><form method="post" class="d-inline"><input type="hidden" name="action" value="status"><input type="hidden" name="announcement_id" value="<?= (int)$post['announcement_id'] ?>"><input type="hidden" name="status" value="draft"><button class="btn btn-sm btn-light text-warning"><i class="bi bi-arrow-counterclockwise"></i></button></form><?php endif; ?>
          <?php if($post['status']!=='archived'): ?><form method="post" class="d-inline" data-confirm="The announcement will be removed from active publication and retained in the archive." data-confirm-title="Archive this announcement?" data-confirm-label="Archive announcement" data-confirm-eyebrow="Content status" data-confirm-tone="danger"><input type="hidden" name="action" value="status"><input type="hidden" name="announcement_id" value="<?= (int)$post['announcement_id'] ?>"><input type="hidden" name="status" value="archived"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-archive"></i></button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form method="post" enctype="multipart/form-data">
  <div class="modal-header"><div><h5 class="modal-title">Create Announcement</h5><small class="text-muted">Add content and an optional cover image.</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="create"><div class="row g-3">
    <div class="col-lg-8"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="255" required></div>
    <div class="col-lg-4"><label class="form-label">Type</label><select class="form-select" name="announcement_type" required><option value="">Select type</option><?php foreach(cms_types() as $type): ?><option value="<?= esc($type) ?>"><?= esc(ucwords($type)) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Content</label><textarea class="form-control" name="content" rows="8" required></textarea></div>
    <div class="col-lg-5"><label class="form-label">Cover Image</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="text-muted">JPG, PNG, or WebP; maximum 5 MB.</small></div>
    <div class="col-lg-4"><label class="form-label">Publish Date</label><input class="form-control" type="datetime-local" name="publish_date" value="<?= date('Y-m-d\TH:i') ?>" required></div>
    <div class="col-lg-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Announcement</button></div>
</form></div></div></div>

<?php foreach($posts as $post): ?>
<div class="modal fade" id="view<?= (int)$post['announcement_id'] ?>" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><div><span class="tag <?= tag_class($post['status']) ?>"><?= esc(ucfirst($post['status'])) ?></span><h5 class="modal-title mt-2"><?= esc($post['title']) ?></h5></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><?php if(!empty($post['image_path'])): ?><img class="cms-preview mb-4" src="<?= esc(cms_image_url($post['image_path'])) ?>" alt="<?= esc($post['title']) ?>"><?php endif; ?><div class="row g-3 mb-4"><div class="col-md-4"><strong>Type</strong><br><?= esc(ucwords($post['announcement_type'])) ?></div><div class="col-md-4"><strong>Publish Date</strong><br><?= esc($post['publish_date']) ?></div><div class="col-md-4"><strong>Created By</strong><br><?= esc($post['created_by_name'] ?? 'Not recorded') ?></div></div><div><?= nl2br(esc($post['content'])) ?></div></div>
  <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<div class="modal fade" id="edit<?= (int)$post['announcement_id'] ?>" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form method="post" enctype="multipart/form-data">
  <div class="modal-header"><div><h5 class="modal-title">Edit Announcement</h5><small class="text-muted"><?= esc($post['title']) ?></small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="update"><input type="hidden" name="announcement_id" value="<?= (int)$post['announcement_id'] ?>"><div class="row g-3">
    <div class="col-lg-8"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="255" value="<?= esc($post['title']) ?>" required></div>
    <div class="col-lg-4"><label class="form-label">Type</label><select class="form-select" name="announcement_type" required><?php foreach(cms_types() as $type): ?><option value="<?= esc($type) ?>" <?= $post['announcement_type']===$type?'selected':'' ?>><?= esc(ucwords($type)) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Content</label><textarea class="form-control" name="content" rows="8" required><?= esc($post['content']) ?></textarea></div>
    <div class="col-lg-5"><label class="form-label">Replace Image</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><?php if(!empty($post['image_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" id="remove<?= (int)$post['announcement_id'] ?>"><label class="form-check-label" for="remove<?= (int)$post['announcement_id'] ?>">Remove current image</label></div><?php endif; ?></div>
    <div class="col-lg-4"><label class="form-label">Publish Date</label><input class="form-control" type="datetime-local" name="publish_date" value="<?= esc(date('Y-m-d\TH:i', strtotime($post['publish_date']))) ?>" required></div>
    <div class="col-lg-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach(cms_statuses() as $status): ?><option value="<?= esc($status) ?>" <?= $post['status']===$status?'selected':'' ?>><?= esc(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button></div>
</form></div></div></div>
<?php endforeach; ?>

<?php page_end(); ?>
