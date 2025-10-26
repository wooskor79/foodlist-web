<?php
// 파일명: www/api/save_restaurant.php (이 코드로 전체 교체)
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}
// 💡 [수정] user_id가 세션에 있는지 확인
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}
$user_id = $_SESSION['user_id'];

$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';
$jibun_address = $_POST['jibun_address'] ?? '';
$detail_address = $_POST['detail_address'] ?? null;
$food_type = $_POST['food_type'] ?? '';
$rating = $_POST['rating'] ?? '';
$star_rating = $_POST['star_rating'] ?? 0.0;
$location_dong = $_POST['location_dong'] ?? '';
$location_si = $_POST['location_si'] ?? '';
$location_gu = $_POST['location_gu'] ?? '';
$location_ri = $_POST['location_ri'] ?? '';

if (empty($name) || empty($address) || empty($location_dong)) {
    echo json_encode(['success' => false, 'message' => '필수 항목(가게이름, 주소, 동)을 모두 입력해주세요. 주소 검색을 먼저 실행해야 합니다.']);
    exit();
}

if (empty($detail_address)) {
    $detail_address = null;
}

// 💡 [수정] user_id 컬럼을 INSERT 쿼리에 추가
$stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, jibun_address, detail_address, food_type, rating, star_rating, location_dong, location_si, location_gu, location_ri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
// 💡 [수정] 파라미터 바인딩에 user_id 추가 (i 타입)
$stmt->bind_param("isssssdsssss", $user_id, $name, $address, $jibun_address, $detail_address, $food_type, $rating, $star_rating, $location_dong, $location_si, $location_gu, $location_ri);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => "맛집 정보가 '{$location_dong}'으로 성공적으로 저장되었습니다."]);
} else {
    echo json_encode(['success' => false, 'message' => '저장에 실패했습니다: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>