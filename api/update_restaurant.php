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

if (empty($id) || (empty($address) && empty($jibun_address))) {
    echo json_encode(['success' => false, 'message' => 'ID와 주소는 필수입니다.']);
    exit();
}

// 주소 단위 추출 로직 (save_restaurant.php에서 복사)
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
if (empty($detail_address)) {
    $detail_address = null;
}

// -----------------------------------------------------
// 1. 이미지 처리 로직
// -----------------------------------------------------
$image_path_to_update = $current_image_path;
$update_columns = "address = ?, jibun_address = ?, detail_address = ?, rating = ?, star_rating = ?, location_dong = ?, location_si = ?, location_gu = ?, location_ri = ?";
$types = "ssssdssss";
$bind_params = [
    $address, $jibun_address, $detail_address, $rating, $star_rating,
    $location_dong, $location_si, $location_gu, $location_ri
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
    
    // 파일 이동 성공 시에만 썸네일 생성 및 image_path 업데이트
    if (@move_uploaded_file($_FILES['photo']['tmp_name'], $full_path)) {
        if (create_thumbnail_for_update($full_path, $thumb_path)) {
            // 이전 파일이 있다면 삭제
            if (!empty($current_image_path) && $current_image_path !== $unique_filename) {
                @unlink($upload_dir . $current_image_path);
                @unlink($thumb_dir . $current_image_path);
            }
            $image_path_to_update = $unique_filename;
            $image_changed = true;
        } else {
            @unlink($full_path); // 썸네일 생성 실패 시 원본 삭제
            $image_path_to_update = $current_image_path; // 기존 경로 유지
            // $image_changed = false; // 이미지 업데이트 쿼리에서 제외
        }
    } else {
        // 파일 이동 실패 시 아무것도 하지 않음 (기존 이미지 경로 유지)
    }
}

// -----------------------------------------------------
// 2. 최종 SQL 쿼리 구성
// -----------------------------------------------------
if ($image_changed) {
    // 이미지 경로를 업데이트해야 하는 경우 (NULL 또는 새 파일명)
    $update_columns .= ", image_path = ?";
    $types .= "s";
    $bind_params[] = $image_path_to_update;
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

// 바인딩 파라미터 준비
// 주의: bind_param은 변수를 참조로 받으므로, $bind_params를 배열로 직접 넘길 수 없습니다.
// PHP 5.6 ~ 7.4에서 bind_param에 배열을 전달하는 안전한 방법
if (!empty($bind_params)) {
    // $bind_params 배열의 값을 참조로 변환
    $refs = [];
    foreach($bind_params as $key => $value) {
        $refs[$key] = &$bind_params[$key];
    }
    // bind_param 함수를 동적으로 호출
    if (!call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refs))) {
        $error_message = '바인딩 실패: ' . $stmt->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    }
}

if ($stmt->execute()) {
    // 💡 [수정] affected_rows가 0이더라도 성공 메시지 반환
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => '맛집 정보가 성공적으로 수정되었습니다.']);
    } else {
        // 💡 [수정] 변경된 내용이 없을 때 명확한 메시지 반환
        echo json_encode(['success' => true, 'message' => '변경 사항이 없습니다.']);
    }
} else {
    // 실패 시 상세 에러 메시지 반환
    echo json_encode(['success' => false, 'message' => '수정에 실패했습니다: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>