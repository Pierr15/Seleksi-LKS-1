-- Database terpisah untuk pengujian PHPUnit (dibuat sekali saat volume DB pertama kali).
CREATE DATABASE IF NOT EXISTS orbit_building_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON orbit_building_test.* TO 'orbit'@'%';
FLUSH PRIVILEGES;
