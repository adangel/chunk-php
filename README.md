# chunk-php

Simple hosting of temporary files. The idea is basically [chunk.io](https://chunk.io/) but implemented
in php so that can run on any website hoster.

## Features

*   Simple upload via browser
*   Upload via command line
*   Supports ZIP and TAR archives
*   Basic user control
*   Limit file size
*   Delete old files after a configurable period
*   Example:
    * Upload interface: <https://chunk.adangel.org>
    * Single image file: <https://chunk.adangel.org/adangel/8ec0f806-0317-4cdb-8925-eae3538b0b04>
    * A zip archive containing a website: <https://chunk.adangel.org/adangel/06190ed0-dd89-4dc8-946a-7324b062afe9/index.html>

## Development

```
composer install
vendor/bin/phpunit tests
```

### Run for development

    php -S localhost:8000 index.php

and then go to <http://localhost:8000>

## Deploy into production

Depending on whether the script should be available at the root
or as a subfolder, you need to use `.htaccess` and configure
rewrite rules.

```
# Make sure, the directory has "AllowOverride FileInfo".

RewriteEngine on

# if installed in subfolder "/chunk-php/"
RewriteRule ^$ /chunk-php/index.php [END]
RewriteRule ^(.*)$ /chunk-php/index.php/$1 [END]

# if installed in root directory
RewriteRule ^$ /index.php [END]
RewriteRule ^(.*)$ /index.php/$1 [END]
```

And adjust the users/credentials in `src/Config.php`.

## Changelog

### 1.0.0 (2020-06-06)
* Initial version
