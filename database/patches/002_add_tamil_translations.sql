ALTER TABLE categories
  ADD COLUMN name_ta VARCHAR(120) NULL AFTER name,
  ADD COLUMN description_ta TEXT NULL AFTER description;

ALTER TABLE articles
  ADD COLUMN title_ta VARCHAR(255) NULL AFTER title,
  ADD COLUMN summary_ta VARCHAR(500) NULL AFTER summary,
  ADD COLUMN content_ta LONGTEXT NULL AFTER content;

ALTER TABLE breaking_news
  ADD COLUMN title_ta VARCHAR(255) NULL AFTER title;

ALTER TABLE navigation_items
  ADD COLUMN label_ta VARCHAR(100) NULL AFTER label;

UPDATE categories SET name_ta=CASE slug
  WHEN 'politics' THEN 'அரசியல்'
  WHEN 'world' THEN 'உலகம்'
  WHEN 'india' THEN 'இந்தியா'
  WHEN 'tamil-nadu' THEN 'தமிழ்நாடு'
  WHEN 'business' THEN 'வணிகம்'
  WHEN 'technology' THEN 'தொழில்நுட்பம்'
  WHEN 'sports' THEN 'விளையாட்டு'
  WHEN 'culture' THEN 'கலாச்சாரம்'
  ELSE name_ta END;

UPDATE navigation_items n
LEFT JOIN categories c ON c.id=n.category_id
SET n.label_ta=COALESCE(c.name_ta,n.label_ta)
WHERE n.type='category';
