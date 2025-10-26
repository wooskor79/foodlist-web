<?php
// 파일명: www/api/share_restaurant.php (신규 파일)
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}

$owner_user_id = $_SESSION['user_id'];
$restaurant_id = $_POST['restaurant_id'] ?? 0;
$share_with_ids = $_POST['share_with_ids'] ?? [];

if (empty($restaurant_id) || !is_array($share_with_ids)) {
    echo json_encode(['success' => false, 'message' => '잘못된 데이터입니다.']);
    exit();
}

// 먼저 해당 맛집이 자신의 소유인지 확인
$stmt = $conn->prepare("SELECT id FROM restaurants WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $restaurant_id, $owner_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => '공유할 권한이 없습니다.']);
    exit();
}
$stmt->close();

// 기존 공유 기록은 삭제하고 새로 추가 (단순화를 위해)
$stmt = $conn->prepare("DELETE FROM restaurant_shares WHERE restaurant_id = ? AND owner_user_id = ?");
$stmt->bind_param("ii", $restaurant_id, $owner_user_id);
$stmt->execute();
$stmt->close();

if (empty($share_with_ids)) {
    echo json_encode(['success' => true, 'message' => '모든 공유가 해제되었습니다.']);
    exit();
}

// 새로운 공유 기록 추가
$sql = "INSERT INTO restaurant_shares (restaurant_id, owner_user_id, shared_with_user_id) VALUES ";
$params = [];
$types = "";
$values_sql = [];

foreach ($share_with_ids as $shared_id) {
    $values_sql[] = "(?, ?, ?)";
    $params[] = $restaurant_id;
    $params[] = $owner_user_id;
    $params[] = (int)$shared_id;
    $types .= "iii";
}

$sql .= implode(", ", $values_sql);
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '선택한 사용자에게 맛집을 공유했습니다.']);
} else {
    echo json_encode(['success' => false, 'message' => '공유에 실패했습니다: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>