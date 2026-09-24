<?php

declare(strict_types=1);

$slug = 'cafe-goodluck';
header('Location: public/cafe.php?slug=' . rawurlencode($slug), true, 302);
exit;
