<?php
session_start();
session_unset();
session_destroy();


include __DIR__ . '/../../config/config.php';

header("Location: " . BASE_URL . "/index.php");
exit();
