<?php
// 파일명: www/api/get_users.php (신규 파일)
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// 자기 자신을 제외한 모든 사용자 목록을 가져옴
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode(['success' => true, 'data' => $users]);

$stmt->close();
$conn->close();
?>