<?php
// 파일명: www/api/check_duplicate.php (신규 파일)
require_once 'db_config.php';

header('Content-Type: application/json');

// 로그인 상태 확인
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}

$address = $_POST['address'] ?? '';
$jibun_address = $_POST['jibun_address'] ?? '';
$detail_address = $_POST['detail_address'] ?? '';

if (empty($address) && empty($jibun_address)) {
    echo json_encode(['success' => false, 'message' => '주소를 입력해주세요.']);
    exit();
}

// 도로명 또는 지번 주소가 일치하고, 상세 주소까지 완전히 일치하는 경우를 중복으로 판단
// 상세주소가 NULL인 경우도 정확히 비교하기 위해 <=> 연산자 사용
$stmt = $conn->prepare(
    "SELECT name, address, food_type, detail_address FROM restaurants 
     WHERE (address = ? OR (jibun_address = ? AND ? != '')) AND detail_address <=> ?"
);
$stmt->bind_param("ssss", $address, $jibun_address, $jibun_address, $detail_address);
$stmt->execute();
$result = $stmt->get_result();

$duplicates = [];
while ($row = $result->fetch_assoc()) {
    $duplicates[] = $row;
}

if (count($duplicates) > 0) {
    echo json_encode(['success' => true, 'duplicate' => true, 'data' => $duplicates]);
} else {
    echo json_encode(['success' => true, 'duplicate' => false]);
}

$stmt->close();
$conn->close();
?>