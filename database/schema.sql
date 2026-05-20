SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS eventhub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE eventhub_db;

DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS mail_logs;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('organizer', 'participant') NOT NULL DEFAULT 'participant',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(50) NOT NULL UNIQUE,
    label         VARCHAR(100) NOT NULL,
    color_primary VARCHAR(7) NOT NULL DEFAULT '#2563EB',
    color_light   VARCHAR(7) NOT NULL DEFAULT '#DBEAFE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    event_date      DATETIME NOT NULL,
    location        VARCHAR(255) NOT NULL,
    capacity        SMALLINT UNSIGNED NOT NULL CHECK (capacity > 0),
    category        VARCHAR(50) NOT NULL,
    organizer_email VARCHAR(255) NOT NULL,
    organizer_id    INT UNSIGNED NULL,
    alert_sent      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS registrations;
CREATE TABLE registrations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    token         VARCHAR(64)  NOT NULL UNIQUE,
    registered_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uq_event_email (event_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mail_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type          ENUM('confirmation', 'capacity_alert', 'ticket', 'other') NOT NULL,
    recipient     VARCHAR(255) NOT NULL,
    event_id      INT UNSIGNED NULL,
    error_message TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mail_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_events_date_category ON events (event_date, category);
-- Justification : la fonction searchEvents() filtre frequemment par date ET categorie.
-- Cet index compose permet a MySQL d'utiliser un seul balayage d'index pour les deux filtres,
-- reduisant le nombre de lignes examinees de O(n) a O(log n).

INSERT INTO categories (slug, label, color_primary, color_light) VALUES
    ('tech',     'Tech',     '#2563EB', '#DBEAFE'),
    ('design',   'Design',   '#7C3AED', '#EDE9FE'),
    ('business', 'Business', '#EA580C', '#FEF3C7'),
    ('science',  'Science',  '#16A34A', '#DCFCE7');

-- Mot de passe : password123
INSERT INTO users (name, email, password, role) VALUES
    ('Organisateur ENSA', 'orga@ensa.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organizer'),
    ('Yassine El Fassi', 'yassine@example.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Salma Benali', 'salma@example.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Mehdi Khalil', 'mehdi@example.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant'),
    ('Zineb Moussaoui', 'zineb@example.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant');

INSERT INTO events (title, description, event_date, location, capacity, category, organizer_email, organizer_id, alert_sent) VALUES
    (
        'DevFest Marrakech 2025',
        'La grande conference tech de Marrakech. Talks, ateliers pratiques et networking avec les professionnels du secteur.',
        '2025-09-20 09:00:00',
        'ENSA Marrakech - Grand Amphi',
        200,
        'tech',
        'walid.bouarifi@gmail.com',
        1,
        0
    ),
    (
        'UX Design Workshop',
        'Atelier intensif de design UX : prototypage Figma, tests utilisateurs, design systems. Places tres limitees.',
        '2025-07-28 14:00:00',
        'Ecole Nationale des Arts, Marrakech',
        30,
        'design',
        'walid.bouarifi@gmail.com',
        1,
        0
    ),
    (
        'PHP & MVC Day',
        'Journee dediee a PHP 8.x, architecture MVC native, bonnes pratiques PDO et securite des applications web.',
        '2025-11-08 09:30:00',
        'ENSA Marrakech - Salle TP Informatique',
        5,
        'tech',
        'walid.bouarifi@gmail.com',
        1,
        0
    );

INSERT INTO registrations (event_id, name, email, token, registered_at) VALUES
    (1, 'Yassine El Fassi',  'yassine@example.ma', SHA2(CONCAT('yassine', RAND()), 256), NOW() - INTERVAL 5 DAY),
    (1, 'Salma Benali',      'salma@example.ma',   SHA2(CONCAT('salma', RAND()), 256),   NOW() - INTERVAL 4 DAY),
    (1, 'Mehdi Khalil',      'mehdi@example.ma',   SHA2(CONCAT('mehdi', RAND()), 256),   NOW() - INTERVAL 3 DAY),
    (2, 'Zineb Moussaoui',   'zineb@example.ma',   SHA2(CONCAT('zineb', RAND()), 256),   NOW() - INTERVAL 2 DAY),
    (3, 'Omar Lahlou',       'omar@example.ma',    SHA2(CONCAT('omar', RAND()), 256),    NOW() - INTERVAL 1 DAY);

SET FOREIGN_KEY_CHECKS = 1;
