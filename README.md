## M2 Scss Preprocessor
[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](https://opensource.org/licenses/BSD-3-Clause)
======
Provides an Scss preprocessor ([scssphp/scssphp](https://github.com/scssphp/scssphp)) to Magento 2 as an Alternative Source Content Processor.

Supports Magento 2 Theme inheritance and Assets fallback system.

Frontend compilation is currently not tested and not fully implemented yet.
___

### How to use
Put your base scss file in your preferred location as Magento 2 standards. Ex:

- app/design/frontend/MyCompany/theme/web/css/--->*mystyle.scss*
- app/code/MyCompany/MyModule/view/frontend/web/css/--->*mystyle.scss*
- app/design/frontend/MyCompany/theme/TheirCompany_TheirModule/web/css/--->*mystyle.scss*

Then add your source files references as you do with .less ones. Oh, and you can use Magento custom directives (like @magento_import) too. Ex:

```scss
@import 'source/_mixins';
@import 'source/functions.scss';
//@magento_import 'source/_extends.scss';
```

### The @vars_import directive
As Magento 2 comes only with .less preprocessor, you may need to merge your new shiny scss project into the old fashioned .less theme. 
When it comes to needs, you can simply use the **@vars_import** directive on top of your main scss file to load and translate .less vars. Ex:

```scss
//@vars_import 'source/_variables.less';

@import 'source/_mixins';
@import 'source/functions.scss';
//@magento_import 'source/_extends.scss';
```

*Happy Coding*
