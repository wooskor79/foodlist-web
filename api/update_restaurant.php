<?php
// 파일명: www/api/update_restaurant.php (이 코드로 전체 교체)
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
$id = $_POST['id'] ?? 0;
$address = $_POST['address'] ?? '';
$jibun_address = $_POST['jibun_address'] ?? '';
$detail_address = $_POST['detail_address'] ?? null;
$rating = $_POST['rating'] ?? '';
$star_rating = $_POST['star_rating'] ?? 0.0;

if (empty($id) || empty($address)) {
    echo json_encode(['success' => false, 'message' => 'ID와 주소는 필수입니다.']);
    exit();
}
if (empty($detail_address)) {
    $detail_address = null;
}

// 💡 [수정] user_id가 일치하는지 확인하는 WHERE 조건 추가
$stmt = $conn->prepare("UPDATE restaurants SET address = ?, jibun_address = ?, detail_address = ?, rating = ?, star_rating = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("ssssdii", $address, $jibun_address, $detail_address, $rating, $star_rating, $id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => '수정되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '수정할 권한이 없거나 변경된 내용이 없습니다.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '수정에 실패했습니다: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>