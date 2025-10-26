<?php
// 파일명: www/register.php (이 코드로 전체 교체)

// 로그인 세션 유효기간을 30일로 설정합니다.
$cookie_lifetime = 60 * 60 * 24 * 30; // 30일
session_set_cookie_params($cookie_lifetime, "/");
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php"); // 로그인 상태이면 메인으로 이동
    exit;
}
$version = filemtime('css/style.css');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo $version; ?>">
    <script>
        try {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.className = 'dark-mode-loading';
            }
        } catch (e) { console.error('localStorage is not available'); }
    </script>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>회원가입</h1>
                <button id="theme-toggle-btn" class="theme-btn" aria-label="테마 전환">🌙</button>
            </div>
            <a href="index.php" class="btn-back">로그인으로</a>
        </header>
        <main>
            <form id="register-form" class="add-form-section">
                <h2>사용자 정보 입력</h2>
                <input type="text" id="username" name="username" placeholder="이름입력" required minlength="2" maxlength="10">
                <input type="password" id="password" name="password" placeholder="비밀번호 (4자 이상)" required minlength="4">
                <button type="submit" class="btn-save">가입하기</button>
            </form>
        </main>
    </div>
    <div id="toast-container"></div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- 💡 [추가] 테마 관리 기능 ---
        const themeToggleBtn = document.getElementById('theme-toggle-btn');

        function applyTheme(theme) {
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
                if(themeToggleBtn) themeToggleBtn.textContent = '☀️';
            } else {
                document.body.classList.remove('dark-mode');
                if(themeToggleBtn) themeToggleBtn.textContent = '🌙';
            }
            document.documentElement.classList.remove('dark-mode-loading');
        }

        function initializeTheme() {
            try {
                const preferredTheme = localStorage.getItem('theme') || 'light';
                applyTheme(preferredTheme);
            } catch (e) {
                console.error('localStorage is not available');
                applyTheme('light');
            }
        }

        function toggleTheme() {
            try {
                const currentTheme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                localStorage.setItem('theme', newTheme);
                applyTheme(newTheme);
            } catch (e) {
                console.error('localStorage is not available');
                showToast('테마 설정을 저장할 수 없습니다.', false);
            }
        }

        initializeTheme();
        if(themeToggleBtn) themeToggleBtn.addEventListener('click', toggleTheme);
        // --- 테마 관리 끝 ---

        const form = document.getElementById('register-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = '가입 중...';

            try {
                const response = await fetch('api/register_process.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                showToast(result.message, result.success);
                if (result.success) {
                    setTimeout(() => { window.location.href = 'index.php'; }, 1500);
                }
            } catch (error) {
                showToast('오류가 발생했습니다.', false);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = '가입하기';
            }
        });

        function showToast(message, isSuccess = true) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${isSuccess ? 'success' : 'error'}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 3000);
        }
    });
    </script>
</body>
</html>