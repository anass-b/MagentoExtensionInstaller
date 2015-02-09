# Magento Extension Installer


## Link/Copy/Remove a single extension

    cd magento/root
    magext link /absolute/path/to/extension
    magext copy /absolute/path/to/extension
    magext remove /absolute/path/to/extension


## Install/Uninstall all extensions in composer.json

    cd directory/of/composer_json/and/vendor/folder
    magext install htdocs
    magext uninstall htdocs