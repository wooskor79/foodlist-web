<?php
// 파일명: www/api/toggle_favorite.php (이 코드로 전체 교체)
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
$status = $_POST['status'] ?? 0;

if (empty($restaurant_id)) {
    echo json_encode(['success' => false, 'message' => 'ID가 필요합니다.']);
    exit();
}

if ($status == 1) { // 즐겨찾기 추가
    $stmt = $conn->prepare("INSERT INTO user_favorites (user_id, restaurant_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $restaurant_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '즐겨찾기에 추가되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '즐겨찾기 추가에 실패했습니다.']);
    }
} else { // 즐겨찾기 제거
    $stmt = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND restaurant_id = ?");
    $stmt->bind_param("ii", $user_id, $restaurant_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '즐겨찾기에서 해제되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '즐겨찾기 해제에 실패했습니다.']);
    }
}

$stmt->close();
$conn->close();
?>