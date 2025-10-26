<?php
// 파일명: /api/db_config.php (이 코드로 전체 교체)

// 로그인 세션 유효기간을 30일로 설정합니다.
$cookie_lifetime = 60 * 60 * 24 * 30; // 30일
session_set_cookie_params($cookie_lifetime, "/");
session_start();

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

// Web Station 환경에 맞게 DB 정보를 직접 입력합니다.
$host = '127.0.0.1';
$user = 'root';
$password = 'dldntjd@D79';
$dbname = 'tasty_list'; // 💡 데이터베이스 이름을 'tasty_list'로 변경

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");
?>