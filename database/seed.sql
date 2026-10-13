-- Development starter data. No administrator password is included.
-- Run `php scripts/create-admin.php` to create the first secure account.
INSERT INTO roles(name,slug) VALUES ('Super Admin','super-admin'),('Editor','editor'),('Author','author');

INSERT INTO categories(name,slug,description,sort_order,is_active,show_on_homepage) VALUES
('Politics','politics','Policy, government, elections, and public affairs.',1,1,1),
('World','world','International reporting and global analysis.',2,1,1),
('India','india','Reporting from across India.',3,1,1),
('Tamil Nadu','tamil-nadu','News and public life across Tamil Nadu.',4,1,1),
('Business','business','Markets, companies, work, and the economy.',5,1,1),
('Technology','technology','The people and ideas shaping technology.',6,1,1),
('Sports','sports','Scores, competition, and the stories beyond the field.',7,1,1),
('Culture','culture','Arts, entertainment, food, and everyday life.',8,1,1);

UPDATE categories SET name_ta=CASE slug
  WHEN 'politics' THEN 'அரசியல்' WHEN 'world' THEN 'உலகம்' WHEN 'india' THEN 'இந்தியா'
  WHEN 'tamil-nadu' THEN 'தமிழ்நாடு' WHEN 'business' THEN 'வணிகம்'
  WHEN 'technology' THEN 'தொழில்நுட்பம்' WHEN 'sports' THEN 'விளையாட்டு'
  WHEN 'culture' THEN 'கலாச்சாரம்' ELSE name_ta END;

INSERT INTO subcategories(category_id,name,slug,description,sort_order,is_active) VALUES
((SELECT id FROM categories WHERE slug='politics'),'Elections','elections','Campaigns, voting, and election administration.',1,1),
((SELECT id FROM categories WHERE slug='politics'),'Government','government','Government decisions, institutions, and public administration.',2,1),
((SELECT id FROM categories WHERE slug='world'),'Asia','asia','Reporting from across Asia.',1,1),
((SELECT id FROM categories WHERE slug='world'),'Europe','europe','Reporting from Europe.',2,1),
((SELECT id FROM categories WHERE slug='india'),'National','national','National affairs and developments across India.',1,1),
((SELECT id FROM categories WHERE slug='india'),'Education','education','Schools, universities, learning, and education policy.',2,1),
((SELECT id FROM categories WHERE slug='tamil-nadu'),'Chennai','chennai','News and public life in Chennai.',1,1),
((SELECT id FROM categories WHERE slug='tamil-nadu'),'Districts','districts','Reporting from districts across Tamil Nadu.',2,1),
((SELECT id FROM categories WHERE slug='business'),'Economy','economy','Economic policy, employment, and growth.',1,1),
((SELECT id FROM categories WHERE slug='business'),'Markets','markets','Companies, investing, and financial markets.',2,1),
((SELECT id FROM categories WHERE slug='technology'),'Artificial Intelligence','artificial-intelligence','AI research, products, policy, and industry.',1,1),
((SELECT id FROM categories WHERE slug='technology'),'Startups','startups','Entrepreneurs, funding, and emerging companies.',2,1),
((SELECT id FROM categories WHERE slug='sports'),'Cricket','cricket','Cricket news, matches, and analysis.',1,1),
((SELECT id FROM categories WHERE slug='sports'),'Football','football','Football competitions, clubs, and players.',2,1),
((SELECT id FROM categories WHERE slug='culture'),'Entertainment','entertainment','Film, television, music, and performance.',1,1),
((SELECT id FROM categories WHERE slug='culture'),'Lifestyle','lifestyle','Food, travel, design, and everyday living.',2,1);

INSERT INTO authors(name,slug,bio,email,status) VALUES
('News Desk','news-desk','The Chronicle newsroom team reports verified developments with clarity and context.','desk@example.com','active'),
('Ananya Raman','ananya-raman','Ananya covers public policy and civic life.','ananya@example.com','active'),
('Karthik Iyer','karthik-iyer','Karthik reports on technology, business, and innovation.','karthik@example.com','active');

INSERT INTO tags(name,slug,description) VALUES
('Elections','elections','Election reporting and analysis'),('Economy','economy','Economic policy and markets'),('Climate','climate','Climate and environment'),('Innovation','innovation','Technology and new ideas');

INSERT INTO advertisement_positions(name,slug,description,recommended_width,recommended_height,is_active) VALUES
('Header','header','Placement beneath the primary site header',1200,180,1),
('Homepage Top','homepage-top','Full-width placement below breaking news',1200,180,1),
('Homepage Middle','homepage-middle','Placement between homepage sections',1200,180,1),
('Sidebar','sidebar','Standard sidebar placement',300,600,1),
('Category Page','category-page','Category sidebar placement',300,600,1),
('Article Top','article-top','Below article headline',1200,180,1),
('Article Middle','article-middle','Article sidebar or in-story placement',300,600,1),
('Article Bottom','article-bottom','Below article content',1200,180,1),
('Footer','footer','Placement before the footer',1200,180,1);

INSERT INTO pages(title,slug,content,status,seo_title,seo_description) VALUES
('About Us','about-us','<p>The Daily Chronicle is an independent newsroom committed to verified reporting, useful context, and accountable journalism.</p><h2>Our mission</h2><p>We serve readers with reporting that respects their time and intelligence.</p>','published','About The Daily Chronicle','Learn about our newsroom and editorial mission.'),
('Editorial Policy','editorial-policy','<p>Our journalism is guided by accuracy, independence, fairness, transparency, and prompt corrections.</p>','published','Editorial Policy','The standards that guide our journalism.'),
('Privacy Policy','privacy-policy','<p>This page should be reviewed by qualified counsel and configured for your jurisdiction before launch.</p>','published','Privacy Policy','How this publication handles reader information.'),
('Terms & Conditions','terms','<p>This page should be reviewed by qualified counsel and configured for your publication before launch.</p>','published','Terms & Conditions','Terms governing use of this publication.');

INSERT INTO navigation_items(label,type,category_id,sort_order,is_active) SELECT name,'category',id,sort_order,1 FROM categories WHERE slug IN ('politics','world','india','tamil-nadu','business','technology','sports','culture');
UPDATE navigation_items n LEFT JOIN categories c ON c.id=n.category_id SET n.label_ta=c.name_ta WHERE n.type='category';

-- Original development stories; replace or archive them before launch.
INSERT INTO articles(title,slug,summary,content,category_id,author_id,status,published_at,is_featured,is_trending,is_editors_choice,seo_title,seo_description) VALUES
('City councils open a new chapter in public budget access','city-councils-open-public-budget-access','A new public portal aims to turn complex municipal spending records into information residents can use.','<p>Several city councils have begun publishing detailed budget records in a common, searchable format.</p><h2>What changes for residents</h2><p>The initiative brings contracts, project milestones, and department spending into one place. Civic groups say the real test will be whether records remain timely and complete.</p><blockquote>Public information becomes useful only when people can understand and act on it.</blockquote><p>Officials plan quarterly reviews and have invited residents to report missing or unclear data.</p>',(SELECT id FROM categories WHERE slug='politics'),(SELECT id FROM authors WHERE slug='ananya-raman'),'published',NOW()-INTERVAL 2 HOUR,1,1,1,'Cities expand public access to budget data','New municipal portals make public spending records easier to search and understand.'),
('Small manufacturers find momentum in energy-efficient upgrades','manufacturers-energy-efficient-upgrades','Lower operating costs are making practical efficiency projects more attractive to regional manufacturers.','<p>For small manufacturers, energy efficiency is moving from a long-term ambition to a near-term operating decision.</p><h2>Incremental changes, measurable returns</h2><p>Factory managers are starting with motors, lighting, compressed-air systems, and better production scheduling. The projects are modest, but the savings compound.</p><p>Industry advisers recommend measuring a baseline before purchasing equipment and reviewing results after every production cycle.</p>',(SELECT id FROM categories WHERE slug='business'),(SELECT id FROM authors WHERE slug='karthik-iyer'),'published',NOW()-INTERVAL 5 HOUR,1,1,0,'Small manufacturers invest in energy efficiency','Regional manufacturers are finding practical savings through targeted energy upgrades.'),
('Researchers build a clearer map of neighbourhood heat','researchers-map-neighbourhood-heat','Street-level measurements reveal how shade, traffic, and building materials affect heat from one block to the next.','<p>A research team has combined street sensors and satellite observations to create a detailed view of urban heat.</p><h2>Why street-level detail matters</h2><p>Citywide averages can hide large differences between adjacent neighbourhoods. Trees, roof materials, road width, and traffic all influence local conditions.</p><p>The team hopes planners can use the findings to prioritize shade, drinking water, and cooling interventions.</p>',(SELECT id FROM categories WHERE slug='technology'),(SELECT id FROM authors WHERE slug='news-desk'),'published',NOW()-INTERVAL 8 HOUR,1,0,1,'Researchers map urban heat block by block','New street-level research shows how heat exposure varies sharply across neighbourhoods.'),
('Community leagues put youth sport back on the weekend calendar','community-leagues-youth-sport-calendar','Volunteer-run leagues are expanding access with shared equipment, flexible schedules, and local coaching.','<p>Weekend grounds are busy again as community leagues welcome a new season of youth sport.</p><p>Organizers have introduced shared equipment libraries and shorter match formats to reduce the cost and time required to participate.</p><h2>Building for the long term</h2><p>Coaches say consistency matters more than competition in the early years. Their focus is on movement, teamwork, and making every player feel they belong.</p>',(SELECT id FROM categories WHERE slug='sports'),(SELECT id FROM authors WHERE slug='news-desk'),'published',NOW()-INTERVAL 1 DAY,0,1,0,'Community youth leagues return for a new season','Local leagues make youth sport more accessible through equipment sharing and flexible schedules.'),
('Independent bookshops create room for slow discovery','independent-bookshops-slow-discovery','A new generation of neighbourhood bookshops is combining careful curation with events that bring readers together.','<p>Independent bookshops are finding renewed purpose as places to browse, talk, and encounter unfamiliar ideas.</p><p>Owners describe curation as a conversation with their neighbourhood rather than an attempt to stock everything.</p><h2>More than a retail shelf</h2><p>Reading circles, children’s hours, translations, and small-press launches are helping shops build durable communities around books.</p>',(SELECT id FROM categories WHERE slug='culture'),(SELECT id FROM authors WHERE slug='ananya-raman'),'published',NOW()-INTERVAL 2 DAY,0,0,1,'Independent bookshops and the art of discovery','Neighbourhood bookshops build community through curation, events, and patient browsing.');

INSERT INTO article_tags(article_id,tag_id)
SELECT a.id,t.id FROM articles a JOIN tags t ON (a.slug='city-councils-open-public-budget-access' AND t.slug='elections') OR (a.slug='small-manufacturers-energy-efficient-upgrades' AND t.slug='economy') OR (a.slug='researchers-map-neighbourhood-heat' AND t.slug='climate') OR (a.slug='researchers-map-neighbourhood-heat' AND t.slug='innovation');

INSERT INTO advertisements(position_id,name,type,destination_url,status,priority,alt_text)
VALUES ((SELECT id FROM advertisement_positions WHERE slug='homepage-top'),'Development sample campaign','image','https://example.com','inactive',0,'Development advertisement placeholder');

INSERT INTO website_settings(setting_key,setting_value,setting_group,type) VALUES
('site_name','The Daily Chronicle','identity','text'),
('site_description','Independent journalism, useful context, and the stories shaping our world.','identity','textarea'),
('contact_email','newsroom@example.com','contact','email'),
('contact_phone','','contact','text'),
('address','','contact','textarea'),
('copyright_text','All rights reserved.','footer','text'),
('facebook_url','','social','url'),
('instagram_url','','social','url'),
('youtube_url','','social','url'),
('x_url','','social','url');

-- Extended magazine advertising inventory.
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
