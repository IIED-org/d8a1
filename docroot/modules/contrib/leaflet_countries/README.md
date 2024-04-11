# Leaflet Countries module

## Install

This module is best installed with composer.

Run `composer require drupal/leaflet_countries` to install the module.

## Usage

After enabling the module you need to add a field to the content type you want
to use to represent a country. The field to add is labeled 'Country (leaflet
map)'.

In your content type's 'Manage display' settings you may choose to output the
field as a 3 letter country code or more usefully as a rendered country map by
choosing the appropriate format.

Create a piece of content and associate it using the new field to a country.

View your new node and you should see the field output either as a map or a
three letter country code.

## Views integration

This currently doesn't work and needs to be replaced.
