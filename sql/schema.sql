-- JobKit — único schema del repo
-- Sin usuarios, postulaciones ni archivos. Solo estructura.
-- Charset: utf8mb4 | Timezone de app: America/Argentina/Buenos_Aires
-- Aplicar con install.php o importar en el cliente MySQL.

CREATE DATABASE IF NOT EXISTS job_search_kit
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE job_search_kit;

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ---------------------------------------------------------------------------
-- Auth / perfil
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS profiles (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  headline VARCHAR(255) NULL,
  linkedin_url VARCHAR(512) NULL,
  website_url VARCHAR(512) NULL,
  portfolio_url VARCHAR(512) NULL,
  x_url VARCHAR(512) NULL,
  instagram_url VARCHAR(512) NULL,
  phone VARCHAR(40) NULL,
  location VARCHAR(120) NULL,
  bio TEXT NULL,
  avatar_path VARCHAR(255) NULL,
  preferred_market ENUM('ar','intl','both') NOT NULL DEFAULT 'ar',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campaign_settings (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  target_applications INT UNSIGNED NOT NULL DEFAULT 100,
  apps_per_day TINYINT UNSIGNED NOT NULL DEFAULT 5,
  run_started_on DATE NULL,
  ideal_comp_ars DECIMAL(14,2) NOT NULL DEFAULT 0,
  ideal_comp_usd DECIMAL(14,2) NOT NULL DEFAULT 0,
  ideal_comp_eur DECIMAL(14,2) NOT NULL DEFAULT 0,
  ideal_remote VARCHAR(32) NOT NULL DEFAULT 'full_remote',
  ideal_schedule VARCHAR(32) NOT NULL DEFAULT 'flexible',
  ideal_require_ar TINYINT(1) NOT NULL DEFAULT 1,
  weight_comp TINYINT UNSIGNED NOT NULL DEFAULT 35,
  weight_remote TINYINT UNSIGNED NOT NULL DEFAULT 25,
  weight_schedule TINYINT UNSIGNED NOT NULL DEFAULT 15,
  weight_quality TINYINT UNSIGNED NOT NULL DEFAULT 15,
  weight_risk TINYINT UNSIGNED NOT NULL DEFAULT 15,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_campaign_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Plantilla de plan (catálogo) + progreso por usuario
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS plan_days (
  day_number TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  phase ENUM('build','high_volume','finish') NOT NULL DEFAULT 'high_volume',
  phase_label VARCHAR(64) NOT NULL DEFAULT '',
  quota_label VARCHAR(128) NOT NULL DEFAULT '',
  applications_target SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  market_split VARCHAR(32) NOT NULL DEFAULT '',
  outcome TEXT NULL,
  special_focus TEXT NULL,
  candidate_tasks JSON NULL,
  ai_tasks JSON NULL,
  execute_today JSON NULL,
  definition_of_done TEXT NULL,
  source_allocations JSON NULL,
  blog_publish TINYINT(1) NOT NULL DEFAULT 0,
  blog_title VARCHAR(255) NOT NULL DEFAULT '',
  blog_angle TEXT NULL,
  blog_draft TEXT NULL,
  x_copy TEXT NULL,
  linkedin_copy TEXT NULL,
  instagram_task TEXT NULL,
  close_day_proof TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS day_progress (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  day_number TINYINT UNSIGNED NOT NULL,
  status ENUM('not_started','in_progress','done','blocked','missed') NOT NULL DEFAULT 'not_started',
  article_url VARCHAR(512) NULL,
  instagram_done TINYINT(1) NOT NULL DEFAULT 0,
  linkedin_posted TINYINT(1) NOT NULL DEFAULT 0,
  x_posted TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  evidence TEXT NULL,
  blockers TEXT NULL,
  carry_forward TEXT NULL,
  applications_logged SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_day (user_id, day_number),
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_day FOREIGN KEY (day_number) REFERENCES plan_days(day_number) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Catálogos (se siembran desde /data en install.php)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS recommendation_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sort_order INT NOT NULL DEFAULT 0,
  title VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS technology_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  area VARCHAR(128) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS technologies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  category ENUM('known','new','old','learning','exclude') NOT NULL DEFAULT 'known',
  notes VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_tech_group FOREIGN KEY (group_id) REFERENCES technology_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  url VARCHAR(768) NOT NULL,
  market ENUM('ar','intl','both') NOT NULL DEFAULT 'ar',
  category VARCHAR(128) NULL,
  notes TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_faq (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(128) NOT NULL DEFAULT '',
  question VARCHAR(512) NOT NULL,
  answer MEDIUMTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS company_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(512) NOT NULL,
  why TEXT NULL,
  tip TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Documentos, ATS y mensajes (por usuario)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS document_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  slug VARCHAR(128) NOT NULL,
  category ENUM('cv','cover_letter','summary','message','other') NOT NULL DEFAULT 'other',
  language ENUM('es','en','both','na') NOT NULL DEFAULT 'na',
  description TEXT NULL,
  body_text MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_user_doc_slug (user_id, slug),
  CONSTRAINT fk_doc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  format ENUM('docx','pdf','txt','md','other') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(128) NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  version VARCHAR(32) NOT NULL DEFAULT '1.0',
  approved TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doc_group FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
  UNIQUE KEY uq_group_format_version (group_id, format, version)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ats_lakes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  language ENUM('es','en') NOT NULL,
  body_text MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_ats_lang (user_id, language),
  CONSTRAINT fk_ats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Diario, ofertas y entrevistas
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  day_number TINYINT UNSIGNED NULL,
  company VARCHAR(255) NOT NULL,
  role_title VARCHAR(255) NOT NULL,
  market ENUM('ar','intl') NOT NULL DEFAULT 'ar',
  platform VARCHAR(128) NULL,
  discovery_source VARCHAR(128) NULL,
  canonical_url VARCHAR(768) NULL,
  location_eligible TINYINT(1) NOT NULL DEFAULT 1,
  role_family VARCHAR(128) NULL,
  cv_version VARCHAR(128) NULL,
  cover_letter VARCHAR(128) NULL,
  salary_note VARCHAR(255) NULL,
  currency ENUM('ARS','USD','EUR','other') NULL,
  salary_min DECIMAL(14,2) NULL,
  salary_max DECIMAL(14,2) NULL,
  fit_score TINYINT UNSIGNED NULL,
  application_date DATE NULL,
  contact_name VARCHAR(255) NULL,
  follow_up_date DATE NULL,
  stage ENUM(
    'discovered','selected','preparing','applied','follow_up',
    'recruiter_screen','technical','leadership','final',
    'offer','accepted','rejected','closed'
  ) NOT NULL DEFAULT 'applied',
  result_notes TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_day FOREIGN KEY (day_number) REFERENCES plan_days(day_number) ON DELETE SET NULL,
  KEY idx_app_user_stage (user_id, stage),
  KEY idx_app_user_date (user_id, application_date),
  KEY idx_company_role (user_id, company(100), role_title(100))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  from_stage VARCHAR(64) NULL,
  to_stage VARCHAR(64) NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_scores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL UNIQUE,
  compensation_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  role_fit_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  growth_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  culture_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  schedule_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  total_comp_monthly DECIMAL(14,2) NULL,
  currency ENUM('ARS','USD','EUR','other') NULL,
  employment_type VARCHAR(64) NULL,
  remote_policy VARCHAR(128) NULL,
  schedule_type VARCHAR(64) NULL,
  notes TEXT NULL,
  ranking_notes TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_offer_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS interview_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  application_id INT UNSIGNED NULL,
  interview_type ENUM(
    'recruiter_screen','technical','leadership','final','offer','other'
  ) NOT NULL DEFAULT 'other',
  interview_date DATE NULL,
  interviewer_name VARCHAR(255) NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
  prep_notes MEDIUMTEXT NULL,
  live_notes MEDIUMTEXT NULL,
  debrief_went_well MEDIUMTEXT NULL,
  debrief_gaps MEDIUMTEXT NULL,
  debrief_follow_up MEDIUMTEXT NULL,
  mood_score TINYINT UNSIGNED NULL,
  outcome ENUM('pending','passed','rejected','ghosted','offer','other') NOT NULL DEFAULT 'pending',
  tags VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_interview_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_interview_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
  KEY idx_interview_user_date (user_id, interview_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Overrides por usuario sobre catálogos
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS user_portals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  url VARCHAR(768) NOT NULL,
  notes VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_portals_user (user_id),
  CONSTRAINT fk_user_portals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_tech_ratings (
  user_id INT UNSIGNED NOT NULL,
  technology_id INT UNSIGNED NOT NULL,
  category ENUM('known','new','old','learning','exclude') NOT NULL DEFAULT 'known',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, technology_id),
  CONSTRAINT fk_user_tech_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_tech_tech FOREIGN KEY (technology_id) REFERENCES technologies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_hr_answers (
  user_id INT UNSIGNED NOT NULL,
  faq_id INT UNSIGNED NOT NULL,
  answer MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, faq_id),
  CONSTRAINT fk_user_hr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_hr_faq FOREIGN KEY (faq_id) REFERENCES hr_faq(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  question VARCHAR(512) NOT NULL,
  why VARCHAR(768) NULL,
  tip VARCHAR(768) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_questions_user (user_id),
  CONSTRAINT fk_user_questions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_technologies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NULL,
  name VARCHAR(128) NOT NULL,
  category ENUM('known','new','old','learning','exclude') NOT NULL DEFAULT 'known',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_techs_user (user_id),
  CONSTRAINT fk_user_techs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
