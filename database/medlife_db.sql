-- ============================================================
--  БД веб-приложения медицинского центра «МедЛайф» (версия 3)
--  3НФ. Персональные и медицинские данные хранятся в
--  зашифрованном виде (поля _enc, AES-256-CBC, includes/crypto.php).
--  Импорт: phpMyAdmin -> вкладка «Импорт» (или «SQL» -> вставить текст)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `medlife`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `medlife`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `medical_records`;
DROP TABLE IF EXISTS `hospitalizations`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `icd10`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `room_categories`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `specialties`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- Учётные записи (вход в систему)
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'хеш password_hash()',
  `role`       ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Пациенты (учёт пациентов). ФИО и телефон зашифрованы.
CREATE TABLE `patients` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `card_number`   VARCHAR(20)  NOT NULL COMMENT 'номер карты, псевдоним пациента',
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `full_name_enc` VARCHAR(512) NOT NULL,
  `phone_enc`     VARCHAR(512) NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_card` (`card_number`),
  UNIQUE KEY `uq_patients_user` (`user_id`),
  CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Специализации врачей
CREATE TABLE `specialties` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_specialties_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Врачи (удаление в админ-панели = is_active 0, чтобы сохранялась история)
CREATE TABLE `doctors` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150) NOT NULL,
  `specialty_id` INT UNSIGNED NOT NULL,
  `experience`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `rating`       DECIMAL(2,1) NOT NULL DEFAULT 4.5,
  `photo`        VARCHAR(255) DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doctors_specialty` (`specialty_id`),
  CONSTRAINT `fk_doctors_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Категории палат (цена за сутки)
CREATE TABLE `room_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  `photo`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Палаты (отдельные номера)
CREATE TABLE `rooms` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`      VARCHAR(10)  NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `status`      ENUM('free','occupied') NOT NULL DEFAULT 'free',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_number` (`number`),
  KEY `idx_rooms_category` (`category_id`),
  CONSTRAINT `fk_rooms_category` FOREIGN KEY (`category_id`) REFERENCES `room_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Справочник диагнозов МКБ-10
CREATE TABLE `icd10` (
  `code`  VARCHAR(10)  NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Записи на приём. Статусы: pending, confirmed, rejected, completed (приём состоялся),
-- hospitalized (приём состоялся, пациент госпитализирован)
CREATE TABLE `appointments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`       INT UNSIGNED NOT NULL,
  `doctor_id`        INT UNSIGNED NOT NULL,
  `appointment_date` DATE NOT NULL,
  `complaints_enc`   TEXT DEFAULT NULL,
  `status`           ENUM('pending','confirmed','rejected','completed','hospitalized') NOT NULL DEFAULT 'pending',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_patient` (`patient_id`),
  KEY `idx_app_doctor` (`doctor_id`),
  KEY `idx_app_status_date` (`status`,`appointment_date`),
  CONSTRAINT `fk_app_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_app_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`  (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- История болезни: диагноз кодируется по МКБ-10, пояснения и лечение зашифрованы
CREATE TABLE `medical_records` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id`     INT UNSIGNED NOT NULL,
  `icd_code`           VARCHAR(10) NOT NULL,
  `diagnosis_note_enc` TEXT DEFAULT NULL,
  `treatment_enc`      TEXT DEFAULT NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mr_appointment` (`appointment_id`),
  KEY `idx_mr_icd` (`icd_code`),
  CONSTRAINT `fk_mr_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mr_icd`         FOREIGN KEY (`icd_code`)       REFERENCES `icd10` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Госпитализации оформляет только администратор. Статусы: planned, active, completed, cancelled.
-- daily_price и service_fee фиксируются при оформлении, total_cost вычисляется автоматически.
CREATE TABLE `hospitalizations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`     INT UNSIGNED NOT NULL,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `room_id`        INT UNSIGNED NOT NULL,
  `admission_date` DATE NOT NULL,
  `discharge_date` DATE NOT NULL,
  `daily_price`    DECIMAL(10,2) NOT NULL,
  `service_fee`    DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `total_cost`     DECIMAL(10,2) AS (DATEDIFF(`discharge_date`, `admission_date`) * `daily_price` + `service_fee`) STORED,
  `status`         ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hosp_appointment` (`appointment_id`),
  KEY `idx_hosp_patient` (`patient_id`),
  KEY `idx_hosp_room` (`room_id`),
  CONSTRAINT `chk_hosp_dates` CHECK (`discharge_date` > `admission_date`),
  CONSTRAINT `fk_hosp_patient`     FOREIGN KEY (`patient_id`)     REFERENCES `patients`     (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hosp_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hosp_room`        FOREIGN KEY (`room_id`)        REFERENCES `rooms`        (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hosp_admin`       FOREIGN KEY (`created_by`)     REFERENCES `users`        (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ТЕСТОВЫЕ ДАННЫЕ
--  Входы: admin@medlife.ru / admin123, ivanov@example.com / user123,
--         petrova@example.com / user123
-- ============================================================

INSERT INTO `users` (`id`,`email`,`password`,`role`) VALUES
(1,'admin@medlife.ru','$2y$12$WsopwvwggCovweglMRnZA.Xi3Gmzzp0aGb4LsDo2JgkRwyqm8mg32','admin'),
(2,'ivanov@example.com','$2y$12$uByDD/7ff04qqqu8syBt0.RYZsawi4SLzmP97P8wfDW2CemYFS5Yq','user'),
(3,'petrova@example.com','$2y$12$uByDD/7ff04qqqu8syBt0.RYZsawi4SLzmP97P8wfDW2CemYFS5Yq','user');
INSERT INTO `patients` (`id`,`card_number`,`user_id`,`full_name_enc`,`phone_enc`) VALUES
(1,'ML-000001',2,'phPtSmZxPsk2kuIDY1iMkJDZUzsKOh9ZaVOsPduj9UkTbv9pT/QlbC3a6z98H5NgQZdEK8DPXZeMr0lzDb8/KA==','YSAbtKEnVf/RmUV2/weojfwB95Jgyudo1ap6Tka9+wFNC9DskRhCmp74Fw2zjH7v'),
(2,'ML-000002',3,'hdS/AMntW5j0fW1ja1pcjblCql2wFb+ipKLN0OGv6kGkIXmm7aj3cRKvu0p/EI8hAcNqbqUnxnaLG2qdWxwOnw==','HmZmlypSzEzUfDuxYAfub1NaMJJaym9FUrLxtB7WE9raV1ilAGH/3KhJ79E9apX0'),
(3,'ML-000003',NULL,'FMmVVHZBhdL+XyG44p0ju1fKM9iSkfIJwY3agpPKuCmRlO3+RXmdxWI7zSomXZODw9pyjow/BA8IxgZaN1vs7Q==','HmYNALMqzfWkT+7N+kLIoAPdALmjTWBXq1pbhVrdFbsQMR1r0zzS6E0k01FECd20'),
(4,'ML-000004',NULL,'k6LWYTeJZRmMIlpJSKTMYkOHOPwGSi2h8qd9q8z0/q1yjAuIoXUQL1ubzv6xWkZZ+xsqX2k8C6URE7YufRJupA==','OqsOFl4LQ7XtplowtD4N0GM4jOCrz+jmhxMc5hkR83bQV3Gj+Qx68Bu6jOnS77Cc'),
(5,'ML-000005',NULL,'mH8IiFvCoZ0pibN8B1fL9PqJQYbHBvxcNDHr77eRBzdlEdc5/AhYUOJS8LXhusTMFr7G1rf5DXspcEpPMgYIVQ==','V+uKjPo2UV9CipT3ZtQDDJMTuCu3p+YjXqYGI6aN3XdRTV/f/S6SU+jW0JlShppW'),
(6,'ML-000006',NULL,'MK31A5nkVRtpa6y9aYaAQoR8DQWhfhKNfx14Jzyovl0dtbp349GO25SeZGsH6O5CI5qxe6RlU5NoLEZvJo03FQ==','673BRgAq0K6F40skAn8JCY6B1C4ZcBiGNcxeQZxjRcw90pAlZuj+V7SGgto7BcZs');

INSERT INTO `specialties` (`id`,`name`) VALUES
(1,'Терапевт'),(2,'Кардиолог'),(3,'Хирург'),(4,'Невролог'),(5,'Офтальмолог'),(6,'Эндокринолог');

INSERT INTO `doctors` (`id`,`name`,`specialty_id`,`experience`,`rating`) VALUES
(1,'Иванова Елена Сергеевна',1,12,4.9),
(2,'Попов Алексей Игоревич',2,18,4.8),
(3,'Смирнов Дмитрий Андреевич',3,22,4.7),
(4,'Кузнецова Мария Павловна',4,8,4.6),
(5,'Орлов Сергей Викторович',5,5,4.5),
(6,'Белова Анна Николаевна',6,15,4.9);

INSERT INTO `room_categories` (`id`,`name`,`description`,`price`,`photo`) VALUES
(1,'Стандарт','2-местная палата, санузел на этаже, круглосуточный пост',5500.00,'assets/images/room-standard.jpg'),
(2,'Комфорт','1-местная палата, санузел, телевизор, холодильник',8500.00,'assets/images/room-comfort.jpg'),
(3,'Люкс','1-местная палата с зоной для посетителей и отдельным санузлом',14000.00,'assets/images/room-lux.jpg');

-- 12 палат: свободно 7 (101, 103, 201, 203, 204, 302, 303), занято 5 (102, 104, 202, 205, 301)
INSERT INTO `rooms` (`id`,`number`,`category_id`,`status`) VALUES
(1,'101',1,'free'),(2,'102',1,'occupied'),(3,'103',1,'free'),(4,'104',1,'occupied'),
(5,'201',2,'free'),(6,'202',2,'occupied'),(7,'203',2,'free'),(8,'204',2,'free'),(9,'205',2,'occupied'),
(10,'301',3,'occupied'),(11,'302',3,'free'),(12,'303',3,'free');

INSERT INTO `icd10` (`code`,`title`) VALUES
('E11.9','Сахарный диабет 2 типа без осложнений'),
('G43.9','Мигрень неуточнённая'),
('H52.1','Миопия'),
('I10','Эссенциальная (первичная) гипертензия'),
('J06.9','Острая инфекция верхних дыхательных путей неуточнённая'),
('K35.8','Острый аппендицит другой и неуточнённый'),
('M17.9','Гонартроз неуточнённый'),
('R51','Головная боль');
INSERT INTO `appointments` (`id`,`patient_id`,`doctor_id`,`appointment_date`,`complaints_enc`,`status`) VALUES
(1,1,1,'2026-09-10','nBlCyBRozberlWDKeW7WpnlnH/gromlJjmT8J9ejisgUosTPVz2g1XpGEHpcIsMpGnpCYPsIweAEurCb3fUrk/+SHRknqEqHTGW5CCacu54=','completed'),
(2,1,2,'2026-09-22','fSnHVmC5AInLfSc+7XROwGZ5TJRe4Cw83SPEwtiQ4T6N4O1jIfvVe5J3NAHewg9GdYrGZE6ryq1pv7UX7j27KM7o32aSTLlZ3IWqYKrNXEvLNXgmMDarQJAZyl+ClGU1','hospitalized'),
(3,2,3,'2026-09-15','nJKIobntvYtARtL3OcXgdMm4TQrygA/oehwYSCe+FM5cRmGXAVmeJMPyldaj+pjO0PeihK1HCMO3LHAhtagF0g==','hospitalized'),
(4,2,1,'2026-10-02','tpxhNMxRpPTFDaF0hlDzEkzAJ25vtQ3bc0hUDVPKXiGDaODJ1l+pk5QXy50n5oDEj5MFPO15XlkX03oTfFERsQ==','completed'),
(5,3,4,'2026-10-08','cbncbf/tq398CNBcuqsd0DNttPtfLJIxCiYKK2no4emOStUfOYVYWV/4ugKTtl+Md2SrjTyulk6d752RmR/rYQ==','completed'),
(6,3,6,'2026-10-12','ojnWLOD5xH25eOTi+dgdUhWDMcepWDRZlyJBr8EUxsKou16q8/CW0nTff/6VdxLuYPFA4CMl8jugjqpHtTw6iw==','completed'),
(7,1,1,'2026-11-20','pXY4+o8YuWj/6kyAWTFMmI1Aokf8cjNLqs6DiVKNQlWVQVqS7oXR+0GA+0nAQp1duisSz7ze2ThIu08k1BdnTQ==','confirmed'),
(8,1,2,'2026-11-25','/+H7/n7tsl0lLo63I8gPRHsnRSSFD0huKMr6ozitPcNYM45pQ9V7attgM8ZYrmIZ','pending'),
(9,1,3,'2026-10-05','aPGfQQxAepsGZzJAEOEcCbdZ83V2Bxh3TXu+f/6/dbHOCahG+nEGzTKQtRl8SSq1','rejected'),
(10,4,2,'2026-10-20','aYx8eOdKcIdBXcxUT1odiJeicnvnM2XsBVzX/V2CL7c5b6i3/L0+oCOXSo0KQKMtxrYJskJQz7ua7a3yq8UCXA==','hospitalized'),
(11,6,3,'2026-10-24','FxRUZ9v2qwTfobZvDjhuHayI96yweJRGrwMskvlHFUObZVcs86htrokI2gnPJngY','hospitalized'),
(12,5,5,'2026-11-02','H+ezaO+rJPNsGsM6aHgSSgxf0Qdh2aXQPmucbIHIxx1T5e+0ysTlhBs3dp3ZN8dXSjt5m+NCFJRgvD4IJXb1qg==','pending');

INSERT INTO `medical_records` (`appointment_id`,`icd_code`,`diagnosis_note_enc`,`treatment_enc`) VALUES
(1,'J06.9','q0HfDB4VV0KAOrGTJr69PnOHx+ePccuVlK9mqS9QrhMzlU9tvQRKyGVvL/g8ga7GEs9thx3YyGJhDR/nCrHkmJ9kX7ydl6oAEL3NnByotVlQKj47jNiD6z4SvVh3SRtJZO46fNUoETV2DeuG6A1oiA==','Ero0+cbqfNQySR4FrDDnXBfrix2YfQOX1cBZEFez+V1Xmv97VdO/abjZ6w7aU3/J0gTFyEjwzd8Yscco8tiATbSW5E1qCAtXg5nR1aVqBpVOcOgE+ZZ3QfU5HmDWbtzikt6Yptx7T6X+TnI01mb5aZlnirGi7YzZSPG+7O5HJezPE2yJjJA0vdE/cpXX6R40jfsjri3UcN7LwmYgruxeiQ=='),
(2,'I10','3Ujkutj2x6VNZH7NrXdDDEBzqM+l8VWyNhff8pvhnlkN1D14adIlgRMyNZUc8z4YToc71z9THZPdCEzorVa4RpizrxnKrSA01zR84sLF79ODKlfUmpY7lMrOpaBJb1UmgyIJWjIq5FYXblxOtsuUzqPBzEcx1aSHXPqP/nILDiRD+ODBsJyNlnX16nyGVn2x','8GsetQkpDjzeGibpzHu8FKalSEqJlb+yoyRtWDDCnWM6r4Zp9zNqRgJDKFrIASV1IReHhilZNjP0xLYvUvvV+QjpJ2sGDlNRjJfH5I6Za2LpIWn04ncK4JrqIO+t9GpF95DeDNsajT6qJZiu9g5rnIih2FE/uvs1tEwnWyZnhWo='),
(3,'K35.8','tYHqxkb9NWNdhgB3e+i4fnkFeTtCz1lQIQAVHCYrtj32zYdzN6IlD2La8iz1EP32BUBhFRXIljiP//DZr6NwbYQVRVppJAVBij0bTfbl1n7lJZ35xeDWbue2nc+pguQh5VO1DURyXIk60Siiqqt9cdabyC1L7LPKDy3Ra6D7JZ8=','XsVeLXMWYjWEU4Y7mGpTgxKAh0gqfk5ti8t0ABwJl4M3sMlinJlQyVP1Ww7Nk8mI+MM7/S2u4yyKfvrETgzBl+VUgK+TJsZwtbMlKuVoY10WRLIfBCSAQGw6S+Cd85OlxdEnpw0Y74nC0oZus9hyRys6QzTZrvczLZDawV8yj6U='),
(4,'J06.9','BfBjDb6A2hfdm6szq7sQjR0kfl7V6LZUrB0+wTlUD94WBYSYqDIhX9Ewil3X5DKaclnFhOa9RUvTufvXt2KPLg==','Y/TBJDpCu4pqBa+2sLhU2Lyd+db9mWeEOm9SKRcMKAGaC8KIbnnD14foJ3mV4fXhdRHYzVelQ9mbP65vJIkQKfRG+hpRqkrvXGdBT8CLyJbAHJa20s5KG27U1B1qD6AX'),
(5,'G43.9','ABeJhL44jbfTqoFLxk+QnUNyegBnbE7W3GCSklqLl4L/8K6ccAqpruhIhEsJMAND','uzXfH02GZehr6HRmRmyw+L58vhjRI6PEahcth03EfzpNIfj5vV09DWKoSEnc2/7lpVbtN3kISF2xq1O12TKzDJn7SOpIDXLsmBMFHFmdBpM04wua6ywYu6iXrWUvCFxhl/kul7Vjrtx7g8m0f5IDkilym13CGEjGZ7xuBCS/arpmDsSE0kM7kpQh+iuRmaV8'),
(6,'E11.9','kOermDpXAkx1FKuYttxFfhG2DQLgqpmRx0ssWCeGfeTwWtj/0XM2zVKgIRnSzC75zXQDheFmo162nC7guNqu0ilSinu2080TuFumAkytgmXXyC1eCtRqIS/oRWMts050','/O+e6EeNUtTM8DN4hNcZv9BBytG6Zwt3GY+jSypUFU1dHhJ9IjhLeIe+vK30gNRxPjKIdFfcFSvZ7OlV2soWk8xDcnJB+2EKkKDEnGszZGTJYbDbk2M3pC0x7LfDpB9RMHdPrI66/YZH+I8TGWtiFrSlCrp+RJ3CqbIYoOQLi+IoTZXvC+GDXv/zVuwAD7Y5'),
(10,'I10','ELxjKOVSQoprD7AKY3wndDxLfgXH4fwzuuLUn4gR+FwcJw51b0eURJYcd13IhKLuevfLlh1XQ4KxjQ3CKMzLZFWpHBuQ/VSgmN9QwcK8oA6K5EiSdp0DFyrPIS66hW1SmuL/kdBqE/I8pfVFSYTrcg==','dkKgCs2b/z265T8r1nTROBqbLA+n4B00ksYzJs6Bxs7OQOOls8cdRbu5OCg9G1I1WeHYG4PNSQ2Az+Th6IP2ao4TorB4imccL2zb9B9MhcP1YSmPPO0vswfp4+64B7P8'),
(11,'K35.8','hf4y/IcKbP4OTh71SE5mZdDHTq3Fx+EOqYhEv3KS6VMEYTxt9nlAzJHG3U2han9guSlwjiZ9e1SCQ5W2mhqNu0IEu2bJeONdlecZ1s1lyjdNpCwZkczfZGoTmrecwpSCvPTw+RNHNb9+R8jjATucJA==','kIi3jLvFpZwBfhod91zQZLXMioiDm3tYCxKR8vMHLDoG19Hga18qlJZdAJd+UUQKMEiXjP/ZluFOacofzOUpgQ==');

-- Госпитализации оформлены администратором (users.id = 1).
-- total_cost считается автоматически: сутки × цена + 500 ₽.
INSERT INTO `hospitalizations`
(`patient_id`,`appointment_id`,`room_id`,`admission_date`,`discharge_date`,`daily_price`,`service_fee`,`status`,`created_by`) VALUES
(1,2,6,'2026-09-23','2026-09-27',8500.00,500.00,'completed',1),
(2,3,1,'2026-09-16','2026-09-20',5500.00,500.00,'completed',1),
(3,NULL,10,'2026-10-25','2026-10-31',14000.00,500.00,'active',1),
(5,NULL,2,'2026-10-24','2026-10-30',5500.00,500.00,'active',1),
(1,NULL,4,'2026-10-26','2026-10-31',5500.00,500.00,'active',1),
(2,NULL,6,'2026-10-27','2026-11-01',8500.00,500.00,'active',1),
(4,10,9,'2026-10-27','2026-11-02',8500.00,500.00,'active',1),
(6,11,7,'2026-11-05','2026-11-08',8500.00,500.00,'planned',1),
(5,NULL,10,'2026-10-10','2026-10-12',14000.00,500.00,'cancelled',1);
