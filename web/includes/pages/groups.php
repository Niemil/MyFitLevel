<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$error = '';

if (is_post()) {
    csrf_check();
    $name = clean_text((string)($_POST['name'] ?? ''), 60);
    $desc = clean_text((string)($_POST['description'] ?? ''), 400);
    $slug = slugify($name);
    if (mb_strlen($name) < 3 || $slug === '') {
        $error = 'Give the group a name (at least 3 characters).';
    } elseif (!rate_ok('gcreate:' . $uid, 3, 86400)) {
        $error = 'You can create up to 3 groups per day.';
    } else {
        $base = $slug;
        $i = 2;
        while (row('SELECT id FROM groups_ WHERE slug = ?', [$slug])) {
            $slug = substr($base, 0, 55) . '-' . $i++;
        }
        q('INSERT INTO groups_ (slug, name, description, owner_id, created_at) VALUES (?,?,?,?,?)', [$slug, $name, $desc ?: null, $uid, now()]);
        $gid = (int)db()->lastInsertId();
        q('INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?,?,?)', [$gid, $uid, now()]);
        rate_hit('gcreate:' . $uid);
        check_awards($uid);
        redirect('g/' . $slug);
    }
}

$list = rows('SELECT g.*, (SELECT COUNT(*) FROM group_members m WHERE m.group_id = g.id) AS members,
                     (SELECT COUNT(*) FROM group_members m2 WHERE m2.group_id = g.id AND m2.user_id = ?) AS joined
              FROM groups_ g ORDER BY members DESC, g.id DESC LIMIT 60', [$uid]);

layout_header('Groups', 'groups');
?>
<h1 class="pagetitle">Groups</h1>
<div class="cols">
<section class="main">
  <?php if (!$list): ?><div class="card center muted">No groups yet. Start the first one!</div><?php endif; ?>
  <?php foreach ($list as $g): ?>
    <a class="card grouprow" href="<?= e(url('g/' . $g['slug'])) ?>">
      <div class="grow"><h4><?= e($g['name']) ?> <?= $g['joined'] ? '<span class="pr">joined</span>' : '' ?></h4>
      <p class="muted small"><?= e($g['description'] ?? '') ?></p></div>
      <div class="muted small"><?= pluralize((int)$g['members'], 'member') ?></div>
    </a>
  <?php endforeach; ?>
</section>
<aside class="side">
  <div class="card">
    <h4>Start a group</h4>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Name <input name="name" maxlength="60" required></label>
      <label>Description <textarea name="description" rows="3" maxlength="400"></textarea></label>
      <button class="btn wide" type="submit">Create group</button>
    </form>
  </div>
</aside>
</div>
<?php layout_footer('groups'); ?>
