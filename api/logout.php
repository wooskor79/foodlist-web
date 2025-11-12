<?php
// 파일명: www/api/logout.php (전체 교체)
session_start();
session_destroy();
header("Location: ../index.php"); // 💡 상대 경로로 수정
exit();
?>