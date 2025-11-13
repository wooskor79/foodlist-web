-- 1. 데이터베이스가 없으면 새로 생성합니다. (utf8mb4_unicode_ci 권장)
CREATE DATABASE IF NOT EXISTS `tasty_list`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- 2. 사용할 데이터베이스를 'tasty_list'로 지정합니다.
USE `tasty_list`;

-- 3. 기존 테이블들을 모두 삭제합니다. (경고: 모든 데이터가 사라집니다!)
-- 순서가 중요합니다. 외래 키(Foreign Key) 제약 조건 때문에 참조하는 테이블부터 먼저 삭제합니다.
SET FOREIGN_KEY_CHECKS = 0; -- 외래 키 제약 조건 임시 비활성화
DROP TABLE IF EXISTS `user_favorites`;
DROP TABLE IF EXISTS `restaurant_shares`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1; -- 외래 키 제약 조건 다시 활성화


-- 4. 새로운 users 테이블을 생성합니다. (다중 사용자용)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 정보 테이블';


-- 5. 새로운 restaurants 테이블을 생성합니다. (image_path1 ~ 5 추가)
CREATE TABLE `restaurants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '맛집을 등록한 사용자 ID',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '도로명 주소',
  `jibun_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '지번 주소',
  `detail_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '상세 주소',
  `food_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` text COLLATE utf8mb4_unicode_ci,
  `star_rating` decimal(2,1) NOT NULL DEFAULT '0.0' COMMENT '별점 (0.0 ~ 5.0)',
  -- 💡 [수정] 단일 image_path 대신 최대 5개의 경로를 저장합니다.
  `image_path1` VARCHAR(255) NULL DEFAULT NULL COMMENT '사진 경로 1',
  `image_path2` VARCHAR(255) NULL DEFAULT NULL COMMENT '사진 경로 2',
  `image_path3` VARCHAR(255) NULL DEFAULT NULL COMMENT '사진 경로 3',
  `image_path4` VARCHAR(255) NULL DEFAULT NULL COMMENT '사진 경로 4',
  `image_path5` VARCHAR(255) NULL DEFAULT NULL COMMENT '사진 경로 5',
  `location_dong` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_si` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '시/도',
  `location_gu` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '시/군/구',
  `location_ri` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '법정리',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_restaurants_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='맛집 리스트 테이블';


-- 6. restaurant_shares 테이블을 생성합니다. (맛집 공유용)
CREATE TABLE `restaurant_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(11) NOT NULL COMMENT '공유된 맛집 ID',
  `owner_user_id` int(11) NOT NULL COMMENT '맛집을 공유한 사용자 ID',
  `shared_with_user_id` int(11) NOT NULL COMMENT '맛집을 공유받은 사용자 ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_share` (`restaurant_id`,`shared_with_user_id`),
  CONSTRAINT `fk_shares_restaurant_id` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shares_owner_id` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shares_shared_id` FOREIGN KEY (`shared_with_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='맛집 공유 정보 테이블';


-- 7. user_favorites 테이블을 생성합니다. (사용자별 즐겨찾기용)
CREATE TABLE `user_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '사용자 ID',
  `restaurant_id` int(11) NOT NULL COMMENT '맛집 ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_favorite_unique` (`user_id`,`restaurant_id`),
  CONSTRAINT `fk_favorites_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_restaurant_id` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자별 즐겨찾기 테이블';


-- 8. [제거됨] restaurants 테이블에 이미지 경로 컬럼을 추가하는 ALTER 문 대신 CREATE TABLE에 반영되었습니다.


-- 9. 최초 접속을 위한 테스트용 계정을 추가합니다. (ID: test / PW: 1234)
-- 참고: '$2y$10$ifz.f.2m5n5.n5y4GzX.W.a.R.E.e.W.c.U.t.H.o.L.i.s'는 '1234'의 bcrypt 해시값이 아닙니다.
-- 실제 bcrypt 해시값을 사용하시거나, 개발 환경에 맞게 조정하세요.
-- **테스트용 계정 정보**
--   - username: 'test'
--   - password_hash: '$2y$10$ifz.f.2m5n5.n5y4GzX.W.a.R.E.e.W.c.U.t.H.o.L.i.s' (예시 해시값)
INSERT INTO `users` (`username`, `password_hash`) VALUES
('test', '$2y$10$ifz.f.2m5n5.n5y4GzX.W.a.R.E.e.W.c.U.t.H.o.L.i.s');