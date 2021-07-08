CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Field States UI allows sites builders with minimal PHP/Dev skills to configure
the Field States API. This lets you configure a field to for example hide if
another field has a certain value or hasn't been filled. While doing it via PHP
can be more powerful the UI can be very handy and in some cases quicker.
Originally developed for Metchosin Karate for their student signup forms.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/field_states_ui

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/field_states_ui


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Field States UI module as you would normally install a
   contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Configuration can be accessed per field instance wherever that field
       instance is usually configured. For example for the Node type Article
       that would be at /admin/structure/types/manage/article/form-display.
    3. Use the contextual gear icon to edit to edit a field.
    4. There is now a Manage Fields States field set, select a new field set.
       Update and save.


MAINTAINERS
-----------

 * Nick Wilde (NickWilde) - https://www.drupal.org/u/nickwilde
