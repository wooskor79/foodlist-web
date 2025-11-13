<?php
// 파일명: www/api/update_restaurant.php (다중 사진 업로드 및 DB 업데이트 로직으로 수정)

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
$address = trim($_POST['address'] ?? '');
$jibun_address = trim($_POST['jibun_address'] ?? '');
$detail_address = trim($_POST['detail_address'] ?? '');
// 💡 [수정] rating 값을 받아 공백을 제거하고, 값이 비어있으면 NULL로 설정
$rating = trim($_POST['rating'] ?? '');
$rating = empty($rating) ? null : $rating; // 빈 문자열이면 NULL로 설정

$star_rating = $_POST['star_rating'] ?? 0.0;
// 💡 [수정] 다중 이미지 관리를 위한 입력 파라미터 (1~5)
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
    echo json_encode(['success' => false, 'message' => 'ID와 주소는 필수입니다.']);
    exit();
}
if (empty($detail_address)) {
    $detail_address = null;
}

// 1. 기존 데이터베이스 이미지 경로 가져오기 (파일 삭제 및 신규 파일 처리를 위해)
$current_db_paths = array_fill(0, 5, null);
$stmt_select = $conn->prepare("SELECT image_path1, image_path2, image_path3, image_path4, image_path5 FROM restaurants WHERE id = ? AND user_id = ?");
$stmt_select->bind_param("ii", $id, $user_id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();
if ($row = $result_select->fetch_row()) {
    $current_db_paths = $row;
} else {
    // 해당 ID의 맛집이 없거나 소유자가 다름
    echo json_encode(['success' => false, 'message' => '수정할 권한이 없거나 가게를 찾을 수 없습니다.']);
    $stmt_select->close();
    $conn->close();
    exit();
}
$stmt_select->close();

$new_paths_to_update = $current_db_paths;
$image_changed = false;


// -----------------------------------------------------
// 2. 이미지 처리 로직 (기존 사진 삭제 및 새 파일 업로드/교체)
// -----------------------------------------------------
// 2-1. 기존 사진 삭제 요청 처리 (remove_photo 플래그 체크)
for ($i = 0; $i < 5; $i++) {
    // 클라이언트에서 '1'을 보내면 삭제 요청
    if ($remove_photos[$i] === '1') {
        $path_to_delete = $current_db_paths[$i];
        if (!empty($path_to_delete)) {
            @unlink($upload_dir . $path_to_delete);
            @unlink($thumb_dir . $path_to_delete);
        }
        $new_paths_to_update[$i] = null; // DB에서 경로 제거
        $image_changed = true;
    } else {
        // 삭제 요청이 없고, 클라이언트에서 기존 경로가 비어있다면(새 파일이 없다는 뜻), DB 경로를 유지
        if (empty($current_image_paths[$i])) {
             // 클라이언트에서 current_image_path가 비어있고 (새 파일 없음), 삭제 요청도 없으면
             // 이전에 DB에 저장된 경로를 유지해야 합니다.
             // 이 로직은 불필요하게 복잡해질 수 있으므로, 클라이언트에서 전송된 current_image_path를 신뢰하는 방식으로 단순화합니다.
             // 하지만, 클라이언트에서 전송된 current_image_path는 "수정 전"의 값이므로, 
             // 삭제되지 않은 항목은 현재 DB 경로를 그대로 유지해야 합니다.
        } else {
             // 클라이언트에서 current_image_path를 보냈다면 (삭제 요청 없음), 해당 경로를 유지
             $new_paths_to_update[$i] = $current_image_paths[$i];
        }
    }
}

// 2-2. 새 이미지 업로드 및 처리
// 💡 [수정] 클라이언트 JS에서 'photos[1]', 'photos[2]'... 형태로 파일을 전송한다고 가정하고 처리합니다.
$uploaded_files = $_FILES['photos'] ?? [];

if (!empty($uploaded_files) && is_array($uploaded_files['name'])) {
    
    // 이 배열은 클라이언트에서 보낸 파일 인덱스를 DB의 image_pathN 순서(0~4)에 맞게 매핑하는 데 사용됩니다.
    // 클라이언트에서 files[1]을 보냈다면, $i=1을 사용합니다.
    foreach ($uploaded_files['name'] as $file_index_str => $file_name) {
        $file_index = (int)$file_index_str; // '1', '2', ... -> 1, 2, ...
        $db_index = $file_index - 1; // DB 컬럼 인덱스 (0~4)

        // DB 인덱스가 유효한지 확인
        if ($db_index < 0 || $db_index >= 5) {
            continue;
        }
        
        // 파일 업로드 오류 확인
        if ($uploaded_files['error'][$file_index_str] !== UPLOAD_ERR_OK) {
            continue; 
        }

        $file_tmp = $uploaded_files['tmp_name'][$file_index_str];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid('img_', true) . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        $thumb_path = $thumb_dir . $unique_filename;

        // 기존 파일이 있다면 삭제 (2-1에서 이미 삭제된 경우에도 안전합니다.)
        $path_to_delete_old = $new_paths_to_update[$db_index];
        if (!empty($path_to_delete_old)) {
            @unlink($upload_dir . $path_to_delete_old);
            @unlink($thumb_dir . $path_to_delete_old);
        }

        // 파일 이동 및 썸네일 생성
        if (@move_uploaded_file($file_tmp, $full_path)) {
            if (create_thumbnail_for_update($full_path, $thumb_path)) {
                $new_paths_to_update[$db_index] = $unique_filename; // 새 경로로 덮어쓰기
                $image_changed = true;
            } else {
                @unlink($full_path); // 썸네일 생성 실패 시 원본 삭제
            }
        }
    }
}

// -----------------------------------------------------
// 3. 최종 SQL 쿼리 구성
// -----------------------------------------------------
// rating 필드 타입을 `s` (string)으로 바인딩하여 NULL 값을 올바르게 처리합니다.
$update_columns = "address = ?, jibun_address = ?, detail_address = ?, rating = ?, star_rating = ?, 
                   image_path1 = ?, image_path2 = ?, image_path3 = ?, image_path4 = ?, image_path5 = ?";
$types = "ssssdsssss";
$bind_params = [
    $address, $jibun_address, $detail_address, $rating, $star_rating,
    $new_paths_to_update[0], $new_paths_to_update[1], $new_paths_to_update[2], 
    $new_paths_to_update[3], $new_paths_to_update[4]
];

// WHERE 조건에 사용될 파라미터 추가
// SQL 쿼리 구성
$sql = "UPDATE restaurants SET $update_columns WHERE id = ? AND user_id = ?";
// 💡 user_id 체크가 필요하므로 WHERE 조건에 user_id를 추가해야 합니다.
$types .= "ii";
$bind_params[] = $id;
$bind_params[] = $user_id;

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}

// PHP 8.0 이상 환경을 가정하여 bind_param 호출
// $bind_params 배열의 요소들을 참조로 변환 (PHP 8.0 이전 호환을 위해)
// PHP 8.0 이상에서는 ...$bind_params로 충분하나, 안전을 위해 배열을 만듭니다.
$bind_refs = [];
foreach ($bind_params as $key => $value) {
    $bind_refs[] = &$bind_params[$key];
}

// bind_param 호출
if (!$stmt->bind_param($types, ...$bind_refs)) {
    $error_message = '바인딩 실패: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}


if ($stmt->execute()) {
    // affected_rows가 0이더라도, 별점/평가 등 다른 필드가 업데이트 되었거나 이미지가 변경되었을 수 있으므로 성공으로 간주합니다.
    if ($stmt->affected_rows > 0 || $image_changed) {
        // 이미지가 변경되었거나 다른 필드가 변경되었으면 성공 메시지
        echo json_encode(['success' => true, 'message' => '맛집 정보가 성공적으로 수정되었습니다.']);
    } else {
        echo json_encode(['success' => true, 'message' => '변경 사항이 없습니다.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '수정에 실패했습니다: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>