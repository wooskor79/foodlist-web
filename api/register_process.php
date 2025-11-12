<?php
// 파일명: www/api/register_process.php (이 코드로 전체 교체)
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (strlen($username) < 2 || strlen($username) > 10) {
    echo json_encode(['success' => false, 'message' => '이름으로 입력.']);
    exit();
}
if (strlen($password) < 4) {
    echo json_encode(['success' => false, 'message' => '비밀번호는 4자 이상으로 입력해주세요.']);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => '이미 사용 중인 아이디입니다.']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password_hash);

if ($stmt->execute()) {
    // 💡 [추가] 가입 성공 시 자동 로그인 처리
    $new_user_id = $stmt->insert_id;
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $new_user_id;
    $_SESSION['username'] = $username;
    
    echo json_encode(['success' => true, 'message' => '가입이 완료되었으며, 자동으로 로그인됩니다.']);
} else {
    echo json_encode(['success' => false, 'message' => '가입에 실패했습니다: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>