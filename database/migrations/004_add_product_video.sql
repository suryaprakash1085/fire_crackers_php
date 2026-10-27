-- Optional product video (mp4/webm) shown on the product page
ALTER TABLE products ADD COLUMN video_path VARCHAR(255) NULL AFTER aliases;
