<?php
// 파일명: www/api/get_restaurants.php (이 코드로 전체 교체)
require_once 'db_config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$is_loggedin = $user_id > 0;

$term = $_GET['term'] ?? '';
$params = [];
$types = '';

if ($is_loggedin) {
    // 💡 [수정] 로그인한 사용자의 경우, 기존 로직을 유지합니다. (자신이 등록한 맛집 + 자신에게 공유된 맛집)
    $base_sql = "
        SELECT 
            combined.id, combined.user_id, combined.name, combined.address, combined.jibun_address, combined.detail_address, 
            combined.food_type, combined.rating, combined.star_rating, combined.location_dong, combined.location_si, 
            combined.location_gu, combined.location_ri, combined.is_owner, combined.owner_name,
            CASE WHEN uf.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
        FROM (
            (SELECT r.*, 1 AS is_owner, u.username AS owner_name
             FROM restaurants r
             JOIN users u ON r.user_id = u.id
             WHERE r.user_id = ?)
            UNION
            (SELECT r.*, 0 AS is_owner, u.username AS owner_name
             FROM restaurant_shares rs
             JOIN restaurants r ON rs.restaurant_id = r.id
             JOIN users u ON rs.owner_user_id = u.id
             WHERE rs.shared_with_user_id = ?)
        ) AS combined
        LEFT JOIN user_favorites uf ON combined.id = uf.restaurant_id AND uf.user_id = ?
    ";
    $params = [$user_id, $user_id, $user_id];
    $types = 'iii';
} else {
    // 💡 [추가] 로그인하지 않은 사용자의 경우, 공유된 모든 맛집 목록을 보여줍니다.
    $base_sql = "
        SELECT 
            r.id, r.user_id, r.name, r.address, r.jibun_address, r.detail_address, 
            r.food_type, r.rating, r.star_rating, r.location_dong, r.location_si, 
            r.location_gu, r.location_ri, 0 AS is_owner, u.username AS owner_name,
            0 AS is_favorite
        FROM restaurant_shares rs
        JOIN restaurants r ON rs.restaurant_id = r.id
        JOIN users u ON rs.owner_user_id = u.id
        GROUP BY r.id 
    ";
}


$where_clause = "";
if (!empty($term) && $term !== '모두' && mb_strlen($term) >= 2) {
    $where_clause = " WHERE full_text LIKE ?";
    $searchTerm = "%" . $term . "%";
    $params[] = $searchTerm;
    $types .= 's';
}

$final_sql = "SELECT * FROM ({$base_sql}) AS final_results
              LEFT JOIN (
                  SELECT id, CONCAT_WS(' ', name, address, jibun_address, detail_address, location_si, location_gu, location_dong, location_ri) AS full_text
                  FROM restaurants
              ) AS search_text ON final_results.id = search_text.id
              {$where_clause}
              ORDER BY name";

$stmt = $conn->prepare($final_sql);

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