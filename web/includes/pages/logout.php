<?php
declare(strict_types=1);

if (is_post()) {
    csrf_check();
    logout_user();
}
header('Location: ' . url(''));
exit;
