<?php
declare(strict_types=1);

/* Public, cacheable picture of Fito in a chosen outfit for the front page demo. Only whitelisted keys, no database use. */
$sets = array_merge(array_keys(game_set_defs()), array_keys(fa_look_sets()));
$o = (string)($_GET['o'] ?? '');
$extras = fa_look_extras();
$keys = [];
if (in_array($o, $sets, true)) {
    foreach (['w', 'h', 'a', 'g', 'b'] as $sx) { $keys[] = 'look_' . $o . '_' . $sx; }
}
foreach (['w' => 'wings', 'a' => 'aura'] as $param => $slot) {
    $k = 'look_' . $slot . '_' . (string)($_GET[$param] ?? '');
    if (isset($extras[$k])) { $keys[] = $k; }
}
$look = fito_try_on(['species' => 'wolf', 'eye' => 'ice', 'body' => (($_GET['s'] ?? '') === 'f' ? 'f' : 'm'), 'picks' => 0, 'gear' => []], $keys);
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo preg_replace('/<svg/', '<svg xmlns="http://www.w3.org/2000/svg"', fito_dressed($look, 300), 1);
