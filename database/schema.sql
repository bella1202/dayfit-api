CREATE DATABASE IF NOT EXISTS dayfit
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

CREATE USER 'dayfit'@'localhost'
  IDENTIFIED BY 'dayfit123#';

GRANT ALL PRIVILEGES ON dayfit.* TO 'dayfit'@'localhost';
FLUSH PRIVILEGES;

USE dayfit;

-- users
CREATE TABLE users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE,
  password_hash VARCHAR(255),
  apple_sub VARCHAR(255) UNIQUE,
  provider ENUM('email','apple') NOT NULL,
  email_verified TINYINT(1) DEFAULT 0,
  nickname VARCHAR(50) NOT NULL,
  birthday DATE DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- email verification
CREATE TABLE email_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  code VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- access tokens (JWT 관리용 로그)
CREATE TABLE access_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- user locations 유저 위치(설정, 최근검색)
CREATE TABLE user_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,

  title VARCHAR(60) NOT NULL,
  subtitle VARCHAR(255) NOT NULL,

  lat DECIMAL(10,7) NOT NULL,
  lon DECIMAL(10,7) NOT NULL,

  place_key CHAR(40) NOT NULL,     -- sha1 같은 고정 길이
  is_primary TINYINT(1) NOT NULL DEFAULT 0,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  UNIQUE KEY uk_user_place (user_id, place_key),
  INDEX idx_user_updated (user_id, updated_at),
  INDEX idx_user_primary (user_id, is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- users history 로그인 히스토리 내역
CREATE TABLE users_history (
	id BIGINT auto_increment NOT NULL,
	user_id BIGINT NOT NULL,
	ip varchar(25) NULL,
	device TEXT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP  NOT NULL,
	CONSTRAINT users_history_pk PRIMARY KEY (id),
	CONSTRAINT users_history_users_FK FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
