<?php
// 파일명: www/add.php (이 코드로 전체 교체)
session_start();
// 💡 [수정] 로그인 확인 로직을 user_id 기준으로 변경
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // 💡 [수정] 상대 경로로 변경
    exit;
}
$version = filemtime('css/style.css');
$js_version = filemtime('js/add.js');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>맛집 추가</title>
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
                <h1>맛집 추가</h1>
                <button id="theme-toggle-btn" class="theme-btn" aria-label="테마 전환">🌙</button>
            </div>
            <a href="index.php" class="btn-back">목록으로</a>
        </header>
        <main>
            <form id="restaurant-form" class="add-form-section">
                <h2>맛집 정보 입력</h2>
                
                <input type="hidden" id="location_dong_input" name="location_dong" required>
                <input type="text" name="name" placeholder="가게 이름" required>
                
                <div class="address-search-group">
                    <input type="text" id="address-search-input" name="address" placeholder="가게 주소 (도로명 or 지번)" required>
                    <button type="button" id="address-search-btn">주소 검색</button>
                </div>
                
                <input type="hidden" id="location-si-input" name="location_si">
                <input type="hidden" id="location-gu-input" name="location_gu">
                <input type="hidden" id="location-ri-input" name="location_ri">
                <input type="hidden" id="jibun-address-input" name="jibun_address">

                <div id="address-results-container" class="hidden">
                    <p><strong>도로명:</strong> <span id="road-addr-result" class="address-selectable"></span></p>
                    <p><strong>지번:</strong> <span id="jibun-addr-result" class="address-selectable"></span></p>
                    <p class="info-text">👆 도로명 또는 지번 주소를 클릭하여 선택하고 상세주소를 입력하세요.</p>
                </div>

                <div id="detail-address-container" class="hidden">
                    <input type="text" id="detail-address-input" name="detail_address" placeholder="상세주소 입력 (예: 101동 101호)">
                </div>
                
                <select name="food_type" required>
                    <option value="" disabled selected>음식 종류 선택</option>
                    <option value="한식">한식</option>
                    <option value="중식">중식</option>
                    <option value="양식">양식</option>
                    <option value="일식">일식</option>
                    <option value="기타">기타</option>
                    <option value="뷔페">뷔페</option>
                </select>

                <div class="star-rating-input">
                    <p class="star-instruction">별점 (한번 터치: 0.5점, 두번 터치: 1점)</p>
                    <div class="star-input-group">
                        <div class="stars">
                            <span class="star" data-value="1">★</span>
                            <span class="star" data-value="2">★</span>
                            <span class="star" data-value="3">★</span>
                            <span class="star" data-value="4">★</span>
                            <span class="star" data-value="5">★</span>
                        </div>
                        <span class="current-star-rating">0.0 / 5.0</span>
                        <button type="button" id="zero-star-btn" class="btn-zero-star">평점안줌</button>
                    </div>
                    <input type="hidden" name="star_rating" id="star-rating-value" value="0.0">
                </div>

                <textarea name="rating" placeholder="평가 (예: 맛있음, 친절함)" rows="3"></textarea>
                
                <button type="submit" class="btn-save">저장</button>
            </form>
        </main>
    </div>

    <div id="duplicate-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>⚠️ 주소 중복</h2>
            <p>입력하신 주소와 상세주소에 이미 등록된 맛집이 있습니다. 그래도 추가하시겠습니까?</p>
            <div id="duplicate-list"></div>
            <div class="modal-actions">
                <button type="button" id="force-save-btn" class="btn-save">무시하고 저장</button>
                <button type="button" id="close-modal-btn" class="btn-cancel">취소</button>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>
    <script src="js/add.js?v=<?php echo $js_version; ?>"></script>
</body>
</html>