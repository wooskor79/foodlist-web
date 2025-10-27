-- 1. 사용할 데이터베이스를 'tasty_list'로 지정합니다.
USE `tasty_list`;

-- 2. 기존 테이블들을 모두 삭제합니다. (경고: 모든 데이터가 사라집니다!)
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `restaurant_shares`;
DROP TABLE IF EXISTS `user_favorites`;

-- 3. 새로운 users 테이블을 생성합니다. (다중 사용자용)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 정보 테이블';

-- 4. 새로운 restaurants 테이블을 생성합니다. (is_favorite 컬럼 없음)
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
  `location_dong` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_si` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '시/도',
  `location_gu` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '시/군/구',
  `location_ri` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '법정리',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='맛집 리스트 테이블';

-- 5. restaurant_shares 테이블을 생성합니다. (맛집 공유용)
CREATE TABLE `restaurant_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(11) NOT NULL COMMENT '공유된 맛집 ID',
  `owner_user_id` int(11) NOT NULL COMMENT '맛집을 공유한 사용자 ID',
  `shared_with_user_id` int(11) NOT NULL COMMENT '맛집을 공유받은 사용자 ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_share` (`restaurant_id`,`shared_with_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='맛집 공유 정보 테이블';

-- 6. user_favorites 테이블을 생성합니다. (사용자별 즐겨찾기용)
CREATE TABLE `user_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '사용자 ID',
  `restaurant_id` int(11) NOT NULL COMMENT '맛집 ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_favorite_unique` (`user_id`,`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자별 즐겨찾기 테이블';

-- 이미지
ALTER TABLE `restaurants`
ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `star_rating`;

-- 7. 최초 접속을 위한 테스트용 계정을 추가합니다. (ID: test / PW: 1234)
INSERT INTO `users` (`username`, `password_hash`) VALUES
('test', '$2y$10$ifz.f.2m5n5.n5y4GzX.W.a.R.E.e.W.c.U.t.H.o.L.i.s');