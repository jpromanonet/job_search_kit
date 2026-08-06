-- Job Search Kit — schema
-- Charset: utf8mb4 | Timezone app: America/Argentina/Buenos_Aires

CREATE DATABASE IF NOT EXISTS job_search_kit
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE job_search_kit;

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Plan: one row per campaign day
CREATE TABLE IF NOT EXISTS day_plans (
  day_number TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  plan_date DATE NOT NULL,
  date_label VARCHAR(64) NOT NULL,
  phase ENUM('build','high_volume','finish') NOT NULL,
  phase_label VARCHAR(32) NOT NULL,
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
  blog_title VARCHAR(255) NOT NULL DEFAULT '',
  blog_angle TEXT NULL,
  blog_draft TEXT NULL,
  x_copy TEXT NULL,
  linkedin_copy TEXT NULL,
  instagram_task TEXT NULL,
  close_day_proof TEXT NULL,
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
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Recommendation sections (preface without technology inventory)
CREATE TABLE IF NOT EXISTS recommendation_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sort_order INT NOT NULL DEFAULT 0,
  title VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL
) ENGINE=InnoDB;

-- Technology inventory
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

-- Document kit (CVs, cover letters, messages, etc.)
CREATE TABLE IF NOT EXISTS document_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  slug VARCHAR(128) NOT NULL UNIQUE,
  category ENUM('cv','cover_letter','summary','message','other') NOT NULL DEFAULT 'other',
  language ENUM('es','en','both','na') NOT NULL DEFAULT 'na',
  description TEXT NULL,
  body_text MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0
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

-- Message templates (recruiter, CTO, CEO, etc.)
CREATE TABLE IF NOT EXISTS message_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  audience ENUM('recruiter','cto_ceo','hiring_manager','referral','follow_up','thank_you','salary','availability','other') NOT NULL,
  language ENUM('es','en') NOT NULL DEFAULT 'en',
  market ENUM('ar','intl','both') NOT NULL DEFAULT 'both',
  title VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  placeholders VARCHAR(512) NULL,
  version VARCHAR(32) NOT NULL DEFAULT '1.0',
  approved TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Job applications tracker
CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  day_number TINYINT UNSIGNED NULL,
  company VARCHAR(255) NOT NULL,
  role_title VARCHAR(255) NOT NULL,
  market ENUM('ar','intl') NOT NULL,
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
  ) NOT NULL DEFAULT 'discovered',
  result_notes TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_day FOREIGN KEY (day_number) REFERENCES day_plans(day_number) ON DELETE SET NULL,
  KEY idx_company_role_url (company(100), role_title(100), canonical_url(191))
) ENGINE=InnoDB;

-- Offer comparator scores (when stage = offer)
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
  notes TEXT NULL,
  ranking_notes TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_offer_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_app_stage ON applications(stage);
CREATE INDEX idx_app_market ON applications(market);
CREATE INDEX idx_app_date ON applications(application_date);
CREATE INDEX idx_day_status ON day_plans(status);
CREATE INDEX idx_day_phase ON day_plans(phase);
