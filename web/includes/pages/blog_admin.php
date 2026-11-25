<?php
declare(strict_types=1);

$admin = require_admin();
blog_ensure();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'delete' && $id) {
        q('DELETE FROM blog_posts WHERE id = ?', [$id]);
        flash('Post deleted.');
        redirect('blog/admin');
    }
    if ($do === 'save') {
        $title = clean_text((string)($_POST['title'] ?? ''), 160);
        $slug = slugify((string)($_POST['slug'] ?? '')) ?: slugify($title);
        $excerpt = clean_text((string)($_POST['excerpt'] ?? ''), 300);
        $body = trim((string)($_POST['body'] ?? ''));
        $tags = clean_text((string)($_POST['tags'] ?? ''), 160);
        $cover = clean_text((string)($_POST['cover'] ?? ''), 255);
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $when = trim((string)($_POST['published_at'] ?? ''));
        $ts = $when !== '' ? strtotime($when) : false;
        if ($title === '' || $slug === '' || $body === '') {
            flash('Title and text are required.', 'err');
            redirect('blog/admin' . ($id ? '?id=' . $id : '?new=1'));
        }
        if ((int)val('SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id <> ?', [$slug, $id]) > 0) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
        }
        $old = $id ? row('SELECT status, published_at FROM blog_posts WHERE id = ?', [$id]) : null;
        $pubAt = null;
        if ($status === 'published') {
            $pubAt = $ts ? date('Y-m-d H:i:s', $ts) : (($old['published_at'] ?? null) ?: now());
        }
        if ($id) {
            q('UPDATE blog_posts SET slug=?, title=?, excerpt=?, body=?, tags=?, cover=?, status=?, published_at=?, updated_at=? WHERE id=?',
                [$slug, $title, $excerpt, $body, $tags, $cover, $status, $pubAt, now(), $id]);
        } else {
            q('INSERT INTO blog_posts (slug,title,excerpt,body,tags,cover,status,author,created_at,published_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$slug, $title, $excerpt, $body, $tags, $cover, $status, site_name(), now(), $pubAt, now()]);
            $id = (int)db()->lastInsertId();
        }
        flash($status === 'published' ? 'Saved and published.' : 'Saved as draft.');
        redirect('blog/admin?id=' . $id);
    }
}

layout_header('Blog admin');
$edit = $id ? row('SELECT * FROM blog_posts WHERE id = ?', [$id]) : null;
if (isset($_GET['new']) || $edit):
    $p = $edit ?: ['title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'tags' => '', 'cover' => '', 'status' => 'draft', 'published_at' => ''];
    ?>
<h1 class="pagetitle"><?= $edit ? 'Edit post' : 'New post' ?></h1>
<p><a href="<?= e(url('blog/admin')) ?>">&larr; All posts</a><?= $edit ? ' · <a href="' . e(url('blog/' . $p['slug'])) . '" target="_blank">Preview / view</a>' : '' ?></p>
<form method="post" class="card" id="bform">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="save">
  <label>Title <input name="title" maxlength="160" required value="<?= e($p['title']) ?>" id="b-title"></label>
  <label>URL slug <input name="slug" maxlength="120" value="<?= e($p['slug']) ?>" placeholder="auto from title"></label>
  <label>Short description (shown in Google and link previews, 120 to 160 characters)
    <textarea name="excerpt" rows="2" maxlength="300" id="b-desc"><?= e($p['excerpt']) ?></textarea></label>
  <label>Text (markdown: ## heading, - list, **bold**, [link](/page), ![alt](image-url))
    <textarea name="body" rows="22" id="b-body" required><?= e($p['body']) ?></textarea></label>
  <label>Tags (comma separated) <input name="tags" maxlength="160" value="<?= e($p['tags']) ?>"></label>
  <label>Cover image URL (optional, 1200x630 is ideal) <input name="cover" maxlength="255" value="<?= e($p['cover']) ?>"></label>
  <label>Status
    <select name="status"><option value="draft" <?= $p['status'] === 'draft' ? 'selected' : '' ?>>Draft (hidden)</option><option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>>Published</option></select></label>
  <label>Publish date (optional; leave blank for now, or set a future time to schedule)
    <input name="published_at" type="datetime-local" value="<?= $p['published_at'] ? e(date('Y-m-d\TH:i', (int)strtotime((string)$p['published_at']))) : '' ?>"></label>
  <div class="card" id="b-seo" style="margin:10px 0"><b>SEO check</b><ul id="b-seo-list" class="muted"></ul></div>
  <div class="btnrow"><button class="btn">Save</button></div>
</form>
<?php if ($edit): ?>
<form method="post" onsubmit="return confirm('Delete this post for good?')" class="card">
  <?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="btn ghost">Delete post</button>
</form>
<?php endif; ?>
<script src="<?= e(asset('blog-admin.js')) ?>" defer></script>
<?php else:
    $all = rows('SELECT id, slug, title, status, published_at, updated_at, views FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC'); ?>
<h1 class="pagetitle">Blog</h1>
<p><a class="btn" href="<?= e(url('blog/admin?new=1')) ?>">New post</a> <a class="btn ghost" href="<?= e(url('blog')) ?>">View blog</a> <a class="btn ghost" href="<?= e(url('admin')) ?>">Admin</a></p>
<div class="card">
  <?php if (!$all): ?><p class="muted">No posts yet.</p><?php endif; ?>
  <?php foreach ($all as $r): ?>
    <p><a href="<?= e(url('blog/admin?id=' . $r['id'])) ?>"><b><?= e($r['title']) ?></b></a><br>
      <small class="muted"><?= $r['status'] === 'published' ? ($r['published_at'] > now() ? 'Scheduled ' . e(blog_date($r['published_at'])) : 'Published ' . e(blog_date($r['published_at']))) : 'Draft' ?> · <?= (int)$r['views'] ?> views · /blog/<?= e($r['slug']) ?></small></p>
  <?php endforeach; ?>
</div>
<?php endif;
layout_footer();
