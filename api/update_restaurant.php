<?php
// 파일명: www/api/update_restaurant.php

// 1. [중요] 모든 출력(에러 메시지 포함)을 버퍼에 담아둡니다. 
// 나중에 JSON만 깨끗하게 보내기 위함입니다.
ob_start();

// 2. 에러 화면 출력 끄기 (JSON 깨짐 방지)
ini_set('display_errors', 0);
error_reporting(E_ALL); // 로그에는 남기되 화면엔 출력 안 함

header('Content-Type: application/json');
require_once 'db_config.php';

// 응답을 보내고 종료하는 헬퍼 함수
function send_response($success, $message, $data = null) {
    // 버퍼에 쌓인 잡다한 텍스트(Warning 등)를 싹 지웁니다.
    ob_end_clean(); 
    
    $response = ['success' => $success, 'message' => $message];
    if ($data) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    send_response(false, '로그인이 필요합니다.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, '잘못된 요청입니다.');
}

// 3. 디렉토리 설정
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$thumb_dir = $upload_dir . 'thumb/';

// 폴더가 없으면 생성 (에러 억제 @ 사용)
if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
if (!is_dir($thumb_dir)) { @mkdir($thumb_dir, 0777, true); }

// 4. 썸네일 함수
function create_thumbnail_for_update($source_path, $dest_path, $thumb_width = 300) {
    if (!extension_loaded('gd')) { return false; }
    $source_info = @getimagesize($source_path);
    if (!$source_info) return false;
    list($width, $height, $type) = $source_info;
    if ($width == 0 || $height == 0) return false;
    
    $thumbnail = imagecreatetruecolor($thumb_width, $thumb_width);
    if (!$thumbnail) return false;

    // 투명도 처리
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    
    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $source = @imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG: $source = @imagecreatefrompng($source_path); break;
        case IMAGETYPE_GIF: $source = @imagecreatefromgif($source_path); break;
        default: return false;
    }
    if (!$source) {
        imagedestroy($thumbnail);
        return false;
    }

    // 중앙 크롭 계산
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

// 5. 파라미터 수신
$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? 0;
$address = trim($_POST['address'] ?? '');
$jibun_address = trim($_POST['jibun_address'] ?? '');
$detail_address = trim($_POST['detail_address'] ?? '');

$raw_rating = $_POST['rating'] ?? '';
$rating = trim($raw_rating);
$rating = (strlen($rating) === 0) ? null : htmlspecialchars($rating, ENT_QUOTES, 'UTF-8');

$star_rating = $_POST['star_rating'] ?? 0.0;

// 이미지 관리 배열
$remove_photos = [
    $_POST['remove_photo1'] ?? '0',
    $_POST['remove_photo2'] ?? '0',
    $_POST['remove_photo3'] ?? '0',
    $_POST['remove_photo4'] ?? '0',
    $_POST['remove_photo5'] ?? '0'
];
$current_image_paths = [
    $_POST['current_image_path1'] ?? null,
    $_POST['current_image_path2'] ?? null,
    $_POST['current_image_path3'] ?? null,
    $_POST['current_image_path4'] ?? null,
    $_POST['current_image_path5'] ?? null
];

if (empty($id) || empty($address)) {
    send_response(false, 'ID와 주소는 필수입니다.');
}
if (empty($detail_address)) {
    $detail_address = null;
}

// 6. DB 권한 확인 및 기존 데이터 로드
$current_db_paths = array_fill(0, 5, null);
$stmt_select = $conn->prepare("SELECT image_path1, image_path2, image_path3, image_path4, image_path5 FROM restaurants WHERE id = ? AND user_id = ?");
$stmt_select->bind_param("ii", $id, $user_id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();

if ($row = $result_select->fetch_row()) {
    $current_db_paths = array_slice($row, 0, 5);
} else {
    $stmt_select->close();
    $conn->close();
    send_response(false, '수정할 권한이 없거나 가게를 찾을 수 없습니다.');
}
$stmt_select->close();

$new_paths_to_update = $current_db_paths;
$image_changed = false;

// 7. 이미지 삭제 처리
for ($i = 0; $i < 5; $i++) {
    if ($remove_photos[$i] === '1') {
        $path_to_delete = $current_db_paths[$i];
        if (!empty($path_to_delete)) {
            @unlink($upload_dir . $path_to_delete);
            @unlink($thumb_dir . $path_to_delete);
        }
        $new_paths_to_update[$i] = null;
        $image_changed = true;
    } else {
        if (!empty($current_image_paths[$i])) {
            $new_paths_to_update[$i] = $current_image_paths[$i];
        }
    }
}

// 8. 새 이미지 업로드 처리
$uploaded_files = $_FILES['photos'] ?? [];
if (!empty($uploaded_files) && is_array($uploaded_files['name'])) {
    foreach ($uploaded_files['name'] as $file_index_str => $file_name) {
        $file_index = (int)$file_index_str;
        $db_index = $file_index - 1;

        if ($db_index < 0 || $db_index >= 5) continue;
        
        // 업로드 에러 체크
        if ($uploaded_files['error'][$file_index_str] !== UPLOAD_ERR_OK) continue;

        $file_tmp = $uploaded_files['tmp_name'][$file_index_str];
        
        // 파일 정보 가져오기 (이미지 파일인지 2차 검증)
        $check = @getimagesize($file_tmp);
        if($check === false) continue; // 이미지가 아니면 패스

        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid('img_', true) . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        $thumb_path = $thumb_dir . $unique_filename;

        // 기존 파일 정리 (덮어쓰기 전)
        $path_to_delete_old = $new_paths_to_update[$db_index];
        if (!empty($path_to_delete_old)) {
            @unlink($upload_dir . $path_to_delete_old);
            @unlink($thumb_dir . $path_to_delete_old);
        }

        // 이동 및 썸네일
        if (@move_uploaded_file($file_tmp, $full_path)) {
            // 썸네일 생성 시도
            if (create_thumbnail_for_update($full_path, $thumb_path)) {
                $new_paths_to_update[$db_index] = $unique_filename;
                $image_changed = true;
            } else {
                // 썸네일 실패 시 원본도 삭제 (깔끔하게)
                @unlink($full_path);
            }
        }
    }
}

// 9. DB 업데이트 쿼리
$update_columns = "address = ?, jibun_address = ?, detail_address = ?, rating = ?, star_rating = ?, 
                   image_path1 = ?, image_path2 = ?, image_path3 = ?, image_path4 = ?, image_path5 = ?";
$types = "ssssdsssss";
$bind_params = [
    $address, $jibun_address, $detail_address, $rating, $star_rating,
    $new_paths_to_update[0], $new_paths_to_update[1], $new_paths_to_update[2], 
    $new_paths_to_update[3], $new_paths_to_update[4]
];

$sql = "UPDATE restaurants SET $update_columns WHERE id = ? AND user_id = ?";
$types .= "ii";
$bind_params[] = $id;
$bind_params[] = $user_id;

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $conn->close();
    send_response(false, 'SQL 쿼리 준비 실패');
}

$bind_refs = [];
foreach ($bind_params as $key => $value) {
    $bind_refs[] = &$bind_params[$key];
}

if (!$stmt->bind_param($types, ...$bind_refs)) {
    $stmt->close();
    $conn->close();
    send_response(false, '바인딩 실패');
}

// 10. 실행 및 결과
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || $image_changed) {
        send_response(true, '맛집 정보가 성공적으로 수정되었습니다.');
    } else {
        send_response(true, '변경 사항이 없습니다.');
    }
} else {
    send_response(false, '수정에 실패했습니다: ' . $stmt->error);
}

$stmt->close();
$conn->close();
?>