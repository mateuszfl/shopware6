CREATE DATABASE shopware_mysql_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON shopware_mysql_test.* TO 'shopware'@'%';
CREATE USER 'replicator'@'%' IDENTIFIED BY 'replicator';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';
