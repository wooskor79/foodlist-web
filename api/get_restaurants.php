<?php
// 파일명: www/api/get_restaurants.php (최종 수정본)
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$is_loggedin = $user_id > 0;
$term = $_GET['term'] ?? '';

$params = [];
$types = '';

// 💡 [수정] 중복 문제가 발생하지 않는 더 안전하고 명확한 쿼리로 변경
$sql = "
    SELECT 
        r.*,
        u.username AS owner_name,
        CASE WHEN r.user_id = ? THEN 1 ELSE 0 END AS is_owner,
        EXISTS (SELECT 1 FROM user_favorites uf WHERE uf.restaurant_id = r.id AND uf.user_id = ?) AS is_favorite
    FROM restaurants r
    JOIN users u ON r.user_id = u.id
";

$where_clauses = [];
if ($is_loggedin) {
    // 로그인 사용자는 (자신이 소유했거나 OR 자신에게 공유된) 가게를 볼 수 있음
    $where_clauses[] = " (r.user_id = ? OR r.id IN (SELECT restaurant_id FROM restaurant_shares WHERE shared_with_user_id = ?)) ";
    $params = [$user_id, $user_id, $user_id, $user_id];
    $types = 'iiii';
} else {
    // 비로그인 사용자는 아무것도 보이지 않음
    $where_clauses[] = " 1=0 ";
}

if (!empty($term) && $term !== '모두' && mb_strlen($term) >= 2) {
    $where_clauses[] = " (r.name LIKE ? OR r.address LIKE ? OR r.jibun_address LIKE ? OR r.location_dong LIKE ?) ";
    $term_param = "%" . $term . "%";
    array_push($params, $term_param, $term_param, $term_param, $term_param);
    $types .= 'ssss';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY r.name ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $restaurants = [];
    while ($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비에 실패했습니다: ' . $conn->error]);
    exit();
}
$conn->close();

echo json_encode([
    'success' => true, 
    'data' => $restaurants, 
    'loggedin' => $is_loggedin
]);
?>