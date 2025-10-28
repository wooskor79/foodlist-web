<?php
// 파일명: www/api/update_restaurant.php (안정적인 파일 업로드 및 바인딩 로직으로 수정)

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}

// 파일 업로드 및 썸네일 생성 함수 (save_restaurant.php에서 복사)
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$thumb_dir = $upload_dir . 'thumb/';

function create_thumbnail_for_update($source_path, $dest_path, $thumb_width = 300) {
    if (!extension_loaded('gd')) { return false; }
    $source_info = @getimagesize($source_path);
    if (!$source_info) return false;
    list($width, $height, $type) = $source_info;
    if ($width == 0 || $height == 0) return false;
    
    $thumbnail = imagecreatetruecolor($thumb_width, $thumb_width);
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

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? 0;
$address = $_POST['address'] ?? '';
$jibun_address = $_POST['jibun_address'] ?? '';
$detail_address = $_POST['detail_address'] ?? null;
$rating = $_POST['rating'] ?? '';
$star_rating = $_POST['star_rating'] ?? 0.0;
$remove_photo = $_POST['remove_photo'] ?? '0'; // 1이면 사진 제거 요청
$current_image_path = $_POST['current_image_path'] ?? null; // 현재 DB에 저장된 파일 이름

if (empty($id) || empty($address)) {
    echo json_encode(['success' => false, 'message' => 'ID와 주소는 필수입니다.']);
    exit();
}
if (empty($detail_address)) {
    $detail_address = null;
}

// -----------------------------------------------------
// 1. 이미지 처리 로직
// -----------------------------------------------------
$image_path_to_update = $current_image_path;
$update_columns = "address = ?, jibun_address = ?, detail_address = ?, rating = ?, star_rating = ?";
$types = "ssssd";
$bind_params = [
    $address, $jibun_address, $detail_address, $rating, $star_rating
];
$image_changed = false;

// 1-1. 기존 이미지 삭제 요청 처리
if ($remove_photo === '1' && !empty($current_image_path)) {
    @unlink($upload_dir . $current_image_path);
    @unlink($thumb_dir . $current_image_path);
    $image_path_to_update = null;
    $image_changed = true;
}

// 1-2. 새 이미지 업로드 및 처리
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    // 새 파일이 업로드되면 기존 삭제 플래그 무시하고 (이미 위에서 삭제되었거나) 새 파일로 대체
    $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('img_', true) . '.' . $file_extension;
    $full_path = $upload_dir . $unique_filename;
    $thumb_path = $thumb_dir . $unique_filename;

    if (@move_uploaded_file($_FILES['photo']['tmp_name'], $full_path)) {
        if (create_thumbnail_for_update($full_path, $thumb_path)) {
            $image_path_to_update = $unique_filename;
            $image_changed = true;
        } else {
            @unlink($full_path); // 썸네일 생성 실패 시 원본 삭제
            $image_path_to_update = null;
            $image_changed = true; // DB에 NULL로 업데이트하기 위해 변경 플래그 유지
        }
    } else {
        // 파일 이동 실패 시 아무것도 하지 않음 (기존 이미지 경로 유지 시도)
    }
}

// -----------------------------------------------------
// 2. 최종 SQL 쿼리 구성
// -----------------------------------------------------
if ($image_changed) {
    // 이미지 경로를 업데이트해야 하는 경우
    $update_columns .= ", image_path = ?";
    $types .= "s";
    $bind_params[] = $image_path_to_update; // 새 경로 또는 NULL
}

// WHERE 조건에 사용될 파라미터 추가
$types .= "ii";
$bind_params[] = $id;
$bind_params[] = $user_id;

// SQL 쿼리 구성
$sql = "UPDATE restaurants SET $update_columns WHERE id = ? AND user_id = ?";

// -----------------------------------------------------
// 3. 쿼리 실행
// -----------------------------------------------------
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}

// 바인딩 파라미터 준비 (참조 필요 없음: PHP 8.0 이상 환경을 가정)
if (!$stmt->bind_param($types, ...$bind_params)) {
    $error_message = '바인딩 실패: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || $stmt->insert_id > 0) {
        echo json_encode(['success' => true, 'message' => '맛집 정보가 성공적으로 수정되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '수정할 권한이 없거나 변경된 내용이 없습니다.']);
    }
} else {
    // 실패 시 상세 에러 메시지 반환
    echo json_encode(['success' => false, 'message' => '수정에 실패했습니다: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>