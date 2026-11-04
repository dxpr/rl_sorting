#!/bin/bash

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

# Solve phpcs 3 with coder 8.3 and PHPCompatibility 9 together; coder 9 needs phpcs 4.
composer global require drupal/coder:^8.3 phpcompatibility/php-compatibility:^9.3 squizlabs/php_codesniffer:^3.13 --dev || exit 1

export PATH="$PATH:$COMPOSER_HOME/vendor/bin"

composer global require dealerdirect/phpcodesniffer-composer-installer --dev || exit 1

composer global show -P
phpcs -i

phpcs --config-set colors 1
phpcs --config-set drupal_core_version "$TARGET_DRUPAL_CORE_VERSION"

phpcs --config-show 