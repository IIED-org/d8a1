<?php

namespace Drupal\leaflet_countries;

/**
 * Contains helper methods for accessing GeoJSON library data.
 *
 * @package Drupal\leaflet_countries
 */
class Countries {

  /**
   * Loads a specific file from the countries library and returns the JSON.
   *
   * @param string $path
   *   The name of the file to load.
   *
   * @return string
   *   The JSON string contained in the loaded file.
   */
  protected static function getJsonContent($path): string {

    // Try loading from libraries and then vendor.
    $realpath = \Drupal::service('file_system')->realpath('libraries/countries/' . $path);
    if (!$realpath) {
      $realpath = \Drupal::service('file_system')->realpath('../vendor/mledoze/countries/' . $path);
    }
    if (!$realpath) {
      return '';
    }

    return file_get_contents($realpath);
  }

  /**
   * Loads the countries JSON file from the filesystem.
   *
   * @return string
   *   The contents of the JSON file.
   */
  protected static function getCountriesJson(): string {
    return self::getJsonContent('countries.json');
  }

  /**
   * Decodes the countries JSON data and returns it.
   *
   * @return mixed
   *   The country data.
   */
  public static function getCountries(): mixed {
    $countries = json_decode(self::getCountriesJson());
    return $countries;
  }

  /**
   * Gets the country codes and returns an array of them.
   *
   * @return string[]
   *   A simple array of country codes.
   */
  public static function getCodes(): array {
    $list = self::getCodesAndLabels();
    $codes = [];
    foreach ($list as $code => $name) {
      $codes[] = $code;
    }
    return $codes;
  }

  /**
   * Gets the country codes and labels and returns a keyed value pair.
   *
   * @return string[]
   *   An array with the key being the country code and the value being the
   *   country's common name.
   */
  public static function getCodesAndLabels(): array {
    $list = self::getCountries();
    $labels = [];
    foreach ($list as $item) {
      $labels[$item->cca3] = $item->name->common;
    }
    asort($labels);
    return $labels;
  }

  /**
   * Gets an individual country's JSON data.
   *
   * @param string $code
   *   The country code of the country whose data should be returned.
   *
   * @return string
   *   The JSON string for the country.
   */
  public static function getIndividualCountryJson($code): string {
    $filepath = 'data/' . strtolower($code) . '.topo.json';
    return self::getJsonContent($filepath);
  }

  /**
   * Gets an individual country's cca3 code from its cca2 code.
   *
   * @param string $cca2
   *   The cca2 code of the country whose data should be returned.
   *
   * @return string
   *   The cca3 code for the country.
   */
  public static function getCca3($cca2) {
    $list = self::getCountries();
    $cca3 = [];
    foreach ($list as $item) {
      $cca3[$item->cca2] = $item->cca3;
    }
    return $cca3[$cca2] ?? '';
  }

}
