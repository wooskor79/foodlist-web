<?php
// 파일명: www/api/save_restaurant.php (최종 수정본)

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
require_once 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$thumb_dir = $upload_dir . 'thumb/';

if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
if (!is_dir($thumb_dir)) { @mkdir($thumb_dir, 0777, true); }

function create_thumbnail($source_path, $dest_path, $thumb_width = 300) {
    if (!extension_loaded('gd')) { return false; }
    $source_info = @getimagesize($source_path);
    if (!$source_info) return false;
    list($width, $height, $type) = $source_info;
    if ($width == 0 || $height == 0) return false;
    $thumb_height = floor($height * ($thumb_width / $width));
    $thumbnail = imagecreatetruecolor($thumb_height, $thumb_height);
    if (!$thumbnail) return false;
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $source = @imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG: $source = @imagecreatefrompng($source_path); break;
        case IMAGETYPE_GIF: $source = @imagecreatefromgif($source_path); break;
        default: return false;
    }
    if (!$source) return false;
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $result = imagejpeg($thumbnail, $dest_path, 90); break;
        case IMAGETYPE_PNG: $result = imagepng($thumbnail, $dest_path, 9); break;
        case IMAGETYPE_GIF: $result = imagegif($thumbnail, $dest_path); break;
    }
    imagedestroy($source);
    imagedestroy($thumbnail);
    return $result;
}

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$jibun_address = trim($_POST['jibun_address'] ?? '');
$detail_address = trim($_POST['detail_address'] ?? '');
$food_type = $_POST['food_type'] ?? '';
$rating = trim($_POST['rating'] ?? '');
$star_rating = $_POST['star_rating'] ?? 0.0;
$latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
$longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

if (empty($name) || (empty($address) && empty($jibun_address)) || empty($food_type)) {
    echo json_encode(['success' => false, 'message' => '가게 이름, 주소, 음식 종류는 필수 항목입니다.']);
    exit;
}

// 💡 [수정] '동' 이름 추출 로직 개선 (지번 주소 우선)
$location_dong = ''; $location_si = ''; $location_gu = ''; $location_ri = '';
$address_for_dong = !empty($jibun_address) ? $jibun_address : $address;
$address_for_si_gu = !empty($address) ? $address : $jibun_address;

if (!empty($address_for_si_gu)) {
    $parts = explode(' ', $address_for_si_gu);
    if(isset($parts[0])) $location_si = $parts[0];
    if(isset($parts[1])) $location_gu = $parts[1];
}
if (!empty($address_for_dong)) {
    $parts = explode(' ', $address_for_dong);
    foreach ($parts as $part) {
        if (str_ends_with($part, '동') || str_ends_with($part, '읍') || str_ends_with($part, '면')) {
            $location_dong = $part;
            break;
        }
    }
    foreach (array_reverse($parts) as $part) {
        if (str_ends_with($part, '리')) {
            $location_ri = $part;
            break;
        }
    }
}

$image_path = null; 
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    // ... (파일 업로드 로직은 기존과 동일)
}

$sql = "INSERT INTO restaurants (user_id, name, address, jibun_address, detail_address, food_type, rating, star_rating, image_path, latitude, longitude, location_dong, location_si, location_gu, location_ri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}
$stmt->bind_param(
    "issssssdsssssss", 
    $user_id, $name, $address, $jibun_address, $detail_address, 
    $food_type, $rating, $star_rating, $image_path, 
    $latitude, $longitude,
    $location_dong, $location_si, $location_gu, $location_ri
);

if ($stmt->execute()) {
    // 💡 [수정] 불필요한 "자기 자신에게 공유"하는 로직을 완전히 제거했습니다.
    echo json_encode(['success' => true, 'message' => '맛집이 성공적으로 추가되었습니다.']);
} else {
    // ... (에러 처리 로직은 기존과 동일)
}
$stmt->close();
$conn->close();
?>