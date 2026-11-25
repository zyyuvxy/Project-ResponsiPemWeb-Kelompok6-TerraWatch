-- Patch foto seed ke assets/img/seed/ untuk database yang sudah terisi
UPDATE `report_images`
SET `image_path` = REPLACE(`image_path`, 'reports/report_', 'assets/img/seed/report_')
WHERE `image_path` LIKE 'reports/report_%';

UPDATE `articles`
SET `image` = REPLACE(`image`, 'articles/article_1.jpg', 'assets/img/seed/article_1.jpg')
WHERE `article_id` = 1;

UPDATE `articles`
SET `image` = 'assets/img/seed/article_2.jpg'
WHERE `article_id` = 2;
