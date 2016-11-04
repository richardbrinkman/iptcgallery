# iptcgallery
Website to view, modify and query IPTC tags of photos.

## Install and initialise database
The IPTC gallery uses a mysql database. Scripts for the installation, initialisation, starting and stopping, you will find in the database directory.
The first time you should run:
```bash
cd database
./installDB
./initDB
./startDB&
```

## Run the webserver
In order to use the IPTC gallery in the browser, you should start a webserver. Easiest is to run:
```bash
cd public_html
php -S localhost:8085 index.php
```
After that, you can fire you browser (such as firefox) and go to http://localhost:8085
