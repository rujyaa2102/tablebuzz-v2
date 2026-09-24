<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

logoutUser();

header(
    'Location: login.php'
);

exit;