<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

logoutAdmin();

header(
    'Location: login.php'
);

exit;