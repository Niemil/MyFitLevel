<?php
declare(strict_types=1);

/* Photo uploads: re-encoded with GD (strips EXIF/GPS), stored outside the web root, served through /img/TOKEN. */

const IMG_MAX_PX = 1600;
const IMG_THUMB_PX = 560;

function img_dir(): string
{
    $d = APP_ROOT . '/config/img';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

function img_path(string $token, bool $thumb = false): string
{
    return img_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $token) . ($thumb ? '_t' : '') . '.jpg';
}

/** Loads an uploaded image into a GD resource, honouring EXIF rotation. Returns null if it is not a usable image. */
function img_load(string $tmp): ?GdImage
{
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    if ($info[0] * $info[1] > 50_000_000 || $info[0] < 8 || $info[1] < 8) {
        return null;
    }
    $im = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
        default => false,
    };
    if (!$im) {
        return null;
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $o = (int)($ex['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) {
            $r = imagerotate($im, $rot, 0);
            if ($r) {
                $im = $r;
            }
        }
    }
    return $im;
}

/** Scales (or square-crops) and writes a JPEG. */
function img_write(GdImage $src, string $path, int $max, bool $square = false, int $q = 82): bool
{
    $w = imagesx($src);
    $h = imagesy($src);
    if ($square) {
        $s = min($w, $h);
        $crop = imagecrop($src, ['x' => intdiv($w - $s, 2), 'y' => intdiv($h - $s, 2), 'width' => $s, 'height' => $s]);
        if (!$crop) {
            return false;
        }
        $src = $crop;
        $w = $h = $s;
    }
    $k = min(1.0, $max / max($w, $h));
    $nw = max(1, (int)round($w * $k));
    $nh = max(1, (int)round($h * $k));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // PNG transparency -> white
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return imagejpeg($dst, $path, $q);
}

/**
 * Stores one uploaded file. $kind: 'post' | 'avatar' | 'gallery'.
 * Returns ['ok'=>true,'id'=>..,'token'=>..] or ['ok'=>false,'error'=>..].
 */
function image_store(array $file, int $uid, string $kind, ?int $postId = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        $e = (int)($file['error'] ?? 0);
        return ['ok' => false, 'error' => in_array($e, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'That photo is too large.' : 'Upload failed.'];
    }
    if ($file['size'] > 15_000_000) {
        return ['ok' => false, 'error' => 'That photo is too large (max 15 MB).'];
    }
    if (!rate_ok('img:' . $uid, 40, 86400)) {
        return ['ok' => false, 'error' => 'Daily photo limit reached. Try again tomorrow.'];
    }
    $im = img_load($file['tmp_name']);
    if (!$im) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG or WebP photo.'];
    }
    $token = bin2hex(random_bytes(12));
    $sq = $kind === 'avatar';
    if (!img_write($im, img_path($token), $sq ? 640 : IMG_MAX_PX, $sq) || !img_write($im, img_path($token, true), $sq ? 160 : IMG_THUMB_PX, $sq, 78)) {
        @unlink(img_path($token));
        return ['ok' => false, 'error' => 'Could not process that photo.'];
    }
    q('INSERT INTO images (user_id, token, kind, post_id, bytes, created_at) VALUES (?,?,?,?,?,?)',
      [$uid, $token, $kind, $postId, (int)filesize(img_path($token)), now()]);
    rate_hit('img:' . $uid);
    return ['ok' => true, 'id' => (int)db()->lastInsertId(), 'token' => $token];
}

function image_delete(array $img): void
{
    q('DELETE FROM images WHERE id = ?', [(int)$img['id']]);
    @unlink(img_path($img['token']));
    @unlink(img_path($img['token'], true));
}

/** Normalises a multi-file input ($_FILES['photos']) into a list of single file arrays. */
function files_list(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) {
        return $f && ($f['error'] ?? 4) !== UPLOAD_ERR_NO_FILE ? [$f] : [];
    }
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function img_url(string $token, bool $thumb = false): string
{
    return url('img/' . $token . ($thumb ? '/t' : ''));
}

/** Photos for a set of posts: post_id => [rows]. */
function post_images(array $postIds): array
{
    if (!$postIds) {
        return [];
    }
    $out = [];
    foreach (rows('SELECT id, post_id, token FROM images WHERE kind = \'post\' AND post_id IN (' . in_clause($postIds) . ') ORDER BY id', $postIds) as $r) {
        $out[(int)$r['post_id']][] = $r;
    }
    return $out;
}

function photo_grid(array $imgs): string
{
    if (!$imgs) {
        return '';
    }
    $n = min(4, count($imgs));
    $h = '<div class="pgrid n' . $n . '">';
    foreach (array_slice($imgs, 0, 4) as $im) {
        $h .= '<a class="pimg" href="' . e(img_url($im['token'])) . '" data-lightbox><img loading="lazy" alt="Photo" src="' . e(img_url($im['token'], true)) . '"></a>';
    }
    return $h . '</div>';
}

/** Avatar for a member row (uses avatar_token when present, else a cached lookup by username). */
function uavatar(array $r, int $size = 40): string
{
    static $cache = [];
    $name = display_name($r);
    $tok = $r['avatar_token'] ?? null;
    if (!array_key_exists('avatar_token', $r) && !empty($r['username'])) {
        $k = $r['username'];
        if (!array_key_exists($k, $cache)) {
            $cache[$k] = val('SELECT avatar_token FROM users WHERE username = ?', [$k]);
        }
        $tok = $cache[$k];
    }
    if ($tok) {
        return '<img class="avatar" alt="' . e($name) . '" width="' . $size . '" height="' . $size . '" style="width:' . $size . 'px;height:' . $size . 'px" src="' . e(img_url($tok, true)) . '">';
    }
    return avatar($name, $size);
}
