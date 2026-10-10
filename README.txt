SIB SMM PANEL — FIXED INSTALL PACKAGE

1. Upload ALL files inside this ZIP directly into the document root of:
   sibsmm.mywebsitee.online
   (Usually public_html or the domain's assigned document root.)

2. Do NOT upload the ZIP itself into a subfolder and expect the domain root to find index.php.

3. Open:
   https://sibsmm.mywebsitee.online/install.php

4. Enter the MySQL database name, username and password created in cPanel.
   Give the MySQL user ALL PRIVILEGES on that database.

5. After installation, open:
   https://sibsmm.mywebsitee.online/

6. Default admin:
   Username: admin
   Password: admin123
   Change the password immediately.

7. If the domain still shows "page can't be found", this is NOT an index.php/database problem.
   Check cPanel -> Domains and make sure sibsmm.mywebsitee.online points to the folder where index.php was uploaded.

8. PHP 7.4+ is required. PHP 8.1/8.2/8.3 is recommended.
   Required extensions: pdo_mysql and curl.

9. Delete install.php and check.php after the panel is working.
