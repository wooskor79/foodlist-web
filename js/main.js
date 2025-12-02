// 파일명: www/js/main.js (가게 수정 시 이름 수정 기능 추가)
document.addEventListener('DOMContentLoaded', () => {
    // --- 기본 요소 ---
    const searchInput = document.getElementById('dong-search-input');
    const searchBtn = document.getElementById('search-btn');
    const searchResults = document.getElementById('search-results');
    const restaurantList = document.getElementById('restaurant-list');
    const filterButtonsContainer = document.getElementById('filter-buttons');
    const sortDropdown = document.getElementById('sort-dropdown');
    const paginationTop = document.getElementById('pagination-top');
    const paginationBottom = document.getElementById('pagination-bottom');
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    
    // 모달 요소
    const shareModal = document.getElementById('share-modal');
    const shareForm = document.getElementById('share-form');
    const shareRestaurantName = document.getElementById('share-restaurant-name');
    const shareRestaurantId = document.getElementById('share-restaurant-id');
    const shareUserList = document.getElementById('share-user-list');
    const closeShareModalBtn = document.getElementById('close-share-modal-btn');
    
    // 사진 슬라이드 모달 요소
    const photoModal = document.getElementById('photo-modal');
    const closePhotoModalBtn = document.getElementById('close-photo-modal-btn');
    const modalSlider = document.getElementById('modal-slider');
    const sliderPrevBtn = document.getElementById('slider-prev-btn');
    const sliderNextBtn = document.getElementById('slider-next-btn');
    const sliderPagination = document.getElementById('slider-pagination');

    const ptrIndicator = document.getElementById('pull-to-refresh-indicator');

    // --- 상태 관리 변수 ---
    let isLoggedIn = false;
    let allRestaurants = [];
    let filteredRestaurants = [];
    let currentPage = 1;
    const itemsPerPage = 10;
    let touchStartY = 0;
    let isRefreshing = false;
    
    // 슬라이드 상태 관리
    let currentSlideIndex = 0;
    let currentPhotoPaths = [];

    // --- 페이지 초기화 ---
    initializeTheme();
    fetchRestaurants('모두');

    // --- 이벤트 리스너 ---
    themeToggleBtn.addEventListener('click', toggleTheme);
    searchBtn.addEventListener('click', handleSearch);
    searchInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') handleSearch(); });
    searchInput.addEventListener('input', handleAutocomplete);
    sortDropdown.addEventListener('change', handleSortChange);
    
    document.addEventListener('click', (e) => {
        if (shareModal && shareModal.querySelector('.modal-content') && !shareModal.querySelector('.modal-content').contains(e.target) && !e.target.classList.contains('btn-share')) {
             if (!shareModal.classList.contains('hidden')) closeShareModal();
        }
        if (photoModal && !photoModal.querySelector('.photo-modal-content').contains(e.target) && !e.target.classList.contains('btn-view-photo')) {
            if (!photoModal.classList.contains('hidden')) closePhotoModal();
        }
        if (searchResults && !searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });

    if (searchResults) searchResults.addEventListener('click', handleSearchResultClick);
    filterButtonsContainer.addEventListener('click', handleFilterClick);
    restaurantList.addEventListener('click', handleCardActions);
    if(closePhotoModalBtn) closePhotoModalBtn.addEventListener('click', closePhotoModal);
    
    if (sliderPrevBtn) sliderPrevBtn.addEventListener('click', () => changeSlide(-1));
    if (sliderNextBtn) sliderNextBtn.addEventListener('click', () => changeSlide(1));


    // 풀 투 리프레시 로직
    document.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) { touchStartY = e.touches[0].clientY; } 
        else { touchStartY = -1; }
    }, { passive: true });

    document.addEventListener('touchmove', (e) => {
        if (touchStartY === -1 || isRefreshing) return;
        const touchY = e.touches[0].clientY;
        const pullDistance = touchY - touchStartY;
        if (pullDistance > 0) {
            ptrIndicator.style.top = `${Math.min(pullDistance / 2 - 50, 20)}px`;
            if (pullDistance > 150) { ptrIndicator.classList.add('refreshing'); } 
            else { ptrIndicator.classList.remove('refreshing'); }
        }
    }, { passive: true });

    document.addEventListener('touchend', () => {
        if (touchStartY === -1 || isRefreshing) return;
        if (ptrIndicator.classList.contains('refreshing')) {
            isRefreshing = true;
            ptrIndicator.style.top = '20px';
            location.reload();
        } else {
            ptrIndicator.style.top = '-50px';
        }
        touchStartY = -1;
    });

    function initializeTheme() {
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggleBtn.textContent = '☀️';
            } else {
                themeToggleBtn.textContent = '🌙';
            }
        } catch (e) { console.error('테마 로딩 실패:', e); }
    }

    function toggleTheme() {
        try {
            document.body.classList.toggle('dark-mode');
            const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            themeToggleBtn.textContent = theme === 'dark' ? '☀️' : '🌙';
            localStorage.setItem('theme', theme);
        } catch (e) { console.error('테마 저장 실패:', e); }
    }
    
    async function fetchRestaurants(term) {
        restaurantList.innerHTML = '<p class="placeholder">불러오는 중...</p>';
        paginationTop.innerHTML = '';
        paginationBottom.innerHTML = '';
        try {
            const url = `api/get_restaurants.php?term=${encodeURIComponent(term || '모두')}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                isLoggedIn = result.loggedin;
                allRestaurants = result.data.map(r => ({
                    ...r,
                    image_paths: [r.image_path1, r.image_path2, r.image_path3, r.image_path4, r.image_path5].filter(p => p)
                }));
                const currentFilter = filterButtonsContainer.querySelector('.active')?.dataset.filter || '모두';
                applyFilterAndRender(currentFilter);
            } else {
                 restaurantList.innerHTML = `<p class="placeholder">오류: ${result.message}</p>`;
            }
        } catch (error) {
            console.error('Error fetching restaurants:', error);
            restaurantList.innerHTML = `<p class="placeholder">맛집 목록을 불러오는 데 실패했습니다.</p>`;
        }
    }
    
    function handleFilterClick(e) {
        const target = e.target;
        if (target.classList.contains('filter-btn')) {
            const filter = target.dataset.filter;
            filterButtonsContainer.querySelector('.active')?.classList.remove('active');
            target.classList.add('active');
            applyFilterAndRender(filter);
        }
    }
    
    function handleSearch() {
        const searchTerm = searchInput.value.trim();
        filterButtonsContainer.querySelector('.active')?.classList.remove('active');
        const allButton = filterButtonsContainer.querySelector('[data-filter="모두"]');
        if (allButton) allButton.classList.add('active');
        if (searchResults) searchResults.style.display = 'none';
        fetchRestaurants(searchTerm);
    }
    
    function handleSortChange() {
        const currentFilter = filterButtonsContainer.querySelector('.active')?.dataset.filter || '모두';
        applyFilterAndRender(currentFilter);
    }

    function sortAndRender() {
        const sortBy = sortDropdown.value;
        filteredRestaurants.sort((a, b) => {
            if (sortBy === 'rating') {
                const ratingA = parseFloat(a.star_rating);
                const ratingB = parseFloat(b.star_rating);
                if (ratingB !== ratingA) return ratingB - ratingA;
            }
            return a.name.localeCompare(b.name, 'ko');
        });
        currentPage = 1;
        renderPage(currentPage);
    }
    
    function applyFilterAndRender(filter) {
        if (filter === '즐겨찾기') {
            filteredRestaurants = allRestaurants.filter(r => r.is_favorite == 1);
        } else if (filter === '모두') {
            filteredRestaurants = [...allRestaurants];
        } else {
            filteredRestaurants = allRestaurants.filter(r => r.food_type === filter);
        }
        sortAndRender();
    }

    function renderPage(page) {
        currentPage = page;
        const totalPages = Math.ceil(filteredRestaurants.length / itemsPerPage); 
        
        if (filteredRestaurants.length === 0) {
            restaurantList.innerHTML = '<p class="placeholder">해당 조건의 맛집이 없습니다.</p>';
            renderPagination(0, 0);
            return;
        }
        const pageItems = filteredRestaurants.slice((page - 1) * itemsPerPage, page * itemsPerPage);
        renderRestaurantList(pageItems);
        renderPagination(totalPages, page);
    }

    function renderRestaurantList(restaurants) {
        restaurantList.innerHTML = '';
        restaurants.forEach(r => {
            const card = document.createElement('div');
            card.className = 'restaurant-card';
            card.dataset.id = r.id;
            card.dataset.foodType = r.food_type;
            card.dataset.starRating = r.star_rating;
            card.dataset.isFavorite = r.is_favorite;
            card.dataset.isOwner = r.is_owner;
            card.dataset.ownerName = r.owner_name;
            card.dataset.imagePaths = JSON.stringify(r.image_paths);

            const isOwner = r.is_owner == 1;
            const favoriteBtn = isLoggedIn ? `<button class="btn-favorite ${r.is_favorite == 1 ? 'is-favorite' : ''}" aria-label="즐겨찾기">♥</button>` : '';
            
            let actionButtons = '';
            if (isLoggedIn) {
                actionButtons = isOwner
                    ? `<div class="card-actions">${favoriteBtn}<button class="btn-share">공유</button><button class="btn-edit">수정</button><button class="btn-delete">삭제</button></div>`
                    : `<div class="card-actions">${favoriteBtn}<button class="btn-delete">삭제</button></div>`;
            }
            
            const ownerInfo = !isOwner && isLoggedIn ? `<p class="owner-info">${escapeHTML(r.owner_name)}님이 공유함</p>` : '';
            let starDisplayHTML = Number(r.star_rating) > 0 
                ? `${generateStarsHTML(r.star_rating)} <span class="star-text">${Number(r.star_rating).toFixed(1)}/5.0</span>`
                : `<span class="no-rating-text">별점을 줄 가치 없음</span>`;
            
            const locationDongText = r.location_dong ? escapeHTML(r.location_dong) : '지역 정보 없음';
            const hasJibun = r.jibun_address && r.jibun_address !== r.address;
            const jibunButton = hasJibun ? `<button class="btn-toggle-jibun">지번보기</button>` : '';
            const hasPhotos = r.image_paths.length > 0;
            const photoButton = hasPhotos ? `<button class="btn-view-photo">사진보기 (${r.image_paths.length})</button>` : '';
            
            const detailAddr = r.detail_address ? ` ${escapeHTML(r.detail_address)}` : '';
            const roadAddrFull = `${escapeHTML(r.address)}${detailAddr}`;
            const jibunAddrFull = r.jibun_address ? `${escapeHTML(r.jibun_address)}${detailAddr}` : '';
            const addressContent = `<p class="info-item"><strong>도로명:</strong> <span class="address-text">${roadAddrFull}</span></p>` +
                (hasJibun ? `<p class="info-item jibun-address hidden"><strong>지번:</strong> <span class="address-text">${jibunAddrFull}</span></p>` : '');
            
            let rawRatingText = r.rating;
            let ratingTextContent;
            const trimmedRatingText = String(rawRatingText || '').trim();
            const isRatingEmpty = trimmedRatingText === '';

            if (isRatingEmpty) {
                 ratingTextContent = `<p class="rating-text no-rating-text">평가 없음</p>`;
            } else {
                 let escapedRatingText = escapeHTML(trimmedRatingText); 
                 ratingTextContent = `<p class="rating-text">${escapedRatingText}</p>`;
            }

            const ratingHTML = `<div class="rating"><div class="rating-content"><strong>평가:</strong>${ratingTextContent}</div></div>`;

            card.innerHTML = `
                <div class="card-header"><h3>${escapeHTML(r.name)}</h3></div>
                <div class="card-subheader">
                    <div class="subheader-left">
                        <span class="location-dong">(${locationDongText})</span>
                        ${jibunButton} ${photoButton}
                    </div>
                    ${actionButtons}
                </div>
                ${ownerInfo}
                <div class="info-group">${addressContent}<p class="info-item"><strong>음식:</strong> ${escapeHTML(r.food_type)}</p></div>
                ${ratingHTML}
                <div class="star-display">${starDisplayHTML}</div>`;
            restaurantList.appendChild(card);
        });
    }

    function renderPagination(totalPages, page) {
        const containers = [paginationTop, paginationBottom];
        if (totalPages <= 1) {
            containers.forEach(c => c.innerHTML = '');
            return;
        }
        let html = `<button class="pagination-btn" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>이전</button>`;
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="pagination-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        html += `<button class="pagination-btn" data-page="${page + 1}" ${page === totalPages ? 'disabled' : ''}>다음</button>`;
        containers.forEach(c => {
            c.innerHTML = html;
            c.onclick = (e) => {
                if (e.target.classList.contains('pagination-btn') && !e.target.disabled) {
                    renderPage(parseInt(e.target.dataset.page));
                    window.scrollTo(0, 0);
                }
            };
        });
    }

    async function handleAutocomplete() {
        const searchTerm = searchInput.value.trim();
        if (searchTerm.length < 2) {
            if(searchResults) searchResults.style.display = 'none'; 
            return; 
        }
        try {
            const response = await fetch(`api/search_dong.php?term=${encodeURIComponent(searchTerm)}`);
            const locations = await response.json();
            if(!searchResults) return;
            searchResults.innerHTML = '';
            if (locations.length > 0) {
                locations.forEach(dongName => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.textContent = dongName;
                    item.dataset.dong = dongName;
                    searchResults.appendChild(item);
                });
            } else { searchResults.innerHTML = '<div class="search-result-item">결과 없음</div>'; }
            searchResults.style.display = 'block';
        } catch (error) { console.error('Error fetching locations:', error); }
    }
    
    function handleSearchResultClick(e) {
        if (e.target && e.target.dataset.dong) {
            const dong = e.target.dataset.dong;
            searchInput.value = dong;
            if(searchResults) searchResults.style.display = 'none';
            handleSearch();
        }
    }
    
    const loginShowBtn = document.getElementById('login-show-btn');
    const loginForm = document.getElementById('login-form');
    const usernameInput = document.getElementById('username-input');
    const passwordInput = document.getElementById('password-input');
    const loginSubmitBtn = document.getElementById('login-submit-btn');
    if (loginShowBtn) {
        loginShowBtn.addEventListener('click', () => {
            loginShowBtn.classList.add('hidden');
            document.querySelector('.btn-register').classList.add('hidden');
            loginForm.classList.remove('hidden');
            setTimeout(() => {
                usernameInput.setAttribute('type', 'password'); 
                usernameInput.focus();
                setTimeout(() => {
                    usernameInput.setAttribute('type', 'text');
                    usernameInput.focus();
                }, 50);
            }, 100);
        });
    }
    if (loginSubmitBtn) {
        const loginAction = async () => {
            const formData = new FormData();
            formData.append('username', usernameInput.value);
            formData.append('password', passwordInput.value);
            try {
                const response = await fetch('api/login_process.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) { location.reload(); } 
                else { showToast(result.message, false); }
            } catch (error) { showToast('로그인 중 오류 발생', false); }
        };
        loginSubmitBtn.addEventListener('click', loginAction);
        passwordInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') loginSubmitBtn.click(); });
        usernameInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') loginSubmitBtn.click(); });
    }

    function handleCardActions(e) {
        const card = e.target.closest('.restaurant-card');
        if (!card) return;
        const id = card.dataset.id;
        const isOwner = card.dataset.isOwner == 1;
        const imagePaths = JSON.parse(card.dataset.imagePaths || '[]');

        if (e.target.classList.contains('btn-toggle-jibun')) {
            const jibunP = card.querySelector('.jibun-address');
            if (jibunP) jibunP.classList.toggle('hidden');
            e.target.textContent = jibunP.classList.contains('hidden') ? '지번보기' : '숨기기';
            return;
        }
        
        if (e.target.classList.contains('btn-view-photo')) {
            if (imagePaths && imagePaths.length > 0) openPhotoSliderModal(imagePaths);
            return;
        }
        if (!isLoggedIn) return;
        if (e.target.classList.contains('btn-favorite')) {
            toggleFavorite(id, e.target);
            return;
        }

        const favoriteBtn = card.querySelector('.btn-favorite')?.outerHTML || '';
        if (e.target.classList.contains('btn-delete')) {
            let confirmHtml = `${favoriteBtn}`;
            if (isOwner) confirmHtml += `<button class="btn-share">공유</button> `;
            confirmHtml += `<span>삭제? </span><button class="btn-confirm-yes">예</button><button class="btn-confirm-no">아니오</button>`;
            card.querySelector('.card-actions').innerHTML = confirmHtml;
        }
        if (e.target.classList.contains('btn-confirm-no')) {
            let originalButtons = `${favoriteBtn}`;
            if (isOwner) {
                originalButtons += `<button class="btn-share">공유</button> <button class="btn-edit">수정</button>`;
            }
            originalButtons += `<button class="btn-delete">삭제</button>`;
            card.querySelector('.card-actions').innerHTML = originalButtons;
        }
        if (e.target.classList.contains('btn-confirm-yes')) {
            if (isOwner) { deleteRestaurant(id); } 
            else { unshareRestaurant(id); }
        }
        if (!isOwner) return;

        if (e.target.classList.contains('btn-share')) {
            const restaurantName = card.querySelector('h3').textContent;
            openShareModal(id, restaurantName);
            return;
        }
        if (e.target.classList.contains('btn-edit')) {
            editRestaurant(card);
        }
        if (e.target.classList.contains('btn-cancel-edit')) {
            // 수정 취소 시 목록 새로고침
            fetchRestaurants(searchInput.value || '모두');
        }
        if (e.target.classList.contains('btn-save-edit')) {
            saveRestaurantEdit(card);
        }
    }

    // 💡 [수정] editRestaurant 함수: 가게 이름(Name) 수정 필드 추가
    function editRestaurant(card) {
        const id = card.dataset.id;
        const restaurantData = allRestaurants.find(r => r.id == id);
        const currentStarRating = parseFloat(card.dataset.starRating);
        const favoriteBtn = card.querySelector('.btn-favorite')?.outerHTML || '';
        const currentImagePaths = JSON.parse(card.dataset.imagePaths || '[]');
        
        // 다중 사진 필드 생성
        let photoInputsHtml = '';
        for (let i = 1; i <= 5; i++) {
            const path = currentImagePaths[i - 1] || '';
            const isHidden = !path; 
            photoInputsHtml += `
                <div class="photo-edit-group" style="margin-bottom: 10px;">
                    <label for="photo-input-${id}-${i}">사진 ${i} (교체/추가)</label>
                    <div class="custom-file-wrapper" id="custom-file-wrapper-${id}-${i}">
                        <input type="text" id="photo-file-name-${id}-${i}" placeholder="${path ? path : '파일 선택 (터치하여 열기)'}" value="${path}" readonly>
                        <input type="file" id="photo-input-${id}-${i}" name="photos[${i}]" data-index="${i}" accept="image/*" class="file-overlay-input"> 
                        <button type="button" class="photo-select-button">파일 선택</button>
                    </div>
                    <div id="thumbnail-preview-${id}-${i}" class="thumbnail-preview ${isHidden ? 'hidden' : ''}" style="max-width: 100px;">
                        <img id="thumbnail-image-${id}-${i}" src="${path ? 'images/thumb/' + path : '#'}" alt="현재/선택 이미지 썸네일" style="max-height: 100px;">
                        <button type="button" class="remove-photo-btn" data-id="${id}" data-index="${i}">&times;</button>
                        <input type="hidden" name="current_image_path${i}" value="${path}">
                        <input type="hidden" name="remove_photo${i}" value="0"> 
                    </div>
                </div>
            `;
        }
        
        const currentRatingText = restaurantData.rating !== null ? restaurantData.rating : ''; 

        // 💡 [수정] cardHeader(정적 타이틀)를 제거하고, formHtml 내부에 이름 입력 필드(input)를 추가합니다.
        const formHtml = `
            <form class="edit-form" enctype="multipart/form-data">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="food_type" value="${restaurantData.food_type}"> 
                
                <div class="info-group">
                    <p class="info-item" style="margin-top:10px;">
                        <strong>이름:</strong> 
                        <input type="text" class="name-edit-input" name="name" value="${escapeHTML(restaurantData.name)}" required style="width: 100%; padding: 0.5rem; font-size: 1.2rem; font-weight: bold; border: 1px solid var(--primary-color); border-radius: var(--border-radius);">
                    </p>
                </div>

                <div class="info-group">
                    <p class="info-item"><strong>도로명:</strong><textarea class="address-edit-area" name="address">${escapeHTML(restaurantData.address)}</textarea></p>
                    <p class="info-item"><strong>지번:</strong><textarea class="jibun-edit-area" name="jibun_address">${escapeHTML(restaurantData.jibun_address || '')}</textarea></p>
                    <p class="info-item"><strong>상세:</strong><textarea class="detail-edit-area" name="detail_address" placeholder="상세 주소">${escapeHTML(restaurantData.detail_address || '')}</textarea></p>
                    <p class="info-item"><strong>음식:</strong> ${escapeHTML(restaurantData.food_type)}</p>
                </div>

                <div class="rating">
                    <div class="rating-content"><strong>평가:</strong><textarea class="rating-edit-area" name="rating" placeholder="평가 (예: 맛있어요, 친절해요)">${escapeHTML(currentRatingText)}</textarea></div>
                </div>

                <div class="star-rating-input" id="star-rating-input-${id}">
                    <label for="star-rating-${id}">별점</label>
                    <p class="star-instruction">별을 터치하여 0.5점 단위로 선택하세요.</p>
                    <div class="star-input-group">
                        <div class="stars edit-mode star-container-${id}">${[1,2,3,4,5].map(v => `<span class="star" data-value="${v}">★</span>`).join('')}</div>
                        <button type="button" class="btn-zero-star-edit" data-id="${id}">별 0개</button>
                    </div>
                    <input type="hidden" class="star-rating-edit-value" name="star_rating" value="${currentStarRating}">
                </div>
                
                <div class="photo-upload-section">
                    ${photoInputsHtml}
                </div>
            </form>
        `;
        
        // 카드 내용을 전체 폼으로 교체
        card.innerHTML = formHtml; 
        
        // 액션 버튼 영역 재구성
        card.insertAdjacentHTML('beforeend', `<div class="card-actions">${favoriteBtn}<button class="btn-share">공유</button> <button class="btn-save-edit">저장</button><button class="btn-cancel-edit">취소</button></div>`);


        // --- 이벤트 리스너 재연결 (별점, 사진 등) ---
        const starRatingInputContainer = document.getElementById(`star-rating-input-${id}`);
        const starContainer = card.querySelector(`.star-container-${id}`);
        const zeroStarBtn = card.querySelector(`.btn-zero-star-edit`);

        if (starContainer) starContainer.addEventListener('click', handleStarEditClick);
        if (zeroStarBtn) zeroStarBtn.addEventListener('click', handleStarEditClick);
        if (starRatingInputContainer) updateEditStars(starRatingInputContainer, currentStarRating);

        for (let i = 1; i <= 5; i++) {
            const photoInput = document.getElementById(`photo-input-${id}-${i}`);
            const photoFileNameInput = document.getElementById(`photo-file-name-${id}-${i}`);
            const removePhotoBtn = card.querySelector(`.remove-photo-btn[data-index="${i}"]`);
            const thumbnailImage = document.getElementById(`thumbnail-image-${id}-${i}`);
            const thumbnailPreview = document.getElementById(`thumbnail-preview-${id}-${i}`);
            const removePhotoHiddenInput = card.querySelector(`input[name="remove_photo${i}"]`);
            const currentImagePathInput = card.querySelector(`input[name="current_image_path${i}"]`);

            if (photoInput) {
                photoInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        if (photoFileNameInput) photoFileNameInput.value = file.name;
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (thumbnailImage) thumbnailImage.src = e.target.result;
                            if (thumbnailPreview) thumbnailPreview.classList.remove('hidden');
                            if (removePhotoHiddenInput) removePhotoHiddenInput.value = '0';
                            if (currentImagePathInput) currentImagePathInput.value = ''; 
                        }
                        reader.readAsDataURL(file);
                    } else {
                        if (photoFileNameInput) photoFileNameInput.value = currentImagePathInput?.value || '파일 선택 (터치하여 열기)';
                    }
                });
            }
            
            if (removePhotoBtn) {
                removePhotoBtn.addEventListener('click', function() {
                    if (photoInput) photoInput.value = '';
                    if (photoFileNameInput) photoFileNameInput.value = '파일 선택 (터치하여 열기)';
                    if (thumbnailImage) thumbnailImage.src = '#';
                    if (thumbnailPreview) thumbnailPreview.classList.add('hidden');
                    if (removePhotoHiddenInput) removePhotoHiddenInput.value = '1';
                    if (currentImagePathInput) currentImagePathInput.value = ''; 
                });
            }
        }
    }

    function handleStarEditClick(e) {
        const starContainerElement = e.target.closest('.star-rating-input');
        if (!starContainerElement) return;

        const ratingInput = starContainerElement.querySelector('.star-rating-edit-value');
        const starsContainer = starContainerElement.querySelector('.stars.edit-mode');
        let newRating = parseFloat(ratingInput.value);

        if (e.target.classList.contains('btn-zero-star-edit')) {
            newRating = 0.0;
        } else if (e.target.classList.contains('star')) {
            const clickedValue = parseInt(e.target.dataset.value);
            const rect = e.target.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const starWidth = rect.width;
            const isHalf = clickX < starWidth / 2;
            newRating = isHalf ? clickedValue - 0.5 : clickedValue;
            
            if (parseFloat(ratingInput.value) === newRating) {
                newRating = 0.0;
            }
        }
        ratingInput.value = newRating.toFixed(1);
        updateEditStars(starContainerElement, newRating);
    }
    
    async function saveRestaurantEdit(card) {
        const editForm = card.querySelector('.edit-form');
        if (!editForm) return;

        const formData = new FormData();
        
        for (let i = 1; i <= 5; i++) {
            const fileInput = document.getElementById(`photo-input-${card.dataset.id}-${i}`);
            const currentImagePathInput = editForm.querySelector(`input[name="current_image_path${i}"]`);
            const removePhotoHiddenInput = editForm.querySelector(`input[name="remove_photo${i}"]`);

            const file = fileInput?.files?.[0];
            if (file) {
                formData.append(`photos[${i}]`, file, file.name); 
            }
            
            if (currentImagePathInput && currentImagePathInput.value) {
                 formData.append(`current_image_path${i}`, currentImagePathInput.value);
            }
            
            if (removePhotoHiddenInput) {
                formData.append(`remove_photo${i}`, removePhotoHiddenInput.value);
            }
        }

        editForm.querySelectorAll('input, textarea, select').forEach(input => {
            if (input.type !== 'file') {
                if (input.name && !input.name.startsWith('photos[') && !input.name.startsWith('current_image_path') && !input.name.startsWith('remove_photo')) {
                    formData.append(input.name, input.value);
                }
            }
        });

        const saveBtn = card.querySelector('.btn-save-edit');
        saveBtn.disabled = true;
        saveBtn.textContent = '저장 중...';

        await updateRestaurant(formData);

        saveBtn.disabled = false;
        saveBtn.textContent = '저장';
    }

    async function openShareModal(id, name) {
        if (!shareModal) return;
        shareRestaurantId.value = id;
        shareRestaurantName.textContent = name;
        shareUserList.innerHTML = '<p class="placeholder">사용자 목록을 불러오는 중...</p>';
        shareModal.classList.remove('hidden');
        try {
            const response = await fetch(`api/get_shared_users.php?restaurant_id=${id}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                renderUserList(result.data, id);
            }
        } catch (error) {
            shareUserList.innerHTML = `<p class="placeholder">사용자 목록 로딩 실패</p>`;
            console.error('Error fetching users:', error);
        }
        
        try {
            const responseAllUsers = await fetch('api/get_users.php');
            const resultAllUsers = await responseAllUsers.json();
            
            if (resultAllUsers.success) { 
                renderUserList(resultAllUsers.data, id); 
            } else { 
                shareUserList.innerHTML = `<p class="placeholder">공유할 사용자 목록 로딩 실패: ${resultAllUsers.message}</p>`; 
            }
        } catch (error) { 
             console.error('Error fetching all users for sharing:', error);
        }
    }
    
    function openPhotoSliderModal(paths) {
        if (!photoModal || paths.length === 0) return;

        currentPhotoPaths = paths;
        currentSlideIndex = 0;
        
        modalSlider.innerHTML = '';
        paths.forEach((path, index) => {
            const imgWrapper = document.createElement('div');
            imgWrapper.className = 'slide';
            imgWrapper.style.width = '100%';
            imgWrapper.style.flexShrink = '0';
            imgWrapper.style.textAlign = 'center';

            const img = document.createElement('img');
            img.src = 'images/' + path; 
            img.alt = `맛집 사진 ${index + 1}`;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '80vh';
            img.style.objectFit = 'contain';
            img.style.borderRadius = 'var(--border-radius)';
            imgWrapper.appendChild(img);
            modalSlider.appendChild(imgWrapper);
        });

        updateSlideDisplay();
        photoModal.classList.remove('hidden');
    }

    function updateSlideDisplay() {
        const totalSlides = currentPhotoPaths.length;

        modalSlider.style.transform = `translateX(-${currentSlideIndex * 100}%)`;
        
        if (sliderPrevBtn) sliderPrevBtn.style.display = totalSlides > 1 ? 'block' : 'none';
        if (sliderNextBtn) sliderNextBtn.style.display = totalSlides > 1 ? 'block' : 'none';
        
        if (sliderPrevBtn) sliderPrevBtn.disabled = currentSlideIndex === 0;
        if (sliderNextBtn) sliderNextBtn.disabled = currentSlideIndex === totalSlides - 1;

        if (sliderPagination) {
            sliderPagination.innerHTML = currentPhotoPaths.map((_, index) => 
                `<span class="dot ${index === currentSlideIndex ? 'active' : ''}" data-index="${index}"></span>`
            ).join('');
            
            sliderPagination.querySelectorAll('.dot').forEach(dot => {
                dot.addEventListener('click', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    changeSlide(index - currentSlideIndex);
                });
            });
        }
    }

    function changeSlide(direction) {
        let newIndex = currentSlideIndex + direction;
        const totalSlides = currentPhotoPaths.length;

        if (newIndex < 0) newIndex = 0;
        if (newIndex >= totalSlides) newIndex = totalSlides - 1;

        currentSlideIndex = newIndex;
        updateSlideDisplay();
    }

    function closePhotoModal() {
        if (!photoModal) return;
        photoModal.classList.add('hidden');
        if(modalSlider) modalSlider.innerHTML = '';
        currentPhotoPaths = [];
        currentSlideIndex = 0;
        if(sliderPagination) sliderPagination.innerHTML = '';
    }

    async function renderUserList(users, restaurantId) {
        if (users.length === 0) {
            shareUserList.innerHTML = '<p class="placeholder">공유할 다른 사용자가 없습니다.</p>';
            return;
        }

        let sharedUsers = [];
        try {
            const response = await fetch(`api/get_shared_users.php?restaurant_id=${restaurantId}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                sharedUsers = result.data.map(user => parseInt(user.shared_with_user_id));
            }
        } catch (error) {
            console.error("공유 사용자 목록을 불러오는 데 실패했습니다.", error);
        }

        let listHtml = '';
        users.forEach(user => {
            const isChecked = sharedUsers.includes(parseInt(user.id)) ? 'checked' : '';
            listHtml += `<div class="share-user-item">
                            <input type="checkbox" id="user-${user.id}" name="share_with_ids[]" value="${user.id}" ${isChecked}>
                            <label for="user-${user.id}">${escapeHTML(user.username)}</label>
                         </div>`;
        });
        shareUserList.innerHTML = listHtml;
    }

    if (shareForm) {
        shareForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(shareForm);
            const submitButton = shareForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = '공유 중...';
            try {
                const response = await fetch('api/share_restaurant.php', { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.success);
                if(result.success) closeShareModal();
            } catch (error) {
                showToast('공유 중 오류가 발생했습니다.', false);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = '공유';
            }
        });
    }

    if(closeShareModalBtn) closeShareModalBtn.addEventListener('click', closeShareModal);
    function closeShareModal() {
        if (!shareModal) return;
        shareModal.classList.add('hidden');
        shareRestaurantId.value = '';
        shareRestaurantName.textContent = '';
        shareUserList.innerHTML = '';
    }

    async function toggleFavorite(id, button) {
        const card = button.closest('.restaurant-card');
        const isCurrentlyFavorite = card.dataset.isFavorite == 1;
        const newStatus = isCurrentlyFavorite ? 0 : 1;
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', newStatus);
        try {
            const response = await fetch('api/toggle_favorite.php', { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.success);
            if (result.success) {
                card.dataset.isFavorite = newStatus;
                button.classList.toggle('is-favorite', newStatus === 1);
                const restaurant = allRestaurants.find(r => r.id == id);
                if (restaurant) restaurant.is_favorite = newStatus;
                if (filterButtonsContainer.querySelector('.active')?.dataset.filter === '즐겨찾기') {
                    applyFilterAndRender('즐겨찾기');
                }
            }
        } catch (error) { console.error('Error toggling favorite:', error); }
    }

    async function deleteRestaurant(id) {
        const formData = new FormData();
        formData.append('id', id);
        try {
            const response = await fetch('api/delete_restaurant.php', { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.success);
            if (result.success) {
                allRestaurants = allRestaurants.filter(r => r.id != id);
                applyFilterAndRender(filterButtonsContainer.querySelector('.active').dataset.filter);
            }
        } catch (error) { console.error('Error deleting:', error); }
    }
    
    async function unshareRestaurant(id) {
        const formData = new FormData();
        formData.append('id', id);
        try {
            const response = await fetch('api/unshare_restaurant.php', { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.success);
            if (result.success) {
                allRestaurants = allRestaurants.filter(r => r.id != id);
                applyFilterAndRender(filterButtonsContainer.querySelector('.active').dataset.filter);
            }
        } catch (error) { console.error('Error unsharing:', error); }
    }

    async function updateRestaurant(formData) {
        try {
            const response = await fetch('api/update_restaurant.php', { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.success);
            if (result.success) {
                fetchRestaurants(searchInput.value || '모두');
            }
        } catch (error) { 
            console.error('Error updating:', error);
            showToast('맛집을 수정하는 데 실패했습니다.', false);
        }
    }

    function updateEditStars(container, rating) {
        const starsContainer = container.querySelector('.stars.edit-mode');
        const stars = starsContainer.querySelectorAll('.star');
        stars.forEach(star => {
            const starValue = parseInt(star.dataset.value);
            star.classList.remove('filled', 'half');
            if (rating >= starValue) star.classList.add('filled');
            else if (rating >= starValue - 0.5) star.classList.add('half');
            else star.classList.remove('filled', 'half');
        });
    }
    
    function showToast(message, isSuccess = true) {
        const container = document.getElementById('toast-container');
        if(!container) return;
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

    function generateStarsHTML(rating) {
        let html = '';
        const ratingNum = Number(rating);
        for (let i = 1; i <= 5; i++) {
            if (ratingNum >= i) html += '<span class="star filled">★</span>';
            else if (ratingNum >= i - 0.5) html += '<span class="star half">★</span>';
            else html += '<span class="star">☆</span>';
        }
        return html;
    }

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return str.toString().replace(/[&<>"']/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#x27;', '"': '&quot;'}[tag] || tag));
    }
});