Laravel Localization Helpers
============================

[![Total Downloads](https://poser.pugx.org/keypoint-solutions/laravel-localization-helpers/downloads.svg)](https://packagist.org/packages/keypoint-solutions/laravel-localization-helpers)

## This branch is the current dev branch

LLH is a set of artisan commands to manage translations in your Laravel project. Key features :

- parse your code and generate lang files
- translate your sentences automatically, thanks to Microsoft Translator API
- configure output according to your code style

## Table of contents

1. [Installation](#1-installation)
1. [Configuration](#2-configuration)
1. [Usage](#3-usage)
1. [Support](#4-support)
1. [Contribute](#5-contribute)

## 1. Installation

- Choose your version according to the version compatibility matrix:

| Laravel | Lumen | Package
|:--------|:------|:----------
| 8.0.x   | 8.0.x | main
| 9.0.x   | 9.0.x | main

- Add the following line in the `require-dev` array of the `composer.json` file and replace the version if needed according to your Laravel version:
    ```php
    "keypoint-solutions/laravel-localization-helpers" : "3.0.*"
    ```

- Update your installation : `composer update`
- For Laravel, add the following line in the `providers` array of the `config/app.php` configuration file :
    ```php
    \LaravelLocalizationHelpersServiceProvider::class,
    ```

- For Lumen, add the following lines in the `bootstrap/app.php` file :
  ```php
  $app->register( \LaravelLocalizationHelpersServiceProvider::class );
  $app->configure('laravel-localization-helpers');
  ```

- Now execute `php artisan list` and you should view the new *localization* commands:
    ```
    ...
    localization
    localization:clear          Remove lang backup files
    localization:find           Display all files where the argument is used as a lemma
    localization:missing        Parse all translations in app directory and build all lang files
    ...
    ```

In Laravel, you can add the facade in the Aliases if you need to manage translations in your code :

```php
'LocalizationHelpers' => Facade\LocalizationHelpers::class
```

## 2. Configuration

To configure your fresh installed package, please create a configuration file by executing :

```bash
php artisan vendor:publish
```

Then you can modify the configuration in file :

```bash
config/laravel-localization-helpers.php
```

Add new folders to search for, add your own lang methods or functions, ...

### Backup files

You should not include backup lang files in GIT or other versioning systems.

In your `laravel` folder, add this in `.gitignore` file :

```bash
# Do not include backup lang files
resources/lang/*/[a-zA-Z]*20[0-9][0-9][0-1][0-9][0-3][0-9]_[0-2][0-9][0-5][0-9][0-5][0-9].php
```

## 3. Usage

### 3.1 Command `localization:missing`

This command parses all your code and generates translations according to lang files in all `lang/XXX/` directories.

Use `php artisan help localization:missing` for more informations about options.

#### *Examples*

##### Generate all lang files

```bash
php artisan localization:missing
```

##### Generate all lang files without prompt

```bash
php artisan localization:missing -n
```

##### Generate all lang files without backuping old files

```bash
php artisan localization:missing -b
```

##### Generate all lang files with automatic translations

```bash
php artisan localization:missing -t
```

##### Generate all lang files without keeping obsolete lemmas

```bash
php artisan localization:missing -o
```

##### Generate all lang files without any comment for new found lemmas

```bash
php artisan localization:missing -c
```

##### Generate all lang files without header comment

```bash
php artisan localization:missing -d
```

##### Generate all lang files and set new lemma values

3 commands below produce the same output:

```bash
php artisan localization:missing
php artisan localization:missing -l
php artisan localization:missing -l "TODO: %LEMMA"
```

You can customize the default generated values for unknown lemmas.

The following command let new values empty:

```bash
php artisan localization:missing -l ""
```

The following command prefixes all lemma values with "Please translate this : "

```bash
php artisan localization:missing -l "Please translate this : %LEMMA"
```

The following command set all lemma values to null to provide fallback translations to all missing values.

```bash
php artisan localization:missing -l null
```

The following command set all lemma values to "Please translate this !"

```bash
php artisan localization:missing -l 'Please translate this !'
```

##### Silent option for shell integration

```bash
#!/bin/bash

php artisan localization:missing -s
if [ $? -eq 0 ]; then
echo "Nothing to do dude, GO for release"
else
echo "I will not release in production, lang files are not clean"
fi
```

##### Simulate all operations (do not write anything) with a dry run

```bash
php artisan localization:missing -r
```

##### Open all must-edit files at the end of the process

```bash
php artisan localization:missing -e
```

You can edit the editor path in your configuration file. By default, editor is *Sublime Text* on *Mac OS X* :

```php
'editor_command_line' => '/Applications/Sublime\\ Text.app/Contents/SharedSupport/bin/subl'
```

For *PHPStorm* on *Mac OS X*:

```php
'editor_command_line' => '/usr/local/bin/phpstorm'
```

### 3.2 Command `localization:find`

This command will search in all your code for the argument as a lemma.

Use `php artisan help localization:find` for more informations about options.

#### *Examples*

##### Find regular lemma

```bash
php artisan localization:find Search
```

##### Find regular lemma with verbose

```bash
php artisan localization:find -v Search
```

##### Find regular lemma with short path displayed

```bash
php artisan localization:find -s "Search me"
```

##### Find lemma with a regular expression

```bash
php artisan localization:find -s -r "@Search.*@"
php artisan localization:find -s -r "/.*me$/"
```

> PCRE functions are used

### 3.3 Command `localization:clear`

This command will remove all backup lang files.

Use `php artisan help localization:clear` for more informations about options.

#### *Examples*

##### Remove all backups

```bash
php artisan localization:clear
```

##### Remove backups older than 7 days

```bash
php artisan localization:clear -d 7
```

## 4. Support

Use the [github issue tool](https://github.com/keypoint-solutions/laravel-localization-helpers/issues) to open an issue or ask for something.

## 5. Contribute

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Added some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request
