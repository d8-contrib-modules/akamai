#!/bin/bash
echo - "Running .travis-before-script.sh"

set -e $DRUPAL_TI_DEBUG

# Ensure the right Drupal version is installed.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

# Download dependencies.
mkdir -p "$DRUPAL_TI_DRUPAL_DIR/$DRUPAL_TI_MODULES_PATH"
cd "$DRUPAL_TI_DRUPAL_DIR/$DRUPAL_TI_MODULES_PATH"
git clone --depth 1 --branch 8.x-1.x http://git.drupal.org/project/composer_manager.git

# Ensure the module is linked into the code base and enabled.
# Note: This function is re-entrant.
drupal_ti_ensure_module_linked

# Initialize composer manager.
TMP_CWD=`pwd`
cd "$TMP_CWD"
php "$DRUPAL_TI_DRUPAL_DIR/$DRUPAL_TI_MODULES_PATH/composer_manager/scripts/init.php"
composer drupal-rebuild
composer update -n --verbose
