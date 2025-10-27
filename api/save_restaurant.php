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

// 💡 [수정] 이미지를 웹사이트 폴더 내에 저장하도록 경로를 자동으로 설정합니다.
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$thumb_dir = $upload_dir . 'thumb/';

if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
if (!is_dir($thumb_dir)) { mkdir($thumb_dir, 0777, true); }

function create_thumbnail($source_path, $dest_path, $thumb_width = 300) {
    if (!extension_loaded('gd')) { return false; }
    $source_info = @getimagesize($source_path);
    if (!$source_info) return false;
    list($width, $height, $type) = $source_info;
    if ($width == 0 || $height == 0) return false;
    $thumb_height = floor($height * ($thumb_width / $width));
    $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
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

if (empty($name) || (empty($address) && empty($jibun_address)) || empty($food_type)) {
    echo json_encode(['success' => false, 'message' => '가게 이름, 주소, 음식 종류는 필수 항목입니다.']);
    exit;
}

$location_dong = ''; $location_si = ''; $location_gu = ''; $location_ri = '';
$full_address_for_parse = !empty($address) ? $address : $jibun_address;
if (!empty($full_address_for_parse)) {
    preg_match('/(\S+시|\S+도)\s(\S+시|\S+군|\S+구)/', $full_address_for_parse, $matches_si_gu);
    $location_si = $matches_si_gu[1] ?? '';
    $location_gu = $matches_si_gu[2] ?? '';
    $addr_parts = explode(' ', $full_address_for_parse);
    foreach ($addr_parts as $part) {
        if (preg_match('/(동|읍|면)$/', $part)) { $location_dong = $part; break; }
    }
    foreach (array_reverse($addr_parts) as $part) {
        if (preg_match('/(리)$/', $part)) { $location_ri = $part; break; }
    }
}

$image_path = null; 

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $photo = $_FILES['photo'];
    $file_extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('img_', true) . '.' . $file_extension;
    $original_path = $upload_dir . $new_filename;
    $thumb_path = $thumb_dir . $new_filename;

    if (!move_uploaded_file($photo['tmp_name'], $original_path)) {
        echo json_encode(['success' => false, 'message' => '파일을 지정된 디렉토리로 옮기는 데 실패했습니다. 폴더 권한을 확인하세요.']);
        exit;
    }
    if (!create_thumbnail($original_path, $thumb_path)) {
        unlink($original_path);
        echo json_encode(['success' => false, 'message' => '썸네일 생성에 실패했습니다. GD 라이브러리가 설치되어 있는지 확인하세요.']);
        exit;
    }
    $image_path = $new_filename;
}

$sql = "INSERT INTO restaurants (user_id, name, address, jibun_address, detail_address, food_type, rating, star_rating, image_path, location_dong, location_si, location_gu, location_ri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}

$stmt->bind_param(
    "issssssdsssss", 
    $user_id, $name, $address, $jibun_address, $detail_address, 
    $food_type, $rating, $star_rating, $image_path, 
    $location_dong, $location_si, $location_gu, $location_ri
);

if ($stmt->execute()) {
    // 💡 [수정] 불필요한 "자기 자신에게 공유"하는 로직을 완전히 제거했습니다.
    echo json_encode(['success' => true, 'message' => '맛집이 성공적으로 추가되었습니다.']);
} else {
    if ($image_path) {
        if (file_exists($upload_dir . $image_path)) unlink($upload_dir . $image_path);
        if (file_exists($thumb_dir . $image_path)) unlink($thumb_dir . $image_path);
    }
    echo json_encode(['success' => false, 'message' => '데이터베이스 저장 실패: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>