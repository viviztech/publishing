CREATE TABLE IF NOT EXISTS site_daily_views (
  view_date DATE PRIMARY KEY,
  page_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO advertisement_positions(name,slug,description,recommended_width,recommended_height,is_active) VALUES
('Homepage Bottom','homepage-bottom','After homepage category sections',970,250,1),
('Category Top','category-top','Before the category lead story',970,90,1),
('Category Bottom','category-bottom','After category pagination',970,250,1),
('Article Sidebar','article-sidebar','Beside the reading column on desktop',300,600,1),
('Topic / Author Top','archive-top','Before topic and author archives',970,90,1),
('Topic / Author Sidebar','archive-sidebar','Beside topic and author article grids',300,600,1),
('Topic / Author Bottom','archive-bottom','After topic and author archives',970,250,1),
('Static Page Top','page-top','Before about, contact and policy pages',970,90,1),
('Static Page Bottom','page-bottom','After about, contact and policy pages',970,250,1),
('Search Top','search-top','Above search results',970,90,1),
('Search Bottom','search-bottom','Below search results',970,250,1);

UPDATE advertisement_positions SET name='Category sidebar',description='Beside the category article grid on desktop',recommended_width=300,recommended_height=600 WHERE slug='category-page';
UPDATE advertisement_positions SET name='Article in-body',description='Inside longer articles; after the body on short stories',recommended_width=336,recommended_height=280 WHERE slug='article-middle';
UPDATE advertisement_positions SET name='Article top',description='Between headline and lead image',recommended_width=970,recommended_height=90 WHERE slug='article-top';
