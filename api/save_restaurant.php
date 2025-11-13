<?php
// 파일명: www/api/save_restaurant.php (다중 사진 업로드 및 DB 저장 로직으로 수정)

// 💡 [디버깅 목적] 에러 표시를 일시적으로 켚니다. 성공적으로 작동하면 다시 0으로 설정해주세요.
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

// 썸네일 생성 함수 (기존 로직 유지)
function create_thumbnail($source_path, $dest_path, $thumb_width = 300) {
    if (!extension_loaded('gd')) { return false; }
    $source_info = @getimagesize($source_path);
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
        case IMAGETYPE_JPEG: $source = @imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG: $source = @imagecreatefrompng($source_path); break;
        case IMAGETYPE_GIF: $source = @imagecreatefromgif($source_path); break;
        default: return false;
    }
    if (!$source) return false;

    // 원본 이미지에서 썸네일 크기만큼 중앙을 잘라냄 (크롭 중앙 정렬)
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

// 💡 [수정] 평가 문구 처리 로직 강화: trim() 후 문자열 길이가 0인 경우에만 NULL로 설정
$raw_rating = $_POST['rating'] ?? '';
$rating = trim($raw_rating);
// empty() 대신 strlen()으로 명확하게 문자열의 길이를 체크합니다.
$rating = (strlen($rating) === 0) ? null : htmlspecialchars($rating, ENT_QUOTES, 'UTF-8'); 

$star_rating = $_POST['star_rating'] ?? 0.0;
$force_add = $_POST['force'] ?? 'false';

if (empty($name) || (empty($address) && empty($jibun_address)) || empty($food_type)) {
    echo json_encode(['success' => false, 'message' => '가게 이름, 주소, 음식 종류는 필수 항목입니다.']);
    exit;
}

// 중복 확인 로직 (force 플래그가 없으면 체크)
if ($force_add === 'false') {
    $stmt_check = $conn->prepare(
        "SELECT name, address, food_type, detail_address FROM restaurants 
         WHERE (address = ? OR (jibun_address = ? AND ? != '')) AND detail_address <=> ?"
    );
    $stmt_check->bind_param("ssss", $address, $jibun_address, $jibun_address, $detail_address);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $duplicates = [];
        while ($row = $result_check->fetch_assoc()) {
            $duplicates[] = $row;
        }
        $stmt_check->close();
        echo json_encode(['success' => true, 'is_duplicate' => true, 'duplicates' => $duplicates]);
        exit;
    }
    $stmt_check->close();
}


// '동/읍/면/리' 이름 추출 로직 (문제 2 해결)
$location_dong = ''; $location_si = ''; $location_gu = ''; $location_ri = '';
$address_for_dong = !empty($jibun_address) ? $jibun_address : $address;
$address_for_si_gu = !empty($address) ? $address : $jibun_address;

if (!empty($address_for_si_gu)) {
    mb_internal_encoding("UTF-8");
    $parts = mb_split('\s+', $address_for_si_gu);
    if(isset($parts[0])) $location_si = $parts[0];
    if(isset($parts[1])) $location_gu = $parts[1];
}

// 💡 [수정] 동, 읍, 면, 리 추출 로직 강화: 주소 끝에서부터 검색하여 가장 상세한 행정구역을 찾습니다.
if (!empty($address_for_dong)) {
    mb_internal_encoding("UTF-8");
    $parts = mb_split('\s+', $address_for_dong);
    $target_parts = ['동', '읍', '면', '리'];
    
    // 뒤에서부터 검색하여 가장 상세한 주소 단위(동/읍/면/리)를 찾습니다.
    // '리'보다는 '동/읍/면'을 우선합니다.
    foreach (array_reverse($parts) as $part) {
        foreach ($target_parts as $suffix) {
            // mb_substr($part, -mb_strlen($suffix))는 PHP 7.1 이상에서 작동
            if (mb_substr($part, -mb_strlen($suffix)) === $suffix) {
                if ($suffix !== '리') {
                    $location_dong = $part;
                    break 2; // 바깥 루프까지 종료
                } else if (empty($location_dong)) {
                    // 동/읍/면이 아직 설정되지 않았다면 '리'를 임시 저장
                    $location_dong = $part; 
                }
            }
        }
    }
    
    // 동/읍/면/리를 찾지 못했고 주소 파트가 남아있다면, 마지막 파트를 사용합니다.
    if (empty($location_dong) && !empty($parts)) {
        $location_dong = array_pop($parts);
    }
}


// -----------------------------------------------------
// 💡 [수정] 다중 이미지 파일 업로드 처리
// -----------------------------------------------------
$image_paths = array_fill(1, 5, null); // [null, null, null, null, null]
$uploaded_files = $_FILES['photos'] ?? [];

if (!empty($uploaded_files) && is_array($uploaded_files['name'])) {
    $file_count = count($uploaded_files['name']);
    $valid_count = 0;
    
    // 최대 5장까지만 처리
    for ($i = 0; $i < min($file_count, 5); $i++) {
        // 파일 업로드 오류 확인
        if ($uploaded_files['error'][$i] !== UPLOAD_ERR_OK) {
            continue; 
        }

        $file_name = $uploaded_files['name'][$i];
        $file_tmp = $uploaded_files['tmp_name'][$i];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid('img_', true) . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        $thumb_path = $thumb_dir . $unique_filename;

        // 파일 이동 및 썸네일 생성
        if (@move_uploaded_file($file_tmp, $full_path)) {
            if (create_thumbnail($full_path, $thumb_path)) {
                $image_paths[$valid_count + 1] = $unique_filename; // 1-based 인덱스 사용
                $valid_count++;
            } else {
                @unlink($full_path); // 썸네일 생성 실패 시 원본 삭제
            }
        }
    }
}

// -----------------------------------------------------
// 💡 [수정] SQL 쿼리: image_path1 ~ 5 추가
// -----------------------------------------------------
$sql = "INSERT INTO restaurants (user_id, name, address, jibun_address, detail_address, food_type, rating, star_rating, image_path1, image_path2, image_path3, image_path4, image_path5, location_dong, location_si, location_gu, location_ri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL 쿼리 준비 실패: ' . $conn->error]);
    exit();
}

// 💡 [수정 완료] 타입 정의 문자열을 "issssssdsssssssss"로 수정했습니다.
// (rating: s, star_rating: d)
$types = "issssssdsssssssss"; 
$bind_params = array_merge(
    [
        $user_id, $name, $address, $jibun_address, $detail_address, 
        $food_type, $rating, $star_rating
    ],
    array_values($image_paths),
    [
        $location_dong, $location_si, $location_gu, $location_ri
    ]
);

// PHP 8.0 이전 환경과의 호환성을 위해 bind_param에 참조를 사용합니다.
$bind_refs = [];
foreach ($bind_params as $key => $value) {
    $bind_refs[] = &$bind_params[$key];
}

if (!$stmt->bind_param($types, ...$bind_refs)) {
    $error_message = '바인딩 실패: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => '맛집 추가에 실패했습니다: 바인딩 오류 (' . $error_message . ')']);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '맛집이 성공적으로 추가되었습니다. (사진 ' . $valid_count . '장 포함)']);
} else {
    $error_message = $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => '맛집 추가에 실패했습니다: DB 실행 오류 (' . $error_message . ')']);
}
$stmt->close();
$conn->close();
?>