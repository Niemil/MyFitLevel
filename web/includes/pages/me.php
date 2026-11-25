<?php
declare(strict_types=1);

$u = require_login();
redirect('u/' . $u['username']);
