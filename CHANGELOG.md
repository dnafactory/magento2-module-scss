1.0.2
=============
* Fixed:
  * magento 2 fails to read the _extend.scss and the _module.scss from modules
  
1.0.1
=============
* Fixed:
    * Most of the collected less variable values were parsed as quoted strings
    * Less 'false' value is now parsed as null
    
1.0.0
=============
* Added:
    * Fully integrated temporary assets management system
    * @vars_import (lib) directive for lib assets references
* Fixed:
    * Stability & performance
    * Less vars extraction (with patched [wikimedia/less.php](https://github.com/dnafactory/wikimedia-less.php-patched/commit/0666335e26e188ee461ec69f56f58072ec09f7cc) module)
    * [01] Broken temporary assets management system
    
0.9.0-alpha
=============
* Added:
    * @vars_import directive for Less to Scss variables translator preprocessor
    * Custom scss Importer preprocessor
    * Less to Scss vars translator
* Fixed:
    * Magento 2 modules dependencies
* Issues:
    * [01] Broken temporary assets management system

0.5.0
=============
* Added:
    * a Client Side Renderer for scss assets
    * Magento 2 modules dependencies

0.1.0
=============
* Added:
    * first version with base scss processor functionality