<?php
// 파일명: www/api/save_restaurant.php (최종 수정본 - bind_param 오류 해결)

// 💡 [디버깅 목적] 에러 표시를 일시적으로 켭니다. 성공적으로 작동하면 다시 0으로 설정해주세요.
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'db_config.php';
// session_start()는 db_config.php에 포함되어 있습니다.

$user_id = $_SESSION['user_id'] ?? 0;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// 파일 업로드 관련 디렉토리 설정
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$thumb_dir = $upload_dir . 'thumb/';

if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
if (!is_dir($thumb_dir)) { @mkdir($thumb_dir, 0777, true); }

function create_thumbnail($source_path, $dest_path, $thumb_width = 300) {
    if (!extension_loaded('gd')) { return false; }
    $source_info = getimagesize($source_path);
    if (!$source_info) return false;
    list($width, $height, $type) = $source_info;
    if ($width == 0 || $height == 0) return false;
    
    // 썸네일 생성 (300x300 정방형으로 생성)
    $thumbnail = imagecreatetruecolor($thumb_width, $thumb_width);
    if (!$thumbnail) return false;

    // 투명 배경 처리
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    
    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($source_path); break;
        case IMAGETYPE_GIF: $source = imagecreatefromgif($source_path); break;
        default: return false;
    }
    if (!$source) return false;

    // 원본 이미지에서 썸네일 크기만큼 중앙을 잘라냄
    $src_x = 0; $src_y = 0; $src_w = $width; $src_h = $height;
    $target_w = $target_h = $thumb_width;
    
    if ($width > $height) {
        $src_x = ($width - $height) / 2; $src_w = $height;
    } else if ($height > $width) {
        $src_y = ($height - $width) / 2; $src_h = $width;
    }
    
    imagecopyresampled($thumbnail, $source, 0, 0, $src_x, $src_y, $target_w, $target_h, $src_w, $src_h);
    
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

$name = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$jibun_address = trim($_POST['jibun_address'] ?? '');
$detail_address = trim($_POST['detail_address'] ?? '');
$food_type = $_POST['food_type'] ?? '';
$rating = trim($_POST['rating'] ?? '');
$star_rating = $_POST['star_rating'] ?? 0.0;
// latitude, longitude 관련 변수와 로직은 제거된 상태입니다.

if (empty($name) || (empty($address) && empty($jibun_address)) || empty($food_type)) {
    echo json_encode(['success' => false, 'message' => '가게 이름, 주소, 음식 종류는 필수 항목입니다.']);
    exit;
}

// '동' 이름 추출 로직 (이전 단계와 동일)
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
    $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('img_', true) . '.' . $file_extension;
    $full_path = $upload_dir . $unique_filename;
    $thumb_path = $thumb_dir . $unique_filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $full_path)) {
        if (create_thumbnail($full_path, $thumb_path)) {
            $image_path = $unique_filename;
        } else {
            // 썸네일 생성 실패 시
            @unlink($full_path);
            // 오류 메시지를 클라이언트에 직접 전달하지 않고, DB 저장만 시도합니다.
        }
    } else {
        // 파일 이동 실패
    }
}

// SQL 쿼리: latitude, longitude 제거 완료
$sql = "INSERT INTO restaurants (user_id, name, address, jibun_address, detail_address, food_type, rating, star_rating, image_path, location_dong, location_si, location_gu, location_ri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}

// 💡 [오류 수정] 13개 변수에 맞게 타입 정의 문자열을 "isssssdssssss"로 수정했습니다.
// (i: user_id, s: name, s: address, s: jibun_address, s: detail_address, s: food_type, s: rating, d: star_rating, s: image_path, s: location_dong, s: location_si, s: location_gu, s: location_ri)
$stmt->bind_param(
    "isssssdssssss", 
    $user_id, $name, $address, $jibun_address, $detail_address, 
    $food_type, $rating, $star_rating, $image_path, 
    $location_dong, $location_si, $location_gu, $location_ri
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '맛집이 성공적으로 추가되었습니다.']);
} else {
    $error_message = $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => '맛집 추가에 실패했습니다: ' . $error_message]);
}
$stmt->close();
$conn->close();
?>