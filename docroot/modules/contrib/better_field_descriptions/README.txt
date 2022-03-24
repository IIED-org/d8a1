Readme file for the Better Descriptions module for Drupal
-------------------------------------------------------

CONTENTS OF THIS FILE
---------------------

 * INTRODUCTION
 * REQUIREMENTS
 * INSTALLATION
 * CONFIGURATION
 * HOW IT WORKS
 * KNOWN UNKNOWNS
 * CONTACT

INTRODUCTION
------------

 - Better field descriptions makes it possible to add themeable descriptions
   to fields in forms.

   Would your customers like to write their own descriptions, but can't or
   won't, since they then need permission to manage the fields in the content
   type? Would you like to show the description above the field or even between
   the title and the field? This module allows you to select the fields you
   want across all content types and give those field a themeable description
   on a separate admin page.

   Works on any field!

   Better field descriptions provides two permissions. One for selecting
   which fields across all content types that should have a "better field
   description", and one for adding/editing the descriptions themselves.

REQUIREMENTS
------------

 - No dependencies.

INSTALLATION
------------

 - Extract the files in your module directory (typically modules/contrib )
 - Visit the modules page and enable the module
 - From the modules page you will find links to
    - permission settings
    - configuration
    - help

CONFIGURATION
------------

 1. Enable the module using drush or by going to `admin/modules`.
 2. Go to `/admin/config/content/better_field_descriptions` for configurations
    related to better field descriptions module.

HOW IT WORKS
-------------
 Users having the permission 'Administer better field descriptions' will get
 three forms to allow configuration of better_fields_descriptions settings.
 1. Entities: select the entity types that will be used for better field
    descriptions.
 2. Settings: select the entity bundles, and fields within each bundle that
   will be used for better field descriptions.
 3. Bundles: specify the better field description text, along with the label
   and position to display the description.

 Users having the permission 'Add a better descriptions to selected fields' can
 then add descriptions in a different form for all selected fields.

 Both users also need the permission 'Use the administration pages and help'.

 Additionally, you can select if the individual description should be placed
 above or below the field itself.

 You can also provide a label for each description or use a default for all
 descriptions.

 Descriptions are rendered via a template file. There are two provided with the
 module; better-field-descriptions-text and better-field-descriptions-fieldset.

 The first provides a simple and clean text, the second wraps the description
 in a fieldset using the label as the legend of the fieldset.

 You can make your own template and put it in the templates folder of your
 theme. The new template will be picked up automatically. Note that changing
 the template will trigger a theme registry rebuild.

 If you create a template that can be useful for others, please share it in the
 issue queue and they might be included in the module with proper attribution.

KNOWN UNKNOWNS
---------------
 Better Description has not been thoroughly tested and never on multilingual
 sites.

CONTACT
--------
 Please provide feedback to the issue queue if you have problems, comments,
 patches, praise or ideas.
