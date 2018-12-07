#!/bin/bash

function printAction {
    if [ "$DBG" != 1 ]; then
        let DOTS=50-${#1}
        echo -n "$1 " >&2
        printf '%0.s.' $(seq ${DOTS}) >&2
        echo -n " " >&2
    fi
}

function printOk {
    if [ "$DBG" != 1 ]; then
        echo -e "\033[32mOK\033[0m" >&2
    fi
}

Q='-q'
if [ "$DBG" = 1 ]; then
    set -x # xtrace: print commands
    Q=''
else
    exec > /dev/null
fi
set -e # errexit: exit on error

PROJECT_HOME=/var/www/secretsanta
VERSION=`date +"%Y%m%d%H%M%S"`
export SYMFONY_ENV=prod
cd ${PROJECT_HOME}

printAction "Get latest version from Git"
cd git
git pull ${Q} origin master
cd ..
printOk

printAction "Install latest version"
cp -R git releases/${VERSION}
cd releases/${VERSION}

cp ../../shared/parameters.yml app/config
cp ../../shared/client_secrets.json app/config
ln -s ../../shared/var/report_cache var/report_cache
printOk

printAction "Composer install"
composer.phar install --no-dev --classmap-authoritative ${Q}
printOk

printAction "Install assets"
bin/console assets:install web ${Q}
cp ../../shared/yandex_* web
printOk

printAction "Cleanup files and setting permissions"
rm -rf .git .gitignore Vagrantfile shell_provisioner
rm -rf web/app_{dev,test,test_travis}.php web/config.php

sudo chmod -R ug=rwX,o=rX ../${VERSION}
sudo chmod -R a+rwX var/logs var/cache
printOk

printAction "Stopping FPM"
sudo service php7.2-fpm stop
printOk
printAction "Running doctrine schema update"
bin/console doctrine:schema:update --force --env=${SYMFONY_ENV} ${Q}
cd ../..
printOk
printAction "Activate new version"
ln -sfn releases/${VERSION} current
printOk
printAction "Starting FPM"
sudo service php7.2-fpm start
printOk

printAction "Cleanup old versions, keep last 2"
cd releases
ls -1 | sort -r | tail -n +3 | xargs sudo rm -rf
cd ..
printOk

echo SecretSanta version ${VERSION} is deployed! >&2

