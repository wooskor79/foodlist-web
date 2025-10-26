// 파일명: www/js/add.js (이 코드로 전체 교체)
document.addEventListener('DOMContentLoaded', () => {
    // --- 테마 관리 ---
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

    // --- 기본 요소 ---
    const restaurantForm = document.getElementById('restaurant-form');
    const starsContainer = document.querySelector('.stars');
    const starRatingInput = document.getElementById('star-rating-value');
    const zeroStarBtn = document.getElementById('zero-star-btn');
    const currentRatingSpan = document.querySelector('.current-star-rating');
    let currentRating = 0.0;

    const addressSearchBtn = document.getElementById('address-search-btn');
    const addressSearchInput = document.getElementById('address-search-input');
    const locationDongInput = document.getElementById('location_dong_input');
    const locationSiInput = document.getElementById('location-si-input');
    const locationGuInput = document.getElementById('location-gu-input');
    const locationRiInput = document.getElementById('location-ri-input'); 
    const addressResultsContainer = document.getElementById('address-results-container');
    const roadAddrSpan = document.getElementById('road-addr-result');
    const jibunAddrSpan = document.getElementById('jibun-addr-result');
    const jibunAddressInput = document.getElementById('jibun-address-input');
    const detailAddressContainer = document.getElementById('detail-address-container');
    const detailAddressInput = document.getElementById('detail-address-input');

    // --- 중복 확인 모달 요소 ---
    const duplicateModal = document.getElementById('duplicate-modal');
    const duplicateList = document.getElementById('duplicate-list');
    const forceSaveBtn = document.getElementById('force-save-btn');
    const closeModalBtn = document.getElementById('close-modal-btn');

    // --- 주소 검색 이벤트 ---
    addressSearchBtn.addEventListener('click', async () => {
        const keyword = addressSearchInput.value.trim();
        if (!keyword) {
            showToast('주소를 입력해주세요.', false);
            return;
        }

        const apiKey = 'devU01TX0FVVEgyMDI1MTAyNDE0MjA0MTExNjM1OTY=';
        const apiUrl = `https://www.juso.go.kr/addrlink/addrLinkApi.do?confmKey=${apiKey}&currentPage=1&countPerPage=5&keyword=${encodeURIComponent(keyword)}&resultType=json`;

        addressSearchBtn.disabled = true;
        addressSearchBtn.textContent = '검색중...';

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error('API 요청 실패');
            
            const data = await response.json();

            if (data.results && data.results.juso && data.results.juso.length > 0) {
                const firstResult = data.results.juso[0];
                
                locationSiInput.value = firstResult.siNm;
                locationGuInput.value = firstResult.sggNm;
                locationDongInput.value = firstResult.emdNm;
                locationRiInput.value = firstResult.liNm || '';

                roadAddrSpan.textContent = firstResult.roadAddr;
                jibunAddrSpan.textContent = firstResult.jibunAddr;
                jibunAddressInput.value = firstResult.jibunAddr;

                addressSearchInput.value = '';
                detailAddressContainer.classList.add('hidden');
                addressResultsContainer.classList.remove('hidden');
                showToast('주소 검색 완료! 아래 주소를 클릭하세요.', true);
            } else {
                showToast('검색된 주소가 없습니다. 다시 시도해주세요.', false);
                locationDongInput.value = '';
                locationSiInput.value = '';
                locationGuInput.value = '';
                locationRiInput.value = '';
                jibunAddressInput.value = '';
                addressResultsContainer.classList.add('hidden');
            }
        } catch (error) {
            console.error('Address search error:', error);
            showToast('주소 검색 중 오류가 발생했습니다.', false);
        } finally {
            addressSearchBtn.disabled = false;
            addressSearchBtn.textContent = '주소 검색';
        }
    });

    addressResultsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('address-selectable')) {
            addressSearchInput.value = e.target.textContent;
            detailAddressContainer.classList.remove('hidden');
            detailAddressInput.focus();
        }
    });

    // --- 별점 로직 ---
    starsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('star')) {
            const clickedValue = parseInt(e.target.dataset.value);
            currentRating = (currentRating === clickedValue - 0.5) ? clickedValue : clickedValue - 0.5;
            starRatingInput.value = currentRating;
            updateStars(currentRating);
        }
    });
    zeroStarBtn.addEventListener('click', () => {
        currentRating = 0.0;
        starRatingInput.value = currentRating;
        updateStars(currentRating);
    });
    function updateStars(rating) {
        const stars = starsContainer.querySelectorAll('.star');
        stars.forEach(star => {
            const starValue = parseInt(star.dataset.value);
            star.classList.remove('filled', 'half');
            if (rating >= starValue) star.classList.add('filled');
            else if (rating >= starValue - 0.5) star.classList.add('half');
        });
        if (currentRatingSpan) {
            currentRatingSpan.textContent = `${rating.toFixed(1)} / 5.0`;
        }
    }

    // --- 폼 제출 로직 ---
    restaurantForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(restaurantForm);
        if (!formData.get('address').trim()) {
            showToast('주소를 검색 후 선택해주세요.', false);
            return;
        }
        try {
            const checkResponse = await fetch('api/check_duplicate.php', { method: 'POST', body: formData });
            const checkResult = await checkResponse.json();
            if (checkResult.success && checkResult.duplicate) {
                displayDuplicates(checkResult.data);
            } else {
                saveRestaurant(formData);
            }
        } catch (error) {
            console.error('Error checking for duplicates:', error);
            showToast('중복 확인 중 오류가 발생했습니다.', false);
        }
    });

    // --- 중복 모달 관련 함수 ---
    function displayDuplicates(duplicates) {
        duplicateList.innerHTML = '';
        duplicates.forEach(dup => {
            const item = document.createElement('div');
            item.className = 'duplicate-item';
            const detail = dup.detail_address ? ` ${escapeHTML(dup.detail_address)}` : '';
            item.innerHTML = `
                <p><strong>가게:</strong> ${escapeHTML(dup.name)}</p>
                <p><strong>주소:</strong> ${escapeHTML(dup.address)}${escapeHTML(detail)}</p>
                <p><strong>종류:</strong> ${escapeHTML(dup.food_type)}</p>
            `;
            duplicateList.appendChild(item);
        });
        duplicateModal.classList.remove('hidden');
    }
    forceSaveBtn.addEventListener('click', () => {
        const formData = new FormData(restaurantForm);
        saveRestaurant(formData);
        closeModal();
    });
    closeModalBtn.addEventListener('click', closeModal);
    function closeModal() {
        duplicateModal.classList.add('hidden');
    }

    // --- 맛집 저장 함수 ---
    async function saveRestaurant(formData) {
        const submitButton = restaurantForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = '저장 중...';
        try {
            const response = await fetch('api/save_restaurant.php', { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.success);
            if (result.success) {
                setTimeout(() => { window.location.href = 'index.php'; }, 1500);
            }
        } catch (error) {
            console.error('Error saving restaurant:', error);
            showToast('맛집 정보 저장 중 오류가 발생했습니다.', false);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = '저장';
        }
    }
    
    // --- 기타 유틸리티 함수 ---
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

    // 💡 [수정] escapeHTML 함수 오류 수정
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return str.toString().replace(/[&<>"']/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[tag] || tag));
    }
});