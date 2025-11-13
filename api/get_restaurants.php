<?php
// 파일명: www/api/get_restaurants.php (다중 사진 경로 조회로 수정)
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$is_loggedin = $user_id > 0;
$term = $_GET['term'] ?? '';

$params = [];
$types = '';

// 💡 [수정] r.rating에 대한 CASE WHEN 구문 제거. 
// DB에서 가져온 원래 값을 그대로 반환합니다.
$sql = "
    SELECT 
        r.id, r.user_id, r.name, r.address, r.jibun_address, r.detail_address, r.food_type, r.star_rating,
        r.rating, -- DB에서 가져온 원래 값을 그대로 반환
        r.image_path1, r.image_path2, r.image_path3, r.image_path4, r.image_path5,
        r.location_dong, r.location_si, r.location_gu, r.location_ri, -- 지역 정보 포함
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
    if (!empty($types)) {
        // 배열을 참조로 전달하기 위해 리스트로 만듭니다.
        $bind_params = array_merge([$types], $params);
        
        // bind_param은 참조를 필요로 하므로, 배열 요소를 참조로 변환
        $bind_refs = [];
        foreach ($bind_params as $key => $value) {
            $bind_refs[] = &$bind_params[$key];
        }

        if (!$stmt->bind_param(...$bind_refs)) {
            // 바인딩 실패 처리
        }
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