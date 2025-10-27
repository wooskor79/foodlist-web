<?php
// 파일명: www/add.php (이 코드로 전체 교체)
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 카카오 API JavaScript 키를 이곳에서 관리합니다.
$kakao_api_key = "341110f947a005cbf66da8265ac7a95c"; // 본인의 JavaScript 키

$version = filemtime('css/style.css');
$js_version = filemtime('js/add.js');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>맛집 추가</title>

    <script src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo $kakao_api_key; ?>&libraries=services&autoload=false" defer></script>

    <link rel="stylesheet" href="css/style.css?v=<?php echo $version; ?>">
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
            <div class="add-form-section">
                <form id="add-restaurant-form" enctype="multipart/form-data">
                    <input type="text" id="name" name="name" placeholder="가게 이름" required>
                    
                    <div class="address-search-group">
                        <input type="text" id="address-search" placeholder="도로명 또는 지번 주소 검색">
                        <button type="button" id="search-address-btn">주소 검색</button>
                    </div>

                    <div id="address-results-container" class="hidden">
                        <p id="address-results-text"></p>
                    </div>

                    <input type="text" id="road-address" name="address" placeholder="도로명 주소 (자동 입력)" readonly required>
                    <input type="text" id="jibun-address" name="jibun_address" placeholder="지번 주소 (자동 입력)" readonly>

                    <div id="detail-address-container">
                         <input type="text" id="detail-address" name="detail_address" placeholder="상세 주소 (직접 입력)">
                    </div>

                    <select id="food-type" name="food_type" required>
                        <option value="" disabled selected>음식 종류 선택</option>
                        <option value="한식">한식</option>
                        <option value="중식">중식</option>
                        <option value="양식">양식</option>
                        <option value="일식">일식</option>
                        <option value="기타">기타</option>
                        <option value="육류">육류</option>
                    </select>
                    
                    <div class="star-rating-input">
                        <label for="star-rating">별점</label>
                        <p class="star-instruction">별을 터치하여 0.5점 단위로 선택하세요.</p>
                        <div class="star-input-group">
                            <div class="stars">
                                <span class="star" data-value="1">★</span>
                                <span class="star" data-value="2">★</span>
                                <span class="star" data-value="3">★</span>
                                <span class="star" data-value="4">★</span>
                                <span class="star" data-value="5">★</span>
                            </div>
                            <span class="current-star-rating">0.0 / 5.0</span>
                            <button type="button" class="btn-zero-star">별 0개</button>
                        </div>
                        <input type="hidden" id="star-rating" name="star_rating" value="0.0">
                    </div>

                    <textarea id="rating" name="rating" placeholder="평가 (예: 맛있어요, 친절해요)"></textarea>

                    <div class="photo-upload-section">
                        <label for="photo-input">사진 추가</label>
                        <input type="file" id="photo-input" name="photo" accept="image/*">
                        <div id="thumbnail-preview" class="hidden">
                            <img id="thumbnail-image" src="#" alt="선택한 이미지 썸네일">
                            <button type="button" id="remove-photo-btn">&times;</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">저장</button>
                </form>
            </div>
        </main>
    </div>

    <div id="duplicate-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>중복 의심 가게</h2>
            <p>입력하신 가게와 유사한 가게가 이미 등록되어 있습니다. 그래도 추가하시겠습니까?</p>
            <div id="duplicate-list"></div>
            <div class="modal-actions">
                <button id="force-add-btn" class="btn-save">계속 추가</button>
                <button id="cancel-add-btn" class="btn-cancel">취소</button>
            </div>
        </div>
    </div>
    <div id="toast-container"></div>
    <script src="js/add.js?v=<?php echo $js_version; ?>"></script>
</body>
</html>