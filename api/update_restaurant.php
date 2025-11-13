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
$address = $_POST['address'] ?? '';
$jibun_address = $_POST['jibun_address'] ?? '';
$detail_address = $_POST['detail_address'] ?? null;
$rating = $_POST['rating'] ?? '';
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
    if ($remove_photos[$i] === '1') {
        $path_to_delete = $current_db_paths[$i];
        if (!empty($path_to_delete)) {
            @unlink($upload_dir . $path_to_delete);
            @unlink($thumb_dir . $path_to_delete);
            $new_paths_to_update[$i] = null; // DB에서 경로 제거
            $image_changed = true;
        }
    }
}

// 2-2. 새 이미지 업로드 및 처리
$uploaded_files = $_FILES['photos'] ?? [];
$upload_counter = 0; // 업로드된 새 파일 카운터

if (!empty($uploaded_files) && is_array($uploaded_files['name'])) {
    $file_count = count($uploaded_files['name']);
    
    for ($i = 0; $i < min($file_count, 5); $i++) {
        // 파일 업로드 오류 확인
        if ($uploaded_files['error'][$i] !== UPLOAD_ERR_OK) {
            continue; 
        }

        // 새 파일 정보
        $file_name = $uploaded_files['name'][$i];
        $file_tmp = $uploaded_files['tmp_name'][$i];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid('img_', true) . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        $thumb_path = $thumb_dir . $unique_filename;

        // 파일 이동 및 썸네일 생성
        if (@move_uploaded_file($file_tmp, $full_path)) {
            if (create_thumbnail_for_update($full_path, $thumb_path)) {
                $upload_counter++;
                $image_changed = true;
                
                // 새로운 이미지 경로를 대체할 위치를 찾습니다.
                // 1. 기존에 삭제 플래그(remove_photoX = 1)로 비워진 위치를 우선 채웁니다.
                // 2. 비워진 위치가 없으면, 기존 이미지 경로를 덮어씁니다.
                $found_index = -1;
                for($j = 0; $j < 5; $j++) {
                    // 삭제 요청으로 NULL이 된 칸을 찾습니다.
                    if ($new_paths_to_update[$j] === null) {
                        $found_index = $j;
                        break;
                    }
                }

                // 삭제된 칸이 없으면, 덮어쓸 첫 번째 칸을 찾습니다. (클라이언트 로직상 첫 번째 칸이 선택될 것임)
                if ($found_index === -1) {
                     // 클라이언트의 파일 선택 순서에 따라 덮어쓰기 (새로 업로드된 파일이 몇 번째 필드인지 알 수 없으므로, 모든 필드를 덮어쓰기 하는 방식으로 처리)
                     // 하지만 클라이언트 JS에서 어떤 파일 인덱스에 매핑되어 올라왔는지 알 수 없으므로, 
                     // 여기서는 단순히 '덮어쓰기' 플래그가 넘어왔다고 가정하고, $current_image_paths 배열을 활용합니다.
                     // (현재 코드는 다중 파일 업로드를 지원하지만, UI 로직에서 각 파일을 어떤 DB 컬럼에 매핑할지 결정해야 합니다.)
                     // 💡 UI에서는 5개의 파일 입력 필드를 별도로 만들고, 각 필드를 'photos[]' 배열로 보내며, 동시에 'current_image_pathN'과 'remove_photoN'을 보내는 것으로 가정합니다.

                     // 💡 [임시 수정] 현재 POST로 넘어오는 파일 배열의 순서(index i)와 DB 경로 인덱스(index j)를 1:1 매칭하는 것은 불가능하므로,
                     // UI에서 넘어온 `current_image_pathN`을 활용합니다.
                     // 클라이언트의 JS 파일(main.js)에서 'photos' 배열이 아닌, 5개의 개별 파일 필드를 사용하도록 가정을 변경합니다. (하단의 PHP 코드에서 반영)
                }

                // 💡 [재수정] 클라이언트에서 5개의 개별 `photo[1]`, `photo[2]` ... 필드로 전송한다고 가정하고 처리합니다.
                // 이 코드는 `photos` 배열로 넘어온 모든 새 파일을, DB의 *비어있거나 삭제 요청된* `image_pathN`에 순서대로 채워 넣는 방식으로 동작합니다.
                
                // 비어있는 첫 번째 인덱스를 찾아 새 파일 경로를 삽입
                $insert_index = -1;
                for($j = 0; $j < 5; $j++) {
                    if ($new_paths_to_update[$j] === null) {
                        $insert_index = $j;
                        break;
                    }
                }
                
                // 비어있는 칸을 찾았으면 삽입
                if ($insert_index !== -1) {
                    $new_paths_to_update[$insert_index] = $unique_filename;
                } else {
                    // 5개 모두 꽉 차 있다면, 이 파일은 무시되거나 에러로 처리되어야 합니다.
                    // 현재 로직에서는 5개 이상 업로드를 허용하지 않으므로, 이 코드는 실행되지 않아야 합니다.
                    @unlink($full_path);
                    @unlink($thumb_path);
                }

            } else {
                @unlink($full_path); // 썸네일 생성 실패 시 원본 삭제
            }
        }
    }
}

// -----------------------------------------------------
// 3. 최종 SQL 쿼리 구성
// -----------------------------------------------------
$update_columns = "address = ?, jibun_address = ?, detail_address = ?, rating = ?, star_rating = ?, 
                   image_path1 = ?, image_path2 = ?, image_path3 = ?, image_path4 = ?, image_path5 = ?";
$types = "ssssdsssss";
$bind_params = [
    $address, $jibun_address, $detail_address, $rating, $star_rating,
    $new_paths_to_update[0], $new_paths_to_update[1], $new_paths_to_update[2], 
    $new_paths_to_update[3], $new_paths_to_update[4]
];

// WHERE 조건에 사용될 파라미터 추가
$types .= "i";
$bind_params[] = $id;

// SQL 쿼리 구성
$sql = "UPDATE restaurants SET $update_columns WHERE id = ? AND user_id = ?";
// 💡 user_id 체크가 필요하므로 WHERE 조건에 user_id를 추가해야 합니다.
// 이미 select에서 권한을 확인했지만, update에서도 안전하게 한 번 더 체크합니다.
$types .= "i";
$bind_params[] = $user_id;

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}

// PHP 8.0 이상 환경을 가정하여 bind_param 호출
if (!$stmt->bind_param($types, ...$bind_params)) {
    $error_message = '바인딩 실패: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

if ($stmt->execute()) {
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