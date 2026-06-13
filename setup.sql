-- AttendX Database Setup
-- Import this file in phpMyAdmin or run: mysql -u root attendx_db < setup.sql

CREATE DATABASE IF NOT EXISTS attendx_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendx_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','lecturer','admin') NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  student_number VARCHAR(20) UNIQUE NOT NULL,
  programme VARCHAR(100),
  year_of_study INT DEFAULT 1,
  phone VARCHAR(20),
  rfid_uid VARCHAR(50),
  finger_id INT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lecturers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  staff_id VARCHAR(20) UNIQUE NOT NULL,
  department VARCHAR(100),
  designation VARCHAR(50),
  phone VARCHAR(20),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_code VARCHAR(20) UNIQUE NOT NULL,
  class_name VARCHAR(100) NOT NULL,
  lecturer_id INT,
  venue VARCHAR(50),
  schedule VARCHAR(100),
  max_students INT DEFAULT 30,
  FOREIGN KEY (lecturer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS student_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  UNIQUE KEY unique_enroll (student_id, class_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  rfid_verified TINYINT(1) DEFAULT 0,
  finger_verified TINYINT(1) DEFAULT 0,
  method ENUM('RFID','Fingerprint','QR','Manual') NOT NULL,
  is_present TINYINT(1) DEFAULT 0,
  override_by INT DEFAULT NULL,
  override_reason TEXT DEFAULT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (override_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(100) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ======================================================
-- SEED DATA
-- All passwords: password123 (bcrypt)
-- Admin password: admin123 (same bcrypt hash used below)
-- ======================================================
INSERT IGNORE INTO users (username, password, role, name, email) VALUES
('admin',      '$2y$12$ryAmufAophevb4SJugeHqexRhHzAt/hAOhNeirVw6skKxlImOYf3i', 'admin',    'Administrator',      'admin@university.edu.my'),
('lecturer01', '$2y$12$pQr7/TVsx6huNxSDkSsiZOjD95Nc75lI0h4DMidRL2A9dCBagvUuG', 'lecturer', 'Dr. Amirul Hakim',   'amirul@university.edu.my'),
('2021001234', '$2y$12$pQr7/TVsx6huNxSDkSsiZOjD95Nc75lI0h4DMidRL2A9dCBagvUuG', 'student',  'Ahmad Rizal',        'ahmad@university.edu.my'),
('2021001235', '$2y$12$pQr7/TVsx6huNxSDkSsiZOjD95Nc75lI0h4DMidRL2A9dCBagvUuG', 'student',  'Nurul Ain Binti Aziz','nurul@university.edu.my'),
('2021001236', '$2y$12$pQr7/TVsx6huNxSDkSsiZOjD95Nc75lI0h4DMidRL2A9dCBagvUuG', 'student',  'Hafiz Zulkarnain',   'hafiz@university.edu.my'),
('2021001237', '$2y$12$pQr7/TVsx6huNxSDkSsiZOjD95Nc75lI0h4DMidRL2A9dCBagvUuG', 'student',  'Siti Nabilah',       'siti@university.edu.my'),
('2021001238', '$2y$12$pQr7/TVsx6huNxSDkSsiZOjD95Nc75lI0h4DMidRL2A9dCBagvUuG', 'student',  'Muhammad Faris',     'faris@university.edu.my');

-- Lecturer profile
INSERT IGNORE INTO lecturers (user_id, staff_id, department, designation, phone)
SELECT id, 'STF001', 'Computer Science', 'Senior Lecturer', '012-3456789' FROM users WHERE username='lecturer01';

-- Student profiles
INSERT IGNORE INTO students (user_id, student_number, programme, year_of_study, phone, rfid_uid, finger_id)
SELECT id, '2021001234', 'B.Sc Computer Science', 3, '011-11111111', 'A1B2C3D4', 1 FROM users WHERE username='2021001234';
INSERT IGNORE INTO students (user_id, student_number, programme, year_of_study, phone, rfid_uid, finger_id)
SELECT id, '2021001235', 'B.Sc Computer Science', 3, '011-22222222', 'B2C3D4E5', 2 FROM users WHERE username='2021001235';
INSERT IGNORE INTO students (user_id, student_number, programme, year_of_study, phone, rfid_uid, finger_id)
SELECT id, '2021001236', 'B.Sc Information Technology', 2, '011-33333333', 'C3D4E5F6', 3 FROM users WHERE username='2021001236';
INSERT IGNORE INTO students (user_id, student_number, programme, year_of_study, phone)
SELECT id, '2021001237', 'B.Sc Information Technology', 2, '011-44444444' FROM users WHERE username='2021001237';
INSERT IGNORE INTO students (user_id, student_number, programme, year_of_study, phone)
SELECT id, '2021001238', 'Diploma Computer Science', 1, '011-55555555' FROM users WHERE username='2021001238';

-- Classes
INSERT IGNORE INTO classes (class_code, class_name, lecturer_id, venue, schedule, max_students)
SELECT 'CS301', 'Data Structures & Algorithms', u.id, 'Lab 3', 'Mon/Wed 9:00-11:00am', 30 FROM users u WHERE u.username='lecturer01';
INSERT IGNORE INTO classes (class_code, class_name, lecturer_id, venue, schedule, max_students)
SELECT 'CS302', 'Database Systems', u.id, 'Room 201', 'Tue/Thu 2:00-4:00pm', 30 FROM users u WHERE u.username='lecturer01';
INSERT IGNORE INTO classes (class_code, class_name, lecturer_id, venue, schedule, max_students)
SELECT 'CS201', 'Object-Oriented Programming', u.id, 'Lab 1', 'Mon/Fri 10:00am-12:00pm', 30 FROM users u WHERE u.username='lecturer01';

-- Enroll students in classes
INSERT IGNORE INTO student_classes (student_id, class_id)
SELECT s.id, c.id FROM students s, classes c WHERE s.student_number IN ('2021001234','2021001235','2021001236') AND c.class_code='CS301';
INSERT IGNORE INTO student_classes (student_id, class_id)
SELECT s.id, c.id FROM students s, classes c WHERE s.student_number IN ('2021001234','2021001235','2021001237') AND c.class_code='CS302';
INSERT IGNORE INTO student_classes (student_id, class_id)
SELECT s.id, c.id FROM students s, classes c WHERE s.student_number IN ('2021001236','2021001237','2021001238') AND c.class_code='CS201';

-- Sample attendance (last 7 days)
INSERT IGNORE INTO attendance (student_id, class_id, rfid_verified, finger_verified, method, is_present, timestamp)
SELECT s.id, c.id, 1, 1, 'RFID', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM students s, classes c WHERE s.student_number='2021001234' AND c.class_code='CS301';

INSERT IGNORE INTO attendance (student_id, class_id, rfid_verified, finger_verified, method, is_present, timestamp)
SELECT s.id, c.id, 1, 0, 'RFID', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM students s, classes c WHERE s.student_number='2021001235' AND c.class_code='CS301';

INSERT IGNORE INTO attendance (student_id, class_id, rfid_verified, finger_verified, method, is_present, timestamp)
SELECT s.id, c.id, 1, 1, 'RFID', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM students s, classes c WHERE s.student_number='2021001234' AND c.class_code='CS302';

INSERT IGNORE INTO attendance (student_id, class_id, rfid_verified, finger_verified, method, is_present, timestamp)
SELECT s.id, c.id, 1, 1, 'Fingerprint', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM students s, classes c WHERE s.student_number='2021001236' AND c.class_code='CS201';

INSERT IGNORE INTO attendance (student_id, class_id, rfid_verified, finger_verified, method, is_present, timestamp)
SELECT s.id, c.id, 0, 1, 'Fingerprint', 0, DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM students s, classes c WHERE s.student_number='2021001237' AND c.class_code='CS201';
