<?php
// 파일명: www/api/delete_restaurant.php (전체 교체 - 이미지 파일 삭제 로직 추가)
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

$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$thumb_dir = $upload_dir . 'thumb/';

$conn->begin_transaction();

try {
    // 1. 삭제할 파일 경로 조회
    $stmt_paths = $conn->prepare("SELECT image_path1, image_path2, image_path3, image_path4, image_path5 FROM restaurants WHERE id = ? AND user_id = ?");
    $stmt_paths->bind_param("ii", $id, $user_id);
    $stmt_paths->execute();
    $result_paths = $stmt_paths->get_result();
    
    if ($result_paths->num_rows === 0) {
        throw new Exception('삭제할 권한이 없거나 가게를 찾을 수 없습니다.');
    }
    
    $paths_row = $result_paths->fetch_assoc();
    $paths_to_delete = array_filter(array_values($paths_row)); // null 또는 빈 값 제거
    $stmt_paths->close();

    // 2. DB 레코드 삭제 (restaurant_shares와 user_favorites는 CASCADE로 자동 삭제됨)
    $stmt_delete = $conn->prepare("DELETE FROM restaurants WHERE id = ? AND user_id = ?");
    $stmt_delete->bind_param("ii", $id, $user_id);
    $stmt_delete->execute();
    $affected_rows = $stmt_delete->affected_rows;
    $stmt_delete->close();
    
    if ($affected_rows > 0) {
        // 3. 파일 시스템에서 이미지 파일 삭제
        foreach ($paths_to_delete as $filename) {
            @unlink($upload_dir . $filename);
            @unlink($thumb_dir . $filename);
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => '맛집이 성공적으로 삭제되었습니다.']);
    } else {
        // DB 삭제는 실패했으나 권한 오류는 아니었던 경우 (영향 받은 행이 0)
        $conn->rollback();
        throw new Exception('삭제 작업에 실패했습니다.');
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>