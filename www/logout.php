<?php
require_once '../includes/common.php';
$_SESSION = [];
redirect(isset($_GET['go']) ? $_GET['go'] : 'https://dumbass.cloud');
