#!/bin/bash

set -e

export AUTOCONF_VERSION=2.69

# Use a release version like 7.1.8 for a stable release
PHP_VERSION=php-7.1.8
PHP_INSTALL_NAME=7.1.8

FILE_OWNER=vagrant

TIMEZONE="Europe\/Brussels"
FPM_PORT=9071
FPM_USER=$FILE_OWNER
FPM_GROUP=$FILE_OWNER

doas rm -rf /etc/php-${PHP_INSTALL_NAME}
doas rm -rf /usr/local/php-${PHP_INSTALL_NAME}

if [ "$1" == "-r" ]; then
    echo "Removed version ${PHP_INSTALL_NAME} from the system."
    exit 0;
fi

doas mkdir -p /etc/php-${PHP_INSTALL_NAME}/conf.d
doas mkdir -p /etc/php-${PHP_INSTALL_NAME}/{cli,fpm}/conf.d
doas mkdir /usr/local/php-${PHP_INSTALL_NAME}

doas chown -R ${FILE_OWNER}:${FILE_OWNER} /etc/php-${PHP_INSTALL_NAME}
doas chown -R ${FILE_OWNER}:${FILE_OWNER} /usr/local/php-${PHP_INSTALL_NAME}

# Download

if [ ! -d php-src ]; then 
    git clone http://github.com/php/php-src.git
fi

cd php-src
git checkout ${PHP_VERSION}

if [ -f Makefile ]; then
    make distclean
fi
git clean -xdf
./buildconf --force

# Removed these:
#     --with-gd
#     --with-freetype-dir
#     --with-openssl
#     --with-xsl
# Added these:
#     --disable-phar

CONFIGURE_STRING="--prefix=/usr/local/php-${PHP_INSTALL_NAME}
                  --enable-bcmath \
                  --with-bz2 \
                  --with-zlib \
                  --enable-zip \
                  --enable-calendar \
                  --enable-exif \
                  --enable-ftp \
                  --with-gettext \
                  --with-jpeg-dir \
                  --with-png-dir \
                  --with-xpm-dir \
                  --enable-mbstring \
                  --enable-mysqlnd \
                  --with-mysqli=mysqlnd \
                  --with-pdo-mysql=mysqlnd \
                  --enable-soap \
                  --with-readline \
                  --with-curl \
                  --disable-cgi \
                  --enable-intl \
                  --disable-phar"

# Options for development
CONFIGURE_STRING="$CONFIGURE_STRING \
                  --enable-debug"

# Build FPM

./configure CC=clang CFLAGS="-march=native" \
    $CONFIGURE_STRING \
    --with-config-file-path=/etc/php-${PHP_INSTALL_NAME}/fpm \
    --with-config-file-scan-dir=/etc/php-${PHP_INSTALL_NAME}/fpm/conf.d \
    --disable-cli \
    --enable-fpm \
    --with-fpm-user=${FPM_USER} \
    --with-fpm-group=${FPM_GROUP}

make -j2
make install

# Install config files

cp php.ini-production /etc/php-${PHP_INSTALL_NAME}/fpm/php.ini
sed -i "s/;date.timezone =.*/date.timezone = ${TIMEZONE}/" /etc/php-${PHP_INSTALL_NAME}/fpm/php.ini

cp sapi/fpm/php-fpm.conf.in /etc/php-${PHP_INSTALL_NAME}/fpm/php-fpm.conf
sed -i "s#^include=.*/#include=/etc/php-${PHP_INSTALL_NAME}/fpm/pool.d/#" /etc/php-${PHP_INSTALL_NAME}/fpm/php-fpm.conf

mkdir /etc/php-${PHP_INSTALL_NAME}/fpm/pool.d/
cp /usr/local/php-${PHP_INSTALL_NAME}/etc/php-fpm.d/www.conf.default /etc/php-${PHP_INSTALL_NAME}/fpm/pool.d/www.conf
sed -i "s/listen = 127.0.0.1:9000/listen = 127.0.0.1:${FPM_PORT}/g" /etc/php-${PHP_INSTALL_NAME}/fpm/pool.d/www.conf

# Cleanup

make distclean
./buildconf --force

# Build CLI

./configure CC=clang CFLAGS="-march=native" \
    $CONFIGURE_STRING \
    --enable-pcntl \
    --enable-sockets \
    --without-pear \
    --with-config-file-path=/etc/php-${PHP_INSTALL_NAME}/cli \
    --with-config-file-scan-dir=/etc/php-${PHP_INSTALL_NAME}/cli/conf.d

make -j4
make install

# Install config files

cp php.ini-production /etc/php-${PHP_INSTALL_NAME}/cli/php.ini
sed -i "s/;date.timezone =.*/date.timezone = ${TIMEZONE}/" /etc/php-${PHP_INSTALL_NAME}/cli/php.ini

# Build extensions

cd ..

PATH=/usr/local/php-${PHP_INSTALL_NAME}/bin:/usr/local/php-${PHP_INSTALL_NAME}/sbin:$PATH

# opcache
echo "zend_extension=opcache.so" > /etc/php-${PHP_INSTALL_NAME}/conf.d/opcache.ini
ln -s /etc/php-${PHP_INSTALL_NAME}/conf.d/opcache.ini /etc/php-${PHP_INSTALL_NAME}/cli/conf.d/opcache.ini
ln -s /etc/php-${PHP_INSTALL_NAME}/conf.d/opcache.ini /etc/php-${PHP_INSTALL_NAME}/fpm/conf.d/opcache.ini

# Symlink PHP into the path
doas ln -sf /usr/local/php-${PHP_INSTALL_NAME}/bin/php /usr/bin/php-${PHP_INSTALL_NAME}

# Ready

echo "Don't forget to run 'make test'."

