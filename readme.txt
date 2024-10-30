=== Easy SCSS and JS ===

Contributors: Lars-
Plugin Name: Easy SCSS and JS
Tags: ljpc, scss, js, easyscss, easy, easyjs, css, url
Author URI: https://www.ljpc.solutions
Author: Lars Jansen
Requires at least: 6.0
Tested up to: 6.0.2
Stable tag: 2.5.7
Requires PHP: 7.4
Version: 2.5.7

== Description ==

This plugin adds SCSS functionality, compresses JS for you and creates an easy way to enqueue scripts and styles as well as localize them.

== How to use ==

Let's assume this structure:
    your theme
    ├── assets
    │   ├── js
    │   │   └── script.js
    │   └── scss
    │       └── style.scss
    ├── functions.php
    └── ...

You can now do this in your functions.php with styles:

    <?php
    add_action('wp_enqueue_scripts', static function(){
        /* Full version */
        \EasySCSSandJS\Styles::add('my_style_handle', __DIR__ .'/assets/scss/style.scss', [],[], true);

        /* Shortest version */
        \EasySCSSandJS\Styles::add('my_style_handle', __DIR__ .'/assets/scss/style.scss');

        /* Add dependencies (example: depends on handle 'bootstrap') */
        \EasySCSSandJS\Styles::add('my_style_handle', __DIR__ .'/assets/scss/style.scss', ['bootstrap']);

        /* Add variables */
        \EasySCSSandJS\Styles::add('my_style_handle', __DIR__ .'/assets/scss/style.scss', [], [
            'my_cool_color' => '#0000ff',
        ]);

        /* Enqueue it yourself */
        \EasySCSSandJS\Styles::add('my_style_handle', __DIR__ .'/assets/scss/style.scss', [], [], false);
        wp_enqueue_style('my_style_handle');
    });


And this with scripts:

    <?php
    add_action('wp_enqueue_scripts', static function(){
        /* Full version */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js', ['jquery'], [], true, true);

        /* Shortest version (jquery is by default a dependency) */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js');

        /* No dependencies (also no jquery) */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js', []);

        /* Add dependencies (besides jquery) */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js', ['jquery', 'other_script']);

        /* Add variables */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js', ['jquery'], [
            'my_variable' => 'testing this awesome plugin',
        ]);

        /* Enqueue it yourself */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js', ['jquery'], [], false);
        wp_enqueue_script('my_script_handle');

        /* Add the script to the header instead of the footer */
        \EasySCSSandJS\Scripts::add('my_script_handle', __DIR__ .'/assets/js/script.js', ['jquery'], [], true, false);
    });

Use variables in SCSS:

    // This is recommended. It will throw a fatal error if your PHP doesn't set the variable for some reason.
    // If PHP does set it, it will replace $my_cool_color with the defined color in PHP.
    $my_cool_color: #ffffff !default;

    body{
      background-color: $my_cool_color; // This will be blue (#0000ff)
    }

Use variables in JS:

    alert(my_script_handle_vars.my_variable);

Compiled files will be saved in wp-content/uploads/compiled-scss-and-js. When that folder is cleared, everything will be regenerated.

== Filters ==

There are filters for adding generic variables to all (or a selection) of scripts and styles or for adding extra content to files:
- `easy_scss_extra_variables`
- `easy_scss_add_code_before_content`
- `easy_scss_add_code_after_content`
- `easy_scss_create_source_map`
- `easy_scss_storage_folder_name`
- `easy_scss_storage_folder`
- `easy_scss_storage_folder_url`
- `easy_scss_after_compilation`
- `easy_js_extra_variables`
- `easy_js_storage_folder_name`
- `easy_js_storage_folder`
- `easy_js_storage_folder_url`
- `easy_js_after_compilation`

== Changelog ==

v2.5.7
- Updated scssphp to 1.11.0

v2.5.6
- Updated scssphp to 1.10.5

v2.5.5
- Updated scssphp to 1.10.2

v2.5.4
- Updated scssphp to 1.10.1

v2.5.3
- Removed legacy code

v2.5.1
- Added the easy_css_after_compilation and easy_js_after_compilation filters
- Upgraded scssphp to 1.10.0

v2.5.0
- Added the easy_css_after_compilation and easy_js_after_compilation filters
- Upgraded scssphp to 1.9.0

v2.3.0
- Added the ability to include URLs, which are then cached, minified and served
- Upgraded scssphp to 1.7.0
