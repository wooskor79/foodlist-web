<?php
// 파일명: www/api/delete_restaurant.php (전체 교체)
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

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID가 없습니다.']);
    exit();
}

$conn->begin_transaction();

try {
    // 1. 맛집 소유권 확인 및 삭제
    $stmt1 = $conn->prepare("DELETE FROM restaurants WHERE id = ? AND user_id = ?");
    $stmt1->bind_param("ii", $id, $user_id);
    $stmt1->execute();
    $affected_rows = $stmt1->affected_rows;
    $stmt1->close();

    if ($affected_rows > 0) {
        // 2. 소유권이 확인되면 공유 기록도 삭제
        $stmt2 = $conn->prepare("DELETE FROM restaurant_shares WHERE restaurant_id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => '삭제되었습니다.']);
    } else {
        throw new Exception('삭제할 권한이 없습니다.');
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>