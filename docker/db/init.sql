-- One database per OpenCart store. Runs once, when the db volume is created.
CREATE DATABASE IF NOT EXISTS oc23 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE IF NOT EXISTS oc30 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE IF NOT EXISTS oc40 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE IF NOT EXISTS oc41 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE USER IF NOT EXISTS 'opencart'@'%' IDENTIFIED BY 'opencart';
GRANT ALL PRIVILEGES ON oc23.* TO 'opencart'@'%';
GRANT ALL PRIVILEGES ON oc30.* TO 'opencart'@'%';
GRANT ALL PRIVILEGES ON oc40.* TO 'opencart'@'%';
GRANT ALL PRIVILEGES ON oc41.* TO 'opencart'@'%';
FLUSH PRIVILEGES;
