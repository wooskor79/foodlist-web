<?php
// 파일명: www/api/save_restaurant.php (이 코드로 전체 교체)
header('Content-Type: application/json');
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// 저장 경로 설정 (사용자 환경에 맞게 절대 경로로 설정해야 할 수 있습니다)
$upload_dir = '/volume1/web/webs/images/';
$thumb_dir = $upload_dir . 'thumb/';

// 디렉토리가 없으면 생성
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_dir($thumb_dir)) {
    mkdir($thumb_dir, 0755, true);
}

// 썸네일 생성 함수
function create_thumbnail($source_path, $dest_path, $thumb_width = 300) {
    list($width, $height, $type) = getimagesize($source_path);
    if ($width == 0 || $height == 0) return false;

    $thumb_height = floor($height * ($thumb_width / $width));
    $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);

    // 투명 배경 처리
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);

    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }

    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            return imagejpeg($thumbnail, $dest_path, 90);
        case IMAGETYPE_PNG:
            return imagepng($thumbnail, $dest_path, 9);
        case IMAGETYPE_GIF:
            return imagegif($thumbnail, $dest_path);
    }
    return false;
}


$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$jibun_address = trim($_POST['jibun_address'] ?? '');
$detail_address = trim($_POST['detail_address'] ?? '');
$food_type = $_POST['food_type'] ?? '';
$rating = trim($_POST['rating'] ?? '');
$star_rating = $_POST['star_rating'] ?? 0.0;
$force = isset($_POST['force']) && $_POST['force'] === 'true';

if (empty($name) || empty($address) || empty($food_type)) {
    echo json_encode(['success' => false, 'message' => '가게 이름, 주소, 음식 종류는 필수 항목입니다.']);
    exit;
}

// 주소에서 동/읍/면 추출 (도로명, 지번 둘 다 시도)
$location_dong = '';
$addr_parts = explode(' ', $address);
if (count($addr_parts) >= 2) {
    $last_part = end($addr_parts);
    if (strpos($last_part, '동') !== false || strpos($last_part, '읍') !== false || strpos($last_part, '면') !== false || strpos($last_part, '가') !== false) {
        $location_dong = $last_part;
    } else if (isset($addr_parts[count($addr_parts) - 2])) {
        $second_last_part = $addr_parts[count($addr_parts) - 2];
         if (strpos($second_last_part, '동') !== false || strpos($second_last_part, '읍') !== false || strpos($second_last_part, '면') !== false) {
            $location_dong = $second_last_part;
        }
    }
}
if (empty($location_dong) && !empty($jibun_address)) {
     $addr_parts = explode(' ', $jibun_address);
     if (count($addr_parts) >= 2) {
        foreach($addr_parts as $part) {
            if (strpos($part, '동') !== false || strpos($part, '읍') !== false || strpos($part, '면') !== false) {
                $location_dong = $part;
                break;
            }
        }
    }
}


// 중복 체크 로직 (force 파라미터가 없을 때만)
if (!$force) {
    $stmt = $pdo->prepare("SELECT id, name, address FROM restaurants WHERE (name = ? AND address LIKE ?) OR name = ?");
    $address_pattern = substr($address, 0, strrpos($address, ' ')) . '%';
    $stmt->execute([$name, $address_pattern, $name]);
    $duplicates = $stmt->fetchAll();

    if ($duplicates) {
        echo json_encode(['success' => false, 'is_duplicate' => true, 'duplicates' => $duplicates]);
        exit;
    }
}


$image_path = null;
// 파일 업로드 처리
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $photo = $_FILES['photo'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($photo['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => '허용되지 않는 파일 형식입니다. (JPG, PNG, GIF만 가능)']);
        exit;
    }

    $file_extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid('img_', true) . '.' . $file_extension;
    
    $original_path = $upload_dir . $new_filename;
    $thumb_path_for_db = $thumb_dir . $new_filename;

    if (move_uploaded_file($photo['tmp_name'], $original_path)) {
        // 썸네일 생성
        if (create_thumbnail($original_path, $thumb_path_for_db)) {
            // DB에 저장될 경로는 웹에서 접근 가능한 상대 경로여야 합니다.
            // 예: 'images/img_....jpg'
            // 웹 루트를 기준으로 경로를 조정해야 합니다.
            // 여기서는 /webs/ 가 웹 루트라고 가정합니다.
            $image_path = 'images/' . $new_filename;
        } else {
             // 썸네일 생성 실패 시 원본 파일 삭제
            unlink($original_path);
            echo json_encode(['success' => false, 'message' => '썸네일 생성에 실패했습니다.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => '파일 업로드에 실패했습니다.']);
        exit;
    }
}


try {
    $stmt = $pdo->prepare(
        "INSERT INTO restaurants (name, address, jibun_address, detail_address, location_dong, food_type, rating, user_id, star_rating, image_path) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $address, $jibun_address, $detail_address, $location_dong, $food_type, $rating, $user_id, $star_rating, $image_path]);
    
    echo json_encode(['success' => true, 'message' => '맛집이 성공적으로 추가되었습니다.']);

} catch (PDOException $e) {
    // 에러 발생 시 업로드된 파일 삭제
    if ($image_path) {
        $real_original_path = $upload_dir . basename($image_path);
        $real_thumb_path = $thumb_dir . basename($image_path);
        if (file_exists($real_original_path)) unlink($real_original_path);
        if (file_exists($real_thumb_path)) unlink($real_thumb_path);
    }
    echo json_encode(['success' => false, 'message' => '데이터베이스 오류: ' . $e->getMessage()]);
}