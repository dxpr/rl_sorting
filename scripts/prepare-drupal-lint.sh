#!/bin/bash
set -e

if [ -z "$TARGET_DRUPAL_CORE_VERSION" ]; then
  # default to target Drupal 11, you can override this by setting the secrets value on your github repo
  TARGET_DRUPAL_CORE_VERSION=11
fi

echo "php --version"
php --version
echo "composer --version"
composer --version

echo "\$COMPOSER_HOME: $COMPOSER_HOME"
echo "TARGET_DRUPAL_CORE_VERSION: $TARGET_DRUPAL_CORE_VERSION"

# Add this line to avoid the plugin prompt
composer config --global allow-plugins.dealerdirect/phpcodesniffer-composer-installer true

# Resolve the coding standards together so they use a compatible PHPCS version.
composer global require --dev drupal/coder 'phpcompatibility/php-compatibility:^9.3' 'squizlabs/php_codesniffer:^3.13' --with-all-dependencies

export PATH="$PATH:$COMPOSER_HOME/vendor/bin"

composer global require dealerdirect/phpcodesniffer-composer-installer --dev

composer global show -P
phpcs -i

phpcs --config-set colors 1
phpcs --config-set drupal_core_version "$TARGET_DRUPAL_CORE_VERSION"

phpcs --config-show 