<?php
session_start();
session_unset();
session_destroy();

// Include config for BASE_URL
include __DIR__ . '/../../config/config.php';

header("Location: " . BASE_URL . "/views/login.php?message=logged_out");
exit();
