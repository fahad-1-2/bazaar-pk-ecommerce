<?php
session_start();
session_unset();
session_destroy();
header("Location: /bazaar_pk/index.php");
exit();
?>