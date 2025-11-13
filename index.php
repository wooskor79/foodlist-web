<?php
// 파일명: www/index.php (스플래시 화면 로직 분리 적용, 슬라이드 모달 추가)
session_start();
$is_loggedin = isset($_SESSION['user_id']) && !empty($_SESSION['username']);
$username = $is_loggedin ? htmlspecialchars($_SESSION['username']) : '';
$version = filemtime('css/style.css') ?? time();
$js_version = filemtime('js/main.js') ?? time();

// 💡 [수정] 스플래시 이미지 경로 변수 삭제
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>맛집 리스트</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo $version; ?>">
    <script>
        // 💡 [수정] 테마 초기화 스크립트 (CSS 파일 로드 전에 어두운 모드 깜빡임 방지)
        try {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.className = 'dark-mode-loading';
                document.body.classList.add('dark-mode');
            }
        } catch (e) { console.error('localStorage is not available'); }
    </script>
    
</head>
<body>
    <?php 
        // 기능을 활성화하려면 아래 주석을 제거하세요.
      //  include_once '3second.php';
    ?>

    <div id="pull-to-refresh-indicator"><div class="arrow">⬇</div><div class="spinner"></div></div>
    <div class="container">
        <header>
            <div class="header-left">
                <a href="index.php" class="header-title-link"><h1>맛집 리스트</h1></a>
                <button id="theme-toggle-btn" class="theme-btn" aria-label="테마 전환">🌙</button>
            </div>
            <div class="header-buttons">
                <?php if ($is_loggedin): ?>
                    <span class="welcome-message"><?php echo $username; ?>님</span>
                    <a href="add.php" class="btn-add">가게입력</a>
                    <a href="api/logout.php" class="btn-logout">로그아웃</a>
                <?php else: ?>
                    <a href="register.php" class="btn-register">회원가입</a>
                    <button id="login-show-btn" class="btn-login">로그인</button>
                    <div id="login-form" class="login-form-inline hidden">
                        <input type="text" id="username-input" placeholder="아이디">
                        <input type="password" id="password-input" placeholder="비밀번호">
                        <button id="login-submit-btn">로그인</button>
                    </div>
                <?php endif; ?>
            </div>
        </header>
        <main>
            <div class="search-section">
                <input type="text" id="dong-search-input" placeholder="가게이름 또는 주소 일부 검색">
                <button id="search-btn">검색</button>
            </div>
            <div id="search-results" class="search-results-box"></div>
            <div class="list-section">
                <div id="filter-buttons" class="filter-container">
                    <button class="filter-btn active" data-filter="모두">모두</button>
                    <button class="filter-btn" data-filter="한식">한식</button>
                    <button class="filter-btn" data-filter="중식">중식</button>
                    <button class="filter-btn" data-filter="양식">양식</button>
                    <button class="filter-btn" data-filter="일식">일식</button>
                    <button class="filter-btn" data-filter="기타">기타</button>
                    <button class="filter-btn" data-filter="육류">육류</button>
                    <?php if ($is_loggedin): ?>
                        <button class="filter-btn" data-filter="즐겨찾기">❤️ 즐겨찾기</button>
                    <?php endif; ?>
                </div>
                <div class="sort-container">
                    <select id="sort-dropdown" class="sort-dropdown">
                        <option value="name">이름순 정렬</option>
                        <option value="rating">별점순 정렬</option>
                    </select>
                </div>
                <hr>
                <div id="pagination-top" class="pagination-container"></div>
                <div id="restaurant-list"><p class="placeholder">맛집 목록을 불러오는 중...</p></div>
                <div id="pagination-bottom" class="pagination-container"></div>
            </div>
        </main>
    </div>
    <div id="toast-container"></div>
    
    <div id="share-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>'<span id="share-restaurant-name"></span>' 공유하기</h2>
            <form id="share-form">
                <input type="hidden" id="share-restaurant-id" name="restaurant_id" value="">
                <p>공유할 사용자를 선택하세요. (기존 공유 사용자는 재선택해야 유지됩니다)</p>
                <div id="share-user-list"></div>
                <div class="modal-actions">
                    <button type="submit" class="btn-share" id="share-submit-btn">공유</button>
                    <button type="button" class="btn-cancel" id="close-share-modal-btn">취소</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 💡 [수정] 사진 슬라이드 기능을 위한 모달 변경 -->
    <div id="photo-modal" class="modal-overlay hidden">
        <div class="photo-modal-content">
            <span class="photo-modal-close" id="close-photo-modal-btn">&times;</span>
            <div class="slider-container">
                <div id="modal-slider" class="modal-slider">
                    <!-- 이미지가 동적으로 로드될 영역 -->
                </div>
                <button class="slider-btn prev" id="slider-prev-btn">&#10094;</button>
                <button class="slider-btn next" id="slider-next-btn">&#10095;</button>
                <div id="slider-pagination" class="slider-pagination">
                    <!-- 페이지네이션 도트가 로드될 영역 -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/main.js?v=<?php echo $js_version; ?>"></script>
</body>
</html>