<?php
// 파일명: www/index.php (이 코드로 전체 교체)

// 로그인 세션 유효기간을 30일로 설정합니다.
$cookie_lifetime = 60 * 60 * 24 * 30; // 30일
session_set_cookie_params($cookie_lifetime, "/");
session_start();

$is_loggedin = isset($_SESSION['user_id']) && !empty($_SESSION['username']);
$username = $is_loggedin ? htmlspecialchars($_SESSION['username']) : '';

$version = filemtime('css/style.css');
$js_version = filemtime('js/main.js');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>맛집 리스트</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo $version; ?>">
</head>
<body>
    <div id="pull-to-refresh-indicator">
        <div class="arrow">⬇</div>
        <div class="spinner"></div>
    </div>

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
                <input type="text" id="dong-search-input" placeholder="주소 또는 가게이름 검색(2글자이상)">
                <button id="search-btn">검색</button>
                <div id="search-results" class="search-results-box"></div>
            </div>
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
                <div id="restaurant-list">
                    <p class="placeholder">맛집 목록을 불러오는 중...</p>
                </div>
                <div id="pagination-bottom" class="pagination-container"></div>
            </div>
        </main>
    </div>
    <div id="toast-container"></div>
    
    <div id="share-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>맛집 공유하기</h2>
            <p><strong>가게:</strong> <span id="share-restaurant-name"></span></p>
            <form id="share-form">
                <input type="hidden" id="share-restaurant-id" name="restaurant_id">
                <div id="share-user-list">
                    <p class="placeholder">공유할 사용자를 불러오는 중...</p>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-save">공유</button>
                    <button type="button" id="close-share-modal-btn" class="btn-cancel">취소</button>
                </div>
            </form>
        </div>
    </div>

    <div id="photo-modal" class="modal-overlay hidden">
        <div class="photo-modal-content">
            <span id="close-photo-modal-btn" class="photo-modal-close">&times;</span>
            <img id="modal-image" src="" alt="맛집 사진">
        </div>
    </div>

    <script src="js/main.js?v=<?php echo $js_version; ?>"></script>
</body>
</html>