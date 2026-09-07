<?php

declare(strict_types=1);

function db(bool $reset = false): PDO
{
    static $pdo = null;
    if ($reset) {
        $pdo = null;
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config.php';
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET time_zone = '-03:00'");
    try {
        ensure_schema($pdo);
    } catch (Throwable $e) {
        // install.php still creating the database
    }
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $hasTable = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if ($hasTable('document_groups') && !$hasColumn('document_groups', 'user_id')) {
        $pdo->exec('ALTER TABLE document_groups ADD COLUMN user_id INT UNSIGNED NULL AFTER id');
    }

    if ($hasTable('profiles')) {
        $profileCols = [
            'website_url' => 'VARCHAR(512) NULL AFTER linkedin_url',
            'portfolio_url' => 'VARCHAR(512) NULL AFTER website_url',
            'x_url' => 'VARCHAR(512) NULL AFTER portfolio_url',
            'instagram_url' => 'VARCHAR(512) NULL AFTER x_url',
            'phone' => 'VARCHAR(40) NULL AFTER instagram_url',
        ];
        foreach ($profileCols as $col => $def) {
            if (!$hasColumn('profiles', $col)) {
                $pdo->exec("ALTER TABLE profiles ADD COLUMN {$col} {$def}");
            }
        }
    }

    if ($hasTable('campaign_settings')) {
        if (!$hasColumn('campaign_settings', 'run_started_on')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN run_started_on DATE NULL AFTER apps_per_day');
        }
        if (!$hasColumn('campaign_settings', 'ideal_comp_ars')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN ideal_comp_ars DECIMAL(14,2) NOT NULL DEFAULT 7000000 AFTER run_started_on');
        }
        if (!$hasColumn('campaign_settings', 'ideal_comp_usd')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN ideal_comp_usd DECIMAL(14,2) NOT NULL DEFAULT 10000 AFTER ideal_comp_ars');
        }
        if (!$hasColumn('campaign_settings', 'ideal_comp_eur')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN ideal_comp_eur DECIMAL(14,2) NOT NULL DEFAULT 9000 AFTER ideal_comp_usd');
        }
        if (!$hasColumn('campaign_settings', 'ideal_remote')) {
            $pdo->exec("ALTER TABLE campaign_settings ADD COLUMN ideal_remote VARCHAR(32) NOT NULL DEFAULT 'full_remote' AFTER ideal_comp_eur");
        }
        if (!$hasColumn('campaign_settings', 'ideal_schedule')) {
            $pdo->exec("ALTER TABLE campaign_settings ADD COLUMN ideal_schedule VARCHAR(32) NOT NULL DEFAULT 'flexible' AFTER ideal_remote");
        }
        if (!$hasColumn('campaign_settings', 'ideal_require_ar')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN ideal_require_ar TINYINT(1) NOT NULL DEFAULT 1 AFTER ideal_schedule');
        }
        if (!$hasColumn('campaign_settings', 'weight_comp')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN weight_comp TINYINT UNSIGNED NOT NULL DEFAULT 35 AFTER ideal_require_ar');
        }
        if (!$hasColumn('campaign_settings', 'weight_remote')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN weight_remote TINYINT UNSIGNED NOT NULL DEFAULT 25 AFTER weight_comp');
        }
        if (!$hasColumn('campaign_settings', 'weight_schedule')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN weight_schedule TINYINT UNSIGNED NOT NULL DEFAULT 15 AFTER weight_remote');
        }
        if (!$hasColumn('campaign_settings', 'weight_quality')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN weight_quality TINYINT UNSIGNED NOT NULL DEFAULT 15 AFTER weight_schedule');
        }
        if (!$hasColumn('campaign_settings', 'weight_risk')) {
            $pdo->exec('ALTER TABLE campaign_settings ADD COLUMN weight_risk TINYINT UNSIGNED NOT NULL DEFAULT 15 AFTER weight_quality');
        }
    }

    if ($hasTable('offer_scores') && !$hasColumn('offer_scores', 'schedule_type')) {
        $pdo->exec('ALTER TABLE offer_scores ADD COLUMN schedule_type VARCHAR(64) NULL AFTER remote_policy');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_portals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(768) NOT NULL,
            notes VARCHAR(512) NULL,
            section VARCHAR(128) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_portals_user (user_id),
            CONSTRAINT fk_user_portals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    if ($hasTable('user_portals') && !$hasColumn('user_portals', 'section')) {
        $pdo->exec('ALTER TABLE user_portals ADD COLUMN section VARCHAR(128) NULL AFTER notes');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_tech_ratings (
            user_id INT UNSIGNED NOT NULL,
            technology_id INT UNSIGNED NOT NULL,
            category ENUM(\'known\',\'new\',\'old\',\'learning\',\'exclude\') NOT NULL DEFAULT \'known\',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, technology_id),
            CONSTRAINT fk_user_tech_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_tech_tech FOREIGN KEY (technology_id) REFERENCES technologies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_hr_answers (
            user_id INT UNSIGNED NOT NULL,
            faq_id INT UNSIGNED NOT NULL,
            question VARCHAR(512) NULL,
            answer MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, faq_id),
            CONSTRAINT fk_user_hr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    if ($hasTable('user_hr_answers') && !$hasColumn('user_hr_answers', 'question')) {
        $pdo->exec('ALTER TABLE user_hr_answers ADD COLUMN question VARCHAR(512) NULL AFTER faq_id');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            question VARCHAR(512) NOT NULL,
            why VARCHAR(768) NULL,
            tip VARCHAR(768) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_questions_user (user_id),
            CONSTRAINT fk_user_questions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_technologies (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NULL,
            name VARCHAR(128) NOT NULL,
            category ENUM(\'known\',\'new\',\'old\',\'learning\',\'exclude\') NOT NULL DEFAULT \'known\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_techs_user (user_id),
            CONSTRAINT fk_user_techs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
}
