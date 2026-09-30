<?php
require __DIR__.'/bootstrap.php';
db()->query('SELECT 1 FROM users LIMIT 1');
header('Content-Type: text/plain');
echo 'OK';
