<?php
// 파일명: www/api/search_dong.php (이 코드로 전체 교체)
require_once 'db_config.php';

$term = $_GET['term'] ?? '';
if (empty($term)) {
    echo json_encode([]);
    exit();
}

if ($term === '모두') {
    echo json_encode(['모두']);
    exit();
}

$searchTerm = "%" . $term . "%";
// [수정] 검색 범위를 모든 주소 단위로 확장
$stmt = $conn->prepare("SELECT DISTINCT location_dong FROM restaurants WHERE CONCAT_WS(' ', location_si, location_gu, location_dong, location_ri) LIKE ? ORDER BY location_dong LIMIT 10");
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();
$locations = [];
while($row = $result->fetch_assoc()) {
    $locations[] = $row['location_dong'];
}
$stmt->close();
$conn->close();
echo json_encode($locations);
?>