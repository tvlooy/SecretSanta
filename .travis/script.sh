#!/usr/bin/env bash

cp "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/files/behat-travis.yml" ./behat.yml

# Configure display
/sbin/start-stop-daemon --start --quiet --pidfile /tmp/xvfb_99.pid --make-pidfile --background --exec /usr/bin/Xvfb -- :99 -ac -screen 0 1680x1050x16
export DISPLAY=:99

chromium-browser --version

# Download and configure ChromeDriver
if [ ! -f $BUILD_CACHE_DIR/chromedriver ]; then
    # Using ChromeDriver 2.31 which supports Chrome 58-60, we're using Chromium 59
    curl http://chromedriver.storage.googleapis.com/2.31/chromedriver_linux64.zip > chromedriver.zip
    unzip chromedriver.zip
    mv chromedriver $BUILD_CACHE_DIR
fi

# Run Selenium with ChromeDriver
echo "Start selenium"
PATH=$PATH:$BUILD_CACHE_DIR bin/selenium-server-standalone > $TRAVIS_BUILD_DIR/selenium.log 2>&1 &

# Run phpunit tests (Temporary re-enable xdebug to generate coverage report)
phpenv config-add ~/xdebug.ini
phpunit -c app/phpunit.xml.dist --coverage-text || exit $?
phpenv config-rm xdebug.ini

# Run webserver
app/console server:run 127.0.0.1:8080 --env=test_travis --router=app/config/router_test_travis.php --no-debug --quiet > /dev/null 2>&1 &

# Run behat tests
bin/behat --strict -f progress || exit $?
