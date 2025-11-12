<?php
// 파일명: www/api/login_process.php (이 코드로 전체 교체)
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => '아이디와 비밀번호를 모두 입력해주세요.']);
    exit();
}

// DB에서 사용자 정보 가져오기
$stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // 입력된 비밀번호와 DB의 해시된 비밀번호 비교
    if (password_verify($password, $user['password_hash'])) {
        // 로그인 성공: 세션에 사용자 정보 저장
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.']);
}

$stmt->close();
$conn->close();
?>