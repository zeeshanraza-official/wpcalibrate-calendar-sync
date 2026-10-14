/**
 * WPCalibrate Calendar Sync - Admin JavaScript
 */

(function ($) {
  'use strict';

  $(document).ready(function () {
    // Copy to clipboard handler.
    $('.wpcs-btn-copy').on('click', function (e) {
      e.preventDefault();
      var targetId = $(this).data('target');
      var $target = $('#' + targetId);
      var $btn = $(this);
      var originalText = $btn.text();

      if ($target.length) {
        var textToCopy = $target.val() || $target.text();

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(textToCopy).then(function () {
            $btn.text(wpcsAdminData.i18n.copied);
            setTimeout(function () {
              $btn.text(originalText);
            }, 2000);
          }).catch(function () {
            fallbackCopy($target, $btn, originalText);
          });
        } else {
          fallbackCopy($target, $btn, originalText);
        }
      }
    });

    function fallbackCopy($target, $btn, originalText) {
      $target.select();
      try {
        document.execCommand('copy');
        $btn.text(wpcsAdminData.i18n.copied);
      } catch (err) {
        $btn.text(wpcsAdminData.i18n.copyFailed);
      }
      setTimeout(function () {
        $btn.text(originalText);
      }, 2000);
    }
  });
})(jQuery);
