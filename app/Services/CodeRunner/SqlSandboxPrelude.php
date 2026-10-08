<?php

namespace App\Services\CodeRunner;

class SqlSandboxPrelude
{
    public function for(string $dialect): string
    {
        return $dialect === 'mysql' ? $this->mysql() : $this->sqlite();
    }

    private function mysql(): string
    {
        return <<<'SQL'
CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120), email VARCHAR(190) UNIQUE, role VARCHAR(30), is_active TINYINT, xp INT, created_at DATETIME, deleted_at DATETIME NULL, email_verified_at DATETIME NULL, display_name VARCHAR(120) NULL, password VARCHAR(255));
INSERT INTO users VALUES
(1,'Anna','anna@example.com','student',1,1850,'2026-09-01 10:00:00',NULL,'2026-09-01 10:10:00','Anna','hash'),
(2,'Béla','bela@example.com','student',1,720,'2026-09-15 12:00:00',NULL,NULL,NULL,'hash'),
(3,'Admin','admin@example.com','admin',1,6200,'2026-08-01 08:00:00',NULL,'2026-08-01 08:10:00','Admin','hash');
CREATE TABLE categories (id BIGINT PRIMARY KEY, name VARCHAR(120), slug VARCHAR(150) UNIQUE, sort_order INT);
INSERT INTO categories VALUES (1,'HTML','html',10),(2,'CSS','css',20),(6,'JavaScript','javascript',60),(9,'SQL','sql',90);
CREATE TABLE lessons (id BIGINT PRIMARY KEY, category_id BIGINT, title VARCHAR(255), slug VARCHAR(255), sort_order INT, is_published TINYINT DEFAULT 1, updated_at DATETIME, deleted_at DATETIME NULL, FOREIGN KEY(category_id) REFERENCES categories(id));
INSERT INTO lessons VALUES
(1,1,'HTML alapok','html-alapok',10,1,'2026-10-01 10:00:00',NULL),
(2,2,'CSS alapok','css-alapok',10,1,'2026-10-01 10:00:00',NULL),
(37,6,'JavaScript változók','javascript-valtozok',40,1,'2026-10-03 10:00:00',NULL),
(90,9,'SELECT és FROM','sql-select-from',20,1,'2026-10-04 10:00:00',NULL);
CREATE TABLE notes (id BIGINT PRIMARY KEY, user_id BIGINT, lesson_id BIGINT, content TEXT);
INSERT INTO notes VALUES (1,1,37,'Gyakorolni a const és let közti különbséget.');
CREATE TABLE favorites (id BIGINT PRIMARY KEY, user_id BIGINT, lesson_id BIGINT);
INSERT INTO favorites VALUES (1,1,37),(2,1,90),(3,2,1);
CREATE TABLE progress (id BIGINT PRIMARY KEY, user_id BIGINT, lesson_id BIGINT, completed_at DATETIME NULL);
INSERT INTO progress VALUES (1,1,1,'2026-10-01 12:00:00'),(2,1,37,'2026-10-03 13:00:00'),(3,2,1,NULL);
CREATE TABLE payments (id BIGINT PRIMARY KEY, user_id BIGINT, amount DECIMAL(10,2), created_at DATETIME);
INSERT INTO payments VALUES (1,1,2490,'2026-09-01 10:00:00'),(2,1,2490,'2026-10-01 10:00:00'),(3,2,1990,'2026-10-02 10:00:00');
CREATE TABLE newsletter_subscribers (id BIGINT PRIMARY KEY, email VARCHAR(190));
INSERT INTO newsletter_subscribers VALUES (1,'anna@example.com'),(2,'newsletter@example.com');
CREATE TABLE tags (id BIGINT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) UNIQUE);
CREATE TABLE settings (`key` VARCHAR(120) PRIMARY KEY, `value` TEXT);
CREATE TABLE accounts (id BIGINT PRIMARY KEY, balance DECIMAL(12,2));
INSERT INTO accounts VALUES (1,5000),(2,1500);
SQL;
    }

    private function sqlite(): string
    {
        return <<<'SQL'
PRAGMA foreign_keys = ON;
CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, role TEXT, is_active INTEGER, xp INTEGER, created_at TEXT, deleted_at TEXT, email_verified_at TEXT, display_name TEXT, password TEXT);
INSERT INTO users VALUES
(1,'Anna','anna@example.com','student',1,1850,'2026-09-01 10:00:00',NULL,'2026-09-01 10:10:00','Anna','hash'),
(2,'Béla','bela@example.com','student',1,720,'2026-09-15 12:00:00',NULL,NULL,NULL,'hash'),
(3,'Admin','admin@example.com','admin',1,6200,'2026-08-01 08:00:00',NULL,'2026-08-01 08:10:00','Admin','hash');
CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT UNIQUE, sort_order INTEGER);
INSERT INTO categories VALUES (1,'HTML','html',10),(2,'CSS','css',20),(6,'JavaScript','javascript',60),(9,'SQL','sql',90);
CREATE TABLE lessons (id INTEGER PRIMARY KEY, category_id INTEGER, title TEXT, slug TEXT, sort_order INTEGER, is_published INTEGER DEFAULT 1, updated_at TEXT, deleted_at TEXT, FOREIGN KEY(category_id) REFERENCES categories(id));
INSERT INTO lessons VALUES
(1,1,'HTML alapok','html-alapok',10,1,'2026-10-01 10:00:00',NULL),
(2,2,'CSS alapok','css-alapok',10,1,'2026-10-01 10:00:00',NULL),
(37,6,'JavaScript változók','javascript-valtozok',40,1,'2026-10-03 10:00:00',NULL),
(90,9,'SELECT és FROM','sql-select-from',20,1,'2026-10-04 10:00:00',NULL);
CREATE TABLE notes (id INTEGER PRIMARY KEY, user_id INTEGER, lesson_id INTEGER, content TEXT);
INSERT INTO notes VALUES (1,1,37,'Gyakorolni a const és let közti különbséget.');
CREATE TABLE favorites (id INTEGER PRIMARY KEY, user_id INTEGER, lesson_id INTEGER);
INSERT INTO favorites VALUES (1,1,37),(2,1,90),(3,2,1);
CREATE TABLE progress (id INTEGER PRIMARY KEY, user_id INTEGER, lesson_id INTEGER, completed_at TEXT);
INSERT INTO progress VALUES (1,1,1,'2026-10-01 12:00:00'),(2,1,37,'2026-10-03 13:00:00'),(3,2,1,NULL);
CREATE TABLE payments (id INTEGER PRIMARY KEY, user_id INTEGER, amount REAL, created_at TEXT);
INSERT INTO payments VALUES (1,1,2490,'2026-09-01 10:00:00'),(2,1,2490,'2026-10-01 10:00:00'),(3,2,1990,'2026-10-02 10:00:00');
CREATE TABLE newsletter_subscribers (id INTEGER PRIMARY KEY, email TEXT);
INSERT INTO newsletter_subscribers VALUES (1,'anna@example.com'),(2,'newsletter@example.com');
CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE);
CREATE TABLE settings (`key` TEXT PRIMARY KEY, `value` TEXT);
CREATE TABLE accounts (id INTEGER PRIMARY KEY, balance REAL);
INSERT INTO accounts VALUES (1,5000),(2,1500);
SQL;
    }
}
