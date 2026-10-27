-- Clean light theme: move the old cream background default to the new cool-white default (only if you never changed it).
UPDATE site_settings SET setting_value='#F8FAFC' WHERE setting_key='color_bg' AND setting_value IN ('#FFFBF5','#fffbf5');
UPDATE site_settings SET setting_value='white' WHERE setting_key='header_style' AND setting_value='tinted';
UPDATE site_settings SET setting_value='white' WHERE setting_key='footer_style' AND setting_value='tinted'
