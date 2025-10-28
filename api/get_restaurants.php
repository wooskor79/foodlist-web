<?php
// 파일명: www/api/get_restaurants.php (비로그인 상태에서 공유된 맛집 표시 기능 추가)
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$is_loggedin = $user_id > 0;
$term = $_GET['term'] ?? '';

$params = [];
$types = '';

// 💡 [수정] 비로그인 시에도 모든 공유된 맛집을 조회할 수 있도록 기본 쿼리 및 WHERE 조건 구조 변경
// is_owner와 is_favorite 컬럼은 로그인 상태에서만 의미가 있으므로, 로그인 상태가 아니면 0으로 설정합니다.

$sql = "
    SELECT 
        r.*,
        u.username AS owner_name,
        CASE 
            WHEN ? > 0 AND r.user_id = ? THEN 1 
            ELSE 0 
        END AS is_owner,
        CASE 
            WHEN ? > 0 AND EXISTS (SELECT 1 FROM user_favorites uf WHERE uf.restaurant_id = r.id AND uf.user_id = ?) THEN 1 
            ELSE 0 
        END AS is_favorite
    FROM restaurants r
    JOIN users u ON r.user_id = u.id
";

// 쿼리 매개변수 바인딩을 위한 기본 설정 (user_id를 4번 사용)
// 1: is_owner 체크용, 2: is_owner 체크용, 3: is_favorite 체크용, 4: is_favorite 체크용
$params = [$user_id, $user_id, $user_id, $user_id];
$types = 'iiii';

$where_clauses = [];
if ($is_loggedin) {
    // 1. 로그인 사용자: 자신이 소유했거나 OR 자신에게 공유된 가게를 모두 조회
    $where_clauses[] = " (r.user_id = ? OR r.id IN (SELECT restaurant_id FROM restaurant_shares WHERE shared_with_user_id = ?)) ";
    array_push($params, $user_id, $user_id);
    $types .= 'ii';
} else {
    // 2. 비로그인 사용자: 'restaurant_shares' 테이블에 존재하는 모든 가게를 조회
    // 즉, 누군가에게 공유된 가게는 모두 공개됩니다.
    $where_clauses[] = " r.id IN (SELECT restaurant_id FROM restaurant_shares) ";
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
    // 💡 [수정] 바인딩할 파라미터가 4개 이상일 때만 bind_param을 호출합니다.
    // 비로그인 상태일 때는 where 조건에 user_id가 추가되지 않으므로, 쿼리문 자체에 user_id를 4번 포함시키고, 
    // where_clauses에 따라 추가적인 user_id를 바인딩합니다.
    if (!empty($types)) {
        // 배열을 참조로 전달하기 위해 리스트로 만듭니다. (PHP 8.0 이상에서는 ...$params를 사용 가능하지만, 안전을 위해)
        $bind_params = array_merge([$types], $params);
        $stmt->bind_param(...$bind_params);
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