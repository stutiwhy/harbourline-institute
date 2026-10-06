<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    if (!empty($_SESSION['user_id'])) log_event((int)$_SESSION['user_id'], '', 'logout');
    destroy_session();
    flash('You have been signed out.');
}
header('Location: login.php');
exit;
