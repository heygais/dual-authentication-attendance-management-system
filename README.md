# rfid--attendance--system

AttendX – IoT-Based Attendance Management System
AttendX is a dual-component attendance system that combines RFID hardware with a web-based dashboard for real-time tracking and management.
Hardware: An ESP32 microcontroller paired with an RC522 RFID reader and a 1602 LCD I2C display handles student check-ins. Students tap their RFID card, and the device displays instant feedback (success, duplicate scan, or not enrolled) while logging the attendance event.
Backend: A PHP/MySQL backend receives attendance data from the ESP32 in real time, validates session and enrollment status, and prevents duplicate scans within a session.
Web Dashboard: A live web interface lets admins and lecturers manage users, classes, and enrollments, view real-time attendance as it happens, and access filtered attendance reports and charts by class or session.
Tech Stack: ESP32, RFID (RC522), LCD 1602 I2C, PHP, MySQL, HTML/CSS/JavaScript (vanilla, no frameworks)
