<?php
// 파일명: www/api/unshare_restaurant.php (신규 파일)
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$restaurant_id = $_POST['id'] ?? 0;

if (empty($restaurant_id)) {
    echo json_encode(['success' => false, 'message' => 'ID가 없습니다.']);
    exit();
}

// restaurant_shares 테이블에서 현재 사용자와 맛집 ID가 일치하는 공유 기록만 삭제
$stmt = $conn->prepare("DELETE FROM restaurant_shares WHERE restaurant_id = ? AND shared_with_user_id = ?");
$stmt->bind_param("ii", $restaurant_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => '내 목록에서 삭제되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '삭제할 수 없는 항목입니다.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '삭제에 실패했습니다: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>