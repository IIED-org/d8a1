/**
 * @file
 */

(function ($, Drupal) {
  Drupal.behaviors.rhNode = {
    attach(context, settings) {
      // Display the action in the vertical tab summary.
      $(context)
        .find('.rabbit-hole-settings-form')
        .drupalSetSummary(function (context) {
          const $action = $(
            '.rabbit-hole-action-setting input:checked',
            context,
          );
          const label = $action.next('label')[0];
          return Drupal.checkPlain(label ? label.textContent : '');
        });
    },
  };
})(jQuery, Drupal);
