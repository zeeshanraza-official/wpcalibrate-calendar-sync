/**
 * WPCalibrate Calendar Sync - Frontend JS
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Interactive month view cell focus and accessibility enhancements.
    var cells = document.querySelectorAll('.wpcs-month-table .wpcs-cell-day');
    cells.forEach(function (cell) {
      cell.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          var firstLink = cell.querySelector('.wpcs-event-name');
          if (firstLink) {
            firstLink.click();
          }
        }
      });
    });
  });
})();
