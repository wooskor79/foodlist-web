// 파일명: www/js/add.js (다중 파일 선택, 썸네일 미리보기 로직으로 수정)
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
    
    // 💡 [수정] 다중 파일 관련 요소
    const photoInputMultiple = document.getElementById('photo-input-multiple'); // 다중 파일 입력 필드
    const multipleThumbnailPreview = document.getElementById('multiple-thumbnail-preview'); // 썸네일 컨테이너
    const removeAllPhotosBtn = document.getElementById('remove-all-photos-btn'); // 모든 사진 제거 버튼

    let currentFormData = null;
    let geocoder;
    let selectedFiles = []; // 선택된 파일들을 저장할 배열 (핵심)

    // 카카오 맵 로딩 확인
    if (typeof kakao !== 'undefined' && kakao.maps) {
        kakao.maps.load(function() {
            geocoder = new kakao.maps.services.Geocoder();
        });
    }
    
    initializeTheme();

    // --- 이벤트 리스너 ---
    themeToggleBtn.addEventListener('click', toggleTheme);
    searchAddressBtn.addEventListener('click', searchAddress);
    addressSearchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') searchAddress();
    });
    
    // 💡 [수정] 폼 제출 시 FormData에 selectedFiles 추가
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        currentFormData = new FormData(form);
        
        // selectedFiles의 모든 파일을 'photos[]' 이름으로 FormData에 추가
        selectedFiles.forEach(file => {
            currentFormData.append('photos[]', file);
        });
        
        checkDuplicateAndSave();
    });

    starsContainer.addEventListener('click', handleStarClick);
    zeroStarBtn.addEventListener('click', resetStars);
    
    const forceAddBtn = document.getElementById('force-add-btn');
    const cancelAddBtn = document.getElementById('cancel-add-btn');
    if (forceAddBtn) {
        forceAddBtn.addEventListener('click', () => {
            if (currentFormData) {
                // 'force' 플래그를 추가하고 저장
                currentFormData.append('force', 'true');
                saveRestaurant(currentFormData);
            }
            duplicateModal.classList.add('hidden');
        });
    }
    if (cancelAddBtn) {
        cancelAddBtn.addEventListener('click', () => {
            duplicateModal.classList.add('hidden');
        });
    }

    // 💡 [수정] 다중 파일 입력 필드 변경 시 로직
    if (photoInputMultiple) {
        photoInputMultiple.addEventListener('change', handlePhotoInputChange);
    }
    
    // 💡 [추가] 모든 사진 제거 버튼 이벤트 리스너
    if (removeAllPhotosBtn) {
        removeAllPhotosBtn.addEventListener('click', removeAllPhotos);
    }

    // --- 함수 ---

    // 💡 [수정] 다중 파일 입력 처리 함수: 기존 파일에 추가 (덮어쓰기 방지)
    function handlePhotoInputChange(event) {
        const newFiles = Array.from(event.target.files);
        
        // 새로 선택된 파일들을 기존 파일 목록에 추가
        selectedFiles = selectedFiles.concat(newFiles);
        
        // 5장 초과 검증 및 조정
        if (selectedFiles.length > 5) {
            showToast(`사진은 최대 5장까지만 등록할 수 있습니다. ${selectedFiles.length - 5}장이 제외되었습니다.`, false);
            // 5장까지만 유지
            selectedFiles = selectedFiles.slice(0, 5);
        }

        renderThumbnails(selectedFiles);
        
        // input[type=file] 자체를 초기화하여, 같은 파일을 다시 선택해도 change 이벤트가 발생하도록 함
        event.target.value = '';
    }

    // 💡 [추가] 썸네일 렌더링 함수
    function renderThumbnails(files) {
        multipleThumbnailPreview.innerHTML = '';
        if (files.length === 0) {
            removeAllPhotosBtn.classList.add('hidden');
            return;
        }

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const thumbWrapper = document.createElement('div');
                thumbWrapper.className = 'thumbnail-wrapper';
                thumbWrapper.style.position = 'relative';
                thumbWrapper.style.maxWidth = '100px';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = `선택한 이미지 미리보기 ${index + 1}`;
                img.style.display = 'block';
                img.style.width = '100%';
                img.style.height = 'auto';
                img.style.borderRadius = 'var(--border-radius)';
                img.style.border = '1px solid var(--card-border-color)';
                img.style.maxHeight = '100px';
                img.style.objectFit = 'cover';

                // 개별 제거 버튼 추가
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-single-photo-btn';
                removeBtn.textContent = '×';
                removeBtn.dataset.index = index;
                removeBtn.style.position = 'absolute';
                removeBtn.style.top = '-10px';
                removeBtn.style.right = '-10px';
                removeBtn.style.backgroundColor = 'var(--danger-color)';
                removeBtn.style.color = 'white';
                removeBtn.style.border = 'none';
                removeBtn.style.borderRadius = '50%';
                removeBtn.style.width = '24px';
                removeBtn.style.height = '24px';
                removeBtn.style.fontSize = '1.2rem';
                removeBtn.style.lineHeight = '22px';
                removeBtn.style.textAlign = 'center';
                removeBtn.style.cursor = 'pointer';
                removeBtn.style.fontWeight = 'bold';
                
                removeBtn.addEventListener('click', function() {
                    removeSinglePhoto(parseInt(this.dataset.index));
                });


                thumbWrapper.appendChild(img);
                thumbWrapper.appendChild(removeBtn);
                multipleThumbnailPreview.appendChild(thumbWrapper);
            }
            reader.readAsDataURL(file);
        });

        // 파일 선택 후에는 '모든 사진 제거' 버튼 표시
        removeAllPhotosBtn.classList.remove('hidden');
    }

    // 💡 [추가] 개별 사진 제거 함수
    function removeSinglePhoto(indexToRemove) {
        if (indexToRemove >= 0 && indexToRemove < selectedFiles.length) {
            // 해당 인덱스의 파일 제거
            selectedFiles.splice(indexToRemove, 1); 
            // 썸네일 새로고침
            renderThumbnails(selectedFiles);
            showToast(`사진 ${indexToRemove + 1}이(가) 제거되었습니다.`, true);
        }
    }
    
    // 💡 [추가] 모든 사진 제거 함수
    function removeAllPhotos() {
        // input[type=file] 초기화 (이전 로직에서는 이 코드가 전체 파일 목록을 덮어쓰는 문제를 야기할 수 있었지만,
        // 현재는 selectedFiles 배열로 상태를 관리하므로, input 초기화는 필요 없습니다. 하지만 코드를 깨끗하게 유지하기 위해 남겨둡니다.)
        if (photoInputMultiple) {
            photoInputMultiple.value = '';
        }
        selectedFiles = []; // 선택된 파일 배열 초기화
        multipleThumbnailPreview.innerHTML = ''; // 썸네일 제거
        removeAllPhotosBtn.classList.add('hidden'); // 버튼 숨기기
        showToast('모든 사진이 제거되었습니다.', true);
    }


    function initializeTheme() {
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggleBtn.textContent = '☀️';
            } else {
                document.body.classList.remove('dark-mode');
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
        // currentFormData는 이미 submit 이벤트에서 파일과 함께 구성됨
        try {
            // 중복 확인은 파일 없이 진행
            const checkData = new FormData();
            checkData.append('name', currentFormData.get('name'));
            checkData.append('address', currentFormData.get('address'));
            checkData.append('jibun_address', currentFormData.get('jibun_address'));
            checkData.append('detail_address', currentFormData.get('detail_address'));
            
            const response = await fetch('api/check_duplicate.php', {
                method: 'POST',
                body: checkData
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
                saveRestaurant(currentFormData);
            }
        } catch (error) {
            console.error('중복 확인 오류:', error);
            showToast('저장 중 오류가 발생했습니다.', false);
        }
    }

    async function saveRestaurant(formData) {
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
            let newRating = isHalf ? clickedValue - 0.5 : clickedValue;
            const currentRating = parseFloat(starRatingInput.value);
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
        if (str === null || str === undefined) {
            return ''; // str이 null이거나 비어있으면 빈 문자열을 반환
        }
        return str.toString().replace(/[&<>"']/g, function(tag) {
            const chars = { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' };
            return chars[tag] || tag;
        });
    }
});