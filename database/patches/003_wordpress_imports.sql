-- Source IDs and original payloads make WordPress imports repeatable and
-- preserve metadata that does not have a dedicated column in this CMS.
CREATE TABLE IF NOT EXISTS wordpress_imports (
  source_host VARCHAR(190) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  source_id VARCHAR(190) NOT NULL,
  local_id BIGINT UNSIGNED NOT NULL,
  source_url VARCHAR(1000) NULL,
  payload JSON NOT NULL,
  imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source_host, entity, source_id),
  INDEX idx_wp_local (entity, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A WordPress post can belong to several categories.
CREATE TABLE IF NOT EXISTS article_categories (
  article_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, category_id),
  CONSTRAINT fk_ac_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_ac_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
