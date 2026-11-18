(($, Drupal, once) => {
  Drupal.behaviors.password_policy_extras = {
    attach(context, settings) {
      once(
        'password_policy_extras',
        'input.js-password-field',
        context,
      ).forEach((passwordInput) => {
        const $passwordInput = $(passwordInput);

        if (settings.password_policy_extras.display_status_after_pass) {
          // Move the table status div just below the password field.
          const passwordPolicyStatus = document.querySelector(
            '#password-policy-status',
          );
          const passwordInputParent = passwordInput.parentElement;
          passwordInputParent.appendChild(passwordPolicyStatus);
        }

        // Accessibility improvements.
        passwordInput.setAttribute(
          'aria-describedby',
          'password-policy-status',
        );
        // Core disables the element that triggered an Ajax request, so the
        // password field would lose the focus while its status is refreshed.
        // Keep it enabled, for its own request only: the other Ajax elements
        // of the form, like the role checkboxes, must not move the focus.
        const passwordAjax = Drupal.ajax.instances.find(
          (instance) => instance && instance.element === passwordInput,
        );
        if (passwordAjax) {
          passwordAjax.beforeSend = function keepPasswordInputEnabled(...args) {
            const hadFocus = document.activeElement === passwordInput;
            Drupal.Ajax.prototype.beforeSend.apply(this, args);
            passwordInput.disabled = false;
            if (hadFocus && document.activeElement !== passwordInput) {
              passwordInput.focus();
            }
          };
        }
        const delay = settings.password_policy_extras.status_refresh_delay;
        if (parseInt(delay, 10) > 0) {
          let timeout = null;
          // The 'input' event fires when the value of the input has been
          // changed as a direct action of a user such as typing.
          $passwordInput.on('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
              // Triggers the default Password Policy 'change' Ajax handling.
              $passwordInput.trigger('change');
            }, delay);
          });
        }
        // Add CSS classes to the status table to indicate if then password
        // field is empty or not.
        $passwordInput.on('input', () => {
          if (passwordInput.value > '') {
            $('#password-policy-status')
              .parent()
              .addClass('password-not-empty')
              .removeClass('password-empty');
          } else {
            $('#password-policy-status')
              .parent()
              .addClass('password-empty')
              .removeClass('password-not-empty');
          }
        });
        // Show status table on password field focus.
        if (settings.password_policy_extras.display_status_on_focus) {
          $passwordInput.on('focus', () => {
            $('#password-policy-status > div').show();
          });
        }
      });
    },
  };
})(jQuery, Drupal, once);
