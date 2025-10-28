<?php
// 파일명: www/api/get_shared_users.php (신규 파일)
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}

$restaurant_id = $_GET['restaurant_id'] ?? 0;

if (empty($restaurant_id)) {
    echo json_encode(['success' => false, 'message' => '맛집 ID가 필요합니다.']);
    exit();
}

// 해당 맛집을 공유받은 사용자 목록을 가져옵니다.
$stmt = $conn->prepare("SELECT shared_with_user_id FROM restaurant_shares WHERE restaurant_id = ?");
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$result = $stmt->get_result();

$shared_users = [];
while ($row = $result->fetch_assoc()) {
    $shared_users[] = $row;
}

echo json_encode(['success' => true, 'data' => $shared_users]);

$stmt->close();
$conn->close();
?>