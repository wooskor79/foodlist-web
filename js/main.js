// 파일명: www/js/main.js (이 코드로 전체 교체)
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
    const photoModal = document.getElementById('photo-modal');
    const modalImage = document.getElementById('modal-image');
    const closePhotoModalBtn = document.getElementById('close-photo-modal-btn');
    
    const ptrIndicator = document.getElementById('pull-to-refresh-indicator');

    // --- 상태 관리 변수 ---
    let isLoggedIn = false;
    let allRestaurants = [];
    let filteredRestaurants = [];
    let currentPage = 1;
    const itemsPerPage = 10;
    let touchStartY = 0;
    let isRefreshing = false;

    // --- 페이지 초기화 ---
    initializeTheme();
    fetchRestaurants(searchInput.value.trim() || '모두');

    // --- 이벤트 리스너 ---
    themeToggleBtn.addEventListener('click', toggleTheme);
    searchBtn.addEventListener('click', handleSearch);
    searchInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') handleSearch(); });
    searchInput.addEventListener('input', handleAutocomplete);
    sortDropdown.addEventListener('change', handleSortChange);
    document.addEventListener('click', (e) => {
        if (shareModal && !shareModal.querySelector('.modal-content').contains(e.target) && !e.target.classList.contains('btn-share')) {
             if (!shareModal.classList.contains('hidden')) {
                closeShareModal();
             }
        }
        if (photoModal && !photoModal.querySelector('.photo-modal-content').contains(e.target) && !e.target.classList.contains('btn-view-photo')) {
            if (!photoModal.classList.contains('hidden')) {
                closePhotoModal();
            }
        }
        if (!searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });
    searchResults.addEventListener('click', handleSearchResultClick);
    filterButtonsContainer.addEventListener('click', handleFilterClick);
    restaurantList.addEventListener('click', handleCardActions);
    closePhotoModalBtn.addEventListener('click', closePhotoModal);
    
    // Pull-to-Refresh 이벤트 리스너
    document.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            touchStartY = e.touches[0].clientY;
        } else {
            touchStartY = -1;
        }
    }, { passive: true });

    document.addEventListener('touchmove', (e) => {
        if (touchStartY === -1 || isRefreshing) return;
        const touchY = e.touches[0].clientY;
        const pullDistance = touchY - touchStartY;
        if (pullDistance > 0) {
            ptrIndicator.style.top = `${Math.min(pullDistance / 2 - 50, 20)}px`;
            if (pullDistance > 150) {
                ptrIndicator.classList.add('refreshing');
            } else {
                ptrIndicator.classList.remove('refreshing');
            }
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

    // --- 테마 관리 ---
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
    
    // --- 핵심 기능 함수 ---
    function handleSearch() {
        const searchTerm = searchInput.value.trim();
        searchResults.style.display = 'none';
        fetchRestaurants(searchTerm);
    }
    
    async function fetchRestaurants(term) {
        restaurantList.innerHTML = '<p class="placeholder">불러오는 중...</p>';
        paginationTop.innerHTML = '';
        paginationBottom.innerHTML = '';
        try {
            const response = await fetch(`api/get_restaurants.php?term=${encodeURIComponent(term)}`);
            const result = await response.json();
            if (result.success) {
                isLoggedIn = result.loggedin;
                allRestaurants = result.data;
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

    function handleSortChange() {
        applyFilterAndRender(filterButtonsContainer.querySelector('.active')?.dataset.filter || '모두');
    }

    function sortAndRender() {
        const sortBy = sortDropdown.value;
        filteredRestaurants.sort((a, b) => {
            if (sortBy === 'rating') {
                const ratingA = parseFloat(a.star_rating);
                const ratingB = parseFloat(b.star_rating);
                if (ratingB !== ratingA) return ratingB - ratingA;
                return a.name.localeCompare(b.name, 'ko');
            } else {
                return a.name.localeCompare(b.name, 'ko');
            }
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
            restaurantList.innerHTML = '<p class="placeholder">등록하거나 공유받은 맛집이 없습니다.</p>';
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
            // image_path 데이터셋 추가
            card.dataset.imagePath = r.image_path || '';

            const isOwner = r.is_owner == 1;
            const favoriteBtn = isLoggedIn ? `<button class="btn-favorite ${r.is_favorite == 1 ? 'is-favorite' : ''}" aria-label="즐겨찾기">♥</button>` : '';
            
            let actionButtons = '';
            if (isLoggedIn) {
                if (isOwner) {
                    actionButtons = `<div class="card-actions">
                        ${favoriteBtn}
                        <button class="btn-share">공유</button> 
                        <button class="btn-edit">수정</button>
                        <button class="btn-delete">삭제</button>
                       </div>`;
                } else {
                    actionButtons = `<div class="card-actions">
                        ${favoriteBtn}
                        <button class="btn-delete">삭제</button>
                       </div>`;
                }
            }
            
            const ownerInfo = !isOwner && isLoggedIn ? `<p class="owner-info">${escapeHTML(r.owner_name)}님이 공유함</p>` : '';

            let starDisplayHTML = Number(r.star_rating) > 0 
                ? `${generateStarsHTML(r.star_rating)} <span class="star-text">${Number(r.star_rating).toFixed(1)}/5.0</span>`
                : `<span class="no-rating-text">별점을 줄 가치 없음</span>`;
            
            const hasJibun = r.jibun_address && r.jibun_address !== r.address;
            const jibunButton = hasJibun ? `<button class="btn-toggle-jibun">지번보기</button>` : '';

            // 사진보기 버튼 추가
            const photoButton = r.image_path ? `<button class="btn-view-photo">사진보기</button>` : '';
            
            const detailAddr = r.detail_address ? ` ${escapeHTML(r.detail_address)}` : '';
            const roadAddrFull = `${escapeHTML(r.address)}${detailAddr}`;
            const jibunAddrFull = r.jibun_address ? `${escapeHTML(r.jibun_address)}${detailAddr}` : '';

            const addressContent = 
                `<p class="info-item"><strong>도로명:</strong> <span class="address-text">${roadAddrFull}</span></p>` +
                (hasJibun ? `<p class="info-item jibun-address hidden"><strong>지번:</strong> <span class="address-text">${jibunAddrFull}</span></p>` : '');
            
            let ratingHTML = '';
            if (r.rating && r.rating.trim() !== '0' && r.rating.trim() !== '') {
                ratingHTML = `<div class="rating"><div class="rating-content"><strong>평가:</strong><p class="rating-text">${escapeHTML(r.rating)}</p></div></div>`;
            }

            card.innerHTML = `
                <div class="card-header">
                    <h3>${escapeHTML(r.name)}</h3>
                </div>
                <div class="card-subheader">
                    <div class="subheader-left">
                        <span class="location-dong">(${escapeHTML(r.location_dong)})</span>
                        ${jibunButton}
                        ${photoButton}
                    </div>
                    ${actionButtons}
                </div>
                ${ownerInfo}
                <div class="info-group">
                    ${addressContent}
                    <p class="info-item"><strong>음식:</strong> ${escapeHTML(r.food_type)}</p>
                </div>
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
            searchResults.style.display = 'none'; 
            return; 
        }
        try {
            const response = await fetch(`api/search_dong.php?term=${encodeURIComponent(searchTerm)}`);
            const locations = await response.json();
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
            searchResults.style.display = 'none';
            fetchRestaurants(dong);
        }
    }

    function handleFilterClick(e) {
        if (e.target.classList.contains('filter-btn')) {
            const filter = e.target.dataset.filter;
            filterButtonsContainer.querySelector('.active')?.classList.remove('active');
            e.target.classList.add('active');
            applyFilterAndRender(filter);
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
                if (result.success) { 
                    location.reload(); 
                } else { 
                    showToast(result.message, false); 
                }
            } catch (error) { 
                showToast('로그인 중 오류 발생', false); 
            }
        };
        loginSubmitBtn.addEventListener('click', loginAction);
        passwordInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') loginSubmitBtn.click(); });
        usernameInput.addEventListener('keyup', (e) => { if (e.key === 'Enter') loginSubmitBtn.click(); });
    }

    function handleCardActions(e) {
        const card = e.target.closest('.restaurant-card');
        if (!card) return;
        
        const id = card.dataset.id;
        const currentSearchTerm = searchInput.value || '모두';
        const isOwner = card.dataset.isOwner == 1;

        if (e.target.classList.contains('btn-toggle-jibun')) {
            const jibunP = card.querySelector('.jibun-address');
            if (jibunP) jibunP.classList.toggle('hidden');
            e.target.textContent = jibunP.classList.contains('hidden') ? '지번보기' : '숨기기';
            return;
        }

        // 사진보기 버튼 클릭 처리
        if (e.target.classList.contains('btn-view-photo')) {
            const imagePath = card.dataset.imagePath;
            if (imagePath) {
                openPhotoModal(imagePath);
            }
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
            if (isOwner) {
                deleteRestaurant(id);
            } else {
                unshareRestaurant(id);
            }
        }

        if (!isOwner) return;

        if (e.target.classList.contains('btn-share')) {
            const restaurantName = card.querySelector('h3').textContent;
            openShareModal(id, restaurantName);
            return;
        }
        if (e.target.classList.contains('btn-edit')) {
            const restaurantData = allRestaurants.find(r => r.id == id);
            const currentStarRating = parseFloat(card.dataset.starRating);
            card.querySelector('.info-group').innerHTML = `
                <p class="info-item"><strong>도로명:</strong><textarea class="address-edit-area">${restaurantData.address}</textarea></p>
                <p class="info-item"><strong>지번:</strong><textarea class="jibun-edit-area">${restaurantData.jibun_address || ''}</textarea></p>
                <p class="info-item"><strong>상세:</strong><textarea class="detail-edit-area">${restaurantData.detail_address || ''}</textarea></p>
                <p class="info-item"><strong>음식:</strong> ${card.dataset.foodType}</p>`;
            card.querySelector('.rating-content').innerHTML = `<strong>평가:</strong><textarea class="rating-edit-area">${restaurantData.rating}</textarea>`;
            const starDisplay = card.querySelector('.star-display');
            starDisplay.innerHTML = `
                <div class="star-rating-input">
                    <div class="star-input-group">
                        <div class="stars edit-mode">${[1,2,3,4,5].map(v => `<span class="star" data-value="${v}">★</span>`).join('')}</div>
                        <button type="button" class="btn-zero-star-edit">별 0개</button>
                    </div>
                    <input type="hidden" class="star-rating-edit-value" value="${currentStarRating}">
                </div>`;
            updateEditStars(starDisplay, currentStarRating);
            card.querySelector('.card-actions').innerHTML = `${favoriteBtn}<button class="btn-share">공유</button> <button class="btn-save-edit">저장</button><button class="btn-cancel-edit">취소</button>`;
        }
        if (e.target.classList.contains('btn-zero-star-edit') || (e.target.classList.contains('star') && e.target.closest('.edit-mode'))) {
            const starContainer = e.target.closest('.star-rating-input');
            const ratingInput = starContainer.querySelector('.star-rating-edit-value');
            let currentRating = parseFloat(ratingInput.value);
            if (e.target.classList.contains('btn-zero-star-edit')) {
                currentRating = 0.0;
            } else {
                const clickedValue = parseInt(e.target.dataset.value);
                currentRating = (currentRating === clickedValue - 0.5) ? clickedValue : clickedValue - 0.5;
            }
            ratingInput.value = currentRating;
            updateEditStars(starContainer, currentRating);
        }
        if (e.target.classList.contains('btn-cancel-edit')) fetchRestaurants(currentSearchTerm);
        if (e.target.classList.contains('btn-save-edit')) {
            const updatedData = {
                id: id,
                address: card.querySelector('.address-edit-area').value,
                jibun_address: card.querySelector('.jibun-edit-area').value,
                detail_address: card.querySelector('.detail-edit-area').value,
                rating: card.querySelector('.rating-edit-area').value,
                star_rating: card.querySelector('.star-rating-edit-value').value
            };
            updateRestaurant(updatedData);
        }
    }

    async function openShareModal(id, name) {
        shareRestaurantId.value = id;
        shareRestaurantName.textContent = name;
        shareUserList.innerHTML = '<p class="placeholder">사용자 목록을 불러오는 중...</p>';
        shareModal.classList.remove('hidden');

        try {
            const response = await fetch('api/get_users.php');
            const result = await response.json();
            if (result.success) {
                renderUserList(result.data);
            } else {
                shareUserList.innerHTML = `<p class="placeholder">${result.message}</p>`;
            }
        } catch (error) {
            shareUserList.innerHTML = `<p class="placeholder">사용자 목록 로딩 실패</p>`;
        }
    }
    
    function openPhotoModal(imagePath) {
        // 썸네일 경로에서 원본 경로를 유추합니다.
        const originalImagePath = imagePath.replace('/thumb/', '/');
        modalImage.src = 'images/' + originalImagePath.split('/').pop();
        photoModal.classList.remove('hidden');
    }

    function closePhotoModal() {
        photoModal.classList.add('hidden');
        modalImage.src = ''; // 이미지 소스 초기화
    }

    function renderUserList(users) {
        if (users.length === 0) {
            shareUserList.innerHTML = '<p class="placeholder">공유할 다른 사용자가 없습니다.</p>';
            return;
        }
        shareUserList.innerHTML = '';
        users.forEach(user => {
            const item = document.createElement('div');
            item.className = 'share-user-item';
            item.innerHTML = `
                <input type="checkbox" id="user-${user.id}" name="share_with_ids[]" value="${user.id}">
                <label for="user-${user.id}">${escapeHTML(user.username)}</label>
            `;
            shareUserList.appendChild(item);
        });
    }

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
            if(result.success) {
                closeShareModal();
            }
        } catch (error) {
            showToast('공유 중 오류가 발생했습니다.', false);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = '공유';
        }
    });

    closeShareModalBtn.addEventListener('click', closeShareModal);
    function closeShareModal() {
        shareModal.classList.add('hidden');
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

    async function updateRestaurant(data) {
        const formData = new FormData();
        for (const key in data) { formData.append(key, data[key]); }
        try {
            const response = await fetch('api/update_restaurant.php', { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.success);
            if (result.success) fetchRestaurants(searchInput.value || '모두');
        } catch (error) { console.error('Error updating:', error); }
    }
    
    function updateEditStars(container, rating) {
        const stars = container.querySelectorAll('.star');
        stars.forEach(star => {
            const starValue = parseInt(star.dataset.value);
            star.classList.remove('filled', 'half');
            if (rating >= starValue) star.classList.add('filled');
            else if (rating >= starValue - 0.5) star.classList.add('half');
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