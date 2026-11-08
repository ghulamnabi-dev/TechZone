# TechZone storefront + admin portal on PHP 8.3 / Apache.
# DocumentRoot is the repository root, so /MarketingSite and /AdminPortal work as in development.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

# Production PHP settings: no error output, hardened session cookies, 2 MB uploads
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo 'expose_php = Off'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'error_log = /dev/stderr'; \
      echo 'upload_max_filesize = 2M'; \
      echo 'post_max_size = 8M'; \
      echo 'session.use_strict_mode = 1'; \
      echo 'session.use_only_cookies = 1'; \
      echo 'session.cookie_httponly = 1'; \
      echo 'session.cookie_samesite = Lax'; \
    } > "$PHP_INI_DIR/conf.d/zz-techzone.ini"

# Apache: hide internals, block non-public folders and dotfiles, no PHP in uploads
RUN { \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo '<Directory /var/www/html>'; \
      echo '    Options -Indexes +FollowSymLinks'; \
      echo '    AllowOverride All'; \
      echo '</Directory>'; \
      echo '<DirectoryMatch "^/var/www/html/(core|database|docs|vendor|\.git|\.github)(/|$)">'; \
      echo '    Require all denied'; \
      echo '</DirectoryMatch>'; \
      echo '<FilesMatch "^(\..*|composer\.(json|lock)|Dockerfile|docker-compose\.yml|.*\.(md|sql|log))$">'; \
      echo '    Require all denied'; \
      echo '</FilesMatch>'; \
      echo '<Directory /var/www/html/AdminPortal/uploads>'; \
      echo '    php_admin_flag engine off'; \
      echo '    <FilesMatch "\.(php|phtml|phar|pht|phps)$">'; \
      echo '        Require all denied'; \
      echo '    </FilesMatch>'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/techzone.conf \
 && a2enconf techzone

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p AdminPortal/uploads \
 && chown -R www-data:www-data AdminPortal/uploads \
 && find . -type d -exec chmod 755 {} + \
 && find . -type f -exec chmod 644 {} +

EXPOSE 80
