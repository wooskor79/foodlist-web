// 파일명: www/js/add.js (이 코드로 전체 교체)
document.addEventListener('DOMContentLoaded', function () {
    // --- 기본 요소 ---
    const form = document.getElementById('add-restaurant-form');
    const searchAddressBtn = document.getElementById('search-address-btn');
    const addressSearchInput = document.getElementById('address-search');
    const roadAddressInput = document.getElementById('road-address');
    const jibunAddressInput = document.getElementById('jibun-address');
    const addressResultsContainer = document.getElementById('address-results-container');
    const addressResultsText = document.getElementById('address-results-text');
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const starsContainer = document.querySelector('.stars');
    const starRatingInput = document.getElementById('star-rating');
    const currentStarRatingSpan = document.querySelector('.current-star-rating');
    const zeroStarBtn = document.querySelector('.btn-zero-star');
    const duplicateModal = document.getElementById('duplicate-modal');
    const duplicateList = document.getElementById('duplicate-list');
    const forceAddBtn = document.getElementById('force-add-btn');
    const cancelAddBtn = document.getElementById('cancel-add-btn');

    // 사진 관련 요소
    const photoInput = document.getElementById('photo-input');
    const thumbnailPreview = document.getElementById('thumbnail-preview');
    const thumbnailImage = document.getElementById('thumbnail-image');
    const removePhotoBtn = document.getElementById('remove-photo-btn');

    let currentFormData = null;

    // 💡 [수정] 카카오맵 API가 로드된 후 주소 검색 객체를 초기화
    let geocoder;
    kakao.maps.load(function() {
        geocoder = new kakao.maps.services.Geocoder();
    });
    
    // --- 초기화 ---
    initializeTheme();

    // --- 이벤트 리스너 ---
    themeToggleBtn.addEventListener('click', toggleTheme);
    searchAddressBtn.addEventListener('click', searchAddress);
    addressSearchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') searchAddress();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        currentFormData = new FormData(form);
        checkDuplicateAndSave();
    });

    starsContainer.addEventListener('click', handleStarClick);
    zeroStarBtn.addEventListener('click', resetStars);

    forceAddBtn.addEventListener('click', () => {
        if (currentFormData) {
            saveRestaurant(currentFormData, true);
        }
        duplicateModal.classList.add('hidden');
    });
    cancelAddBtn.addEventListener('click', () => {
        duplicateModal.classList.add('hidden');
    });

    photoInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                thumbnailImage.src = e.target.result;
                thumbnailPreview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    });

    removePhotoBtn.addEventListener('click', function() {
        photoInput.value = '';
        thumbnailImage.src = '#';
        thumbnailPreview.classList.add('hidden');
    });

    // --- 함수 ---
    function initializeTheme() {
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggleBtn.textContent = '☀️';
            } else {
                themeToggleBtn.textContent = '🌙';
            }
        } catch (e) { console.error("테마 로딩 실패:", e); }
    }

    function toggleTheme() {
        try {
            document.body.classList.toggle('dark-mode');
            const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            themeToggleBtn.textContent = theme === 'dark' ? '☀️' : '🌙';
            localStorage.setItem('theme', theme);
        } catch (e) { console.error("테마 저장 실패:", e); }
    }
    
    function searchAddress() {
        // 💡 [수정] geocoder 객체가 로드되었는지 확인
        if (!geocoder) {
            showToast('지도 API가 아직 로딩 중입니다. 잠시 후 다시 시도해주세요.', false);
            return;
        }

        const query = addressSearchInput.value.trim();
        if (!query) {
            showToast('검색할 주소를 입력하세요.', false);
            return;
        }

        searchAddressBtn.disabled = true;
        searchAddressBtn.textContent = '검색중...';
        
        const callback = function(result, status) {
            searchAddressBtn.disabled = false;
            searchAddressBtn.textContent = '주소 검색';

            if (status === kakao.maps.services.Status.OK) {
                const addr = result[0];
                roadAddressInput.value = addr.road_address ? addr.road_address.address_name : '';
                jibunAddressInput.value = addr.address ? addr.address.address_name : '';

                addressResultsText.innerHTML = `<strong>도로명:</strong> ${roadAddressInput.value || '없음'}<br><strong>지번:</strong> ${jibunAddressInput.value || '없음'}`;
                addressResultsContainer.classList.remove('hidden');

                if (result.length > 1) {
                    addressResultsText.innerHTML += `<br><small>(${result.length}개의 결과 중 첫 번째 항목 선택됨)</small>`;
                }

            } else {
                showToast('검색 결과가 없습니다.', false);
                roadAddressInput.value = '';
                jibunAddressInput.value = '';
                addressResultsContainer.classList.add('hidden');
            }
        };

        geocoder.addressSearch(query, callback);
    }

    async function checkDuplicateAndSave() {
        const formData = new FormData(form);
        try {
            const response = await fetch('api/check_duplicate.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.is_duplicate) {
                let listHtml = '';
                result.duplicates.forEach(item => {
                    listHtml += `<div class="duplicate-item">
                                    <p><strong>가게명:</strong> ${escapeHTML(item.name)}</p>
                                    <p><strong>주소:</strong> ${escapeHTML(item.address)}</p>
                                 </div>`;
                });
                duplicateList.innerHTML = listHtml;
                duplicateModal.classList.remove('hidden');
            } else {
                saveRestaurant(formData, false);
            }
        } catch (error) {
            console.error('중복 확인 오류:', error);
            showToast('저장 중 오류가 발생했습니다.', false);
        }
    }

    async function saveRestaurant(formData, force = false) {
        if (force) {
            formData.append('force', 'true');
        }

        const saveBtn = form.querySelector('.btn-save');
        saveBtn.disabled = true;
        saveBtn.textContent = '저장 중...';

        try {
            const response = await fetch('api/save_restaurant.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, true);
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1000);
            } else {
                showToast(result.message, false);
            }
        } catch (error) {
            console.error('Error saving restaurant:', error);
            showToast('맛집을 저장하는 데 실패했습니다.', false);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = '저장';
        }
    }
    
    function handleStarClick(e) {
        if (e.target.classList.contains('star')) {
            const clickedValue = parseInt(e.target.dataset.value);
            const rect = e.target.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const starWidth = rect.width;
            const isHalf = clickX < starWidth / 2;

            let newRating;
            const currentRating = parseFloat(starRatingInput.value);

            if (isHalf) {
                newRating = clickedValue - 0.5;
            } else {
                newRating = clickedValue;
            }

            if (currentRating === newRating) {
                newRating = 0.0;
            }
            
            updateStars(newRating);
        }
    }
    
    function resetStars() {
        updateStars(0.0);
    }

    function updateStars(rating) {
        starRatingInput.value = rating.toFixed(1);
        currentStarRatingSpan.textContent = `${rating.toFixed(1)} / 5.0`;

        const allStars = starsContainer.querySelectorAll('.star');
        allStars.forEach(star => {
            const starValue = parseInt(star.dataset.value);
            star.classList.remove('filled', 'half');
            if (rating >= starValue) {
                star.classList.add('filled');
            } else if (rating >= starValue - 0.5) {
                star.classList.add('half');
            }
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
    
    function escapeHTML(str) {
        if (!str) return '';
        return str.toString().replace(/[&<>"']/g, function(tag) {
            const chars = { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' };
            return chars[tag] || tag;
        });
    }
});