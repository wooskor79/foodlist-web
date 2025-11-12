<?php
// 파일명: www/3second.php (폴더 내 이미지/영상 파일을 무작위로 찾아서 로드)

// 1. 스플래시 미디어 폴더 경로 설정 (웹 접근 경로)
$splash_folder = 'splash_assets'; 
// 2. 실제 서버 파일 시스템 경로 설정
$splash_dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $splash_folder . '/';

$media_file = null;
$media_files = []; // 무작위 선택을 위해 모든 유효한 파일명을 저장할 배열
$is_video = false;

// 폴더가 존재하고 읽을 수 있는 경우에만 파일을 검색
if (is_dir($splash_dir)) {
    $files = scandir($splash_dir);
    
    // 유효한 미디어 파일만 목록에 추가합니다.
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // 💡 [수정] MP4 확장자 추가
            if (in_array($extension, ['jpg', 'png', 'jpeg', 'gif', 'webp', 'mp4'])) {
                $media_files[] = $file;
            }
        }
    }
}

if (!empty($media_files)) {
    // 이미지/영상 파일 목록에서 무작위로 하나의 키를 선택합니다.
    $random_key = array_rand($media_files);
    $media_file = $media_files[$random_key];
    
    // 💡 [추가] 선택된 파일이 영상인지 확인
    $extension = strtolower(pathinfo($media_file, PATHINFO_EXTENSION));
    if ($extension === 'mp4') {
        $is_video = true;
    }
} else {
    // 폴더에 미디어가 없을 경우, 기본 아이콘을 대체 이미지로 사용합니다.
    $media_file = 'app_icon.png';
}

// 최종 미디어 경로 결정
$splash_media_path = $media_file ? $splash_folder . '/' . $media_file : 'app_icon.png';

$css_version = filemtime('css/3second.css') ?? time(); 
?>
<link rel="stylesheet" href="css/3second.css?v=<?php echo $css_version; ?>">

    <div id="splash-screen">
        <div class="splash-image-container">
            <?php if ($is_video): ?>
                <video id="splash-media" src="<?php echo $splash_media_path; ?>" autoplay loop muted playsinline alt="로딩 영상 (랜덤 선택)"></video>
            <?php else: ?>
                <img id="splash-media" src="<?php echo $splash_media_path; ?>" alt="로딩 이미지 (랜덤 선택)">
            <?php endif; ?>
        </div>
        <p class="loading-text">광고는 봐야지?...</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const splashScreen = document.getElementById('splash-screen');
            // 3000ms (3초) 후 스플래시 화면을 숨깁니다.
            // (이미지든 영상이든 동일하게 3초 딜레이 적용)
            setTimeout(() => {
                if (splashScreen) {
                    splashScreen.classList.add('hidden');
                }
            }, 3000); 
            
            // body에 강제로 적용했던 dark-mode-loading 클래스를 제거합니다.
            document.documentElement.classList.remove('dark-mode-loading');
        });
    </script>
<?php
// 주의: 이 파일은 index.php에서 include될 때 HTML head와 body 사이의 적절한 위치에 포함되어야 합니다.
?>