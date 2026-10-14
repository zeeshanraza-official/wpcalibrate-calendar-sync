(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element) {
    return;
  }

  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;
  var ToggleControl = wp.components.ToggleControl;
  var __ = wp.i18n.__;

  registerBlockType('wpcalibrate/calendar', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;

      return el(
        wp.element.Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: __('Calendar Settings', 'wpcalibrate-calendar-sync'), initialOpen: true },
            el(SelectControl, {
              label: __('Display View', 'wpcalibrate-calendar-sync'),
              value: attributes.view,
              options: [
                { label: __('Agenda (List)', 'wpcalibrate-calendar-sync'), value: 'agenda' },
                { label: __('Month Grid', 'wpcalibrate-calendar-sync'), value: 'month' }
              ],
              onChange: function (val) {
                setAttributes({ view: val });
              }
            }),
            el(TextControl, {
              label: __('Calendar IDs (comma separated, empty for all)', 'wpcalibrate-calendar-sync'),
              value: attributes.calendars,
              onChange: function (val) {
                setAttributes({ calendars: val });
              }
            }),
            el(TextControl, {
              label: __('Max Events (Agenda view)', 'wpcalibrate-calendar-sync'),
              type: 'number',
              value: attributes.limit,
              onChange: function (val) {
                setAttributes({ limit: parseInt(val, 10) || 20 });
              }
            }),
            el(ToggleControl, {
              label: __('Show Event Description', 'wpcalibrate-calendar-sync'),
              checked: attributes.show_description,
              onChange: function (val) {
                setAttributes({ show_description: val });
              }
            }),
            el(ToggleControl, {
              label: __('Show Event Location', 'wpcalibrate-calendar-sync'),
              checked: attributes.show_location,
              onChange: function (val) {
                setAttributes({ show_location: val });
              }
            })
          )
        ),
        el(
          'div',
          { className: 'wpcs-block-preview' },
          el('div', { className: 'wpcs-block-preview-badge' }, __('WPCalibrate Calendar', 'wpcalibrate-calendar-sync')),
          el('p', { className: 'wpcs-block-preview-title' },
            __('View: ', 'wpcalibrate-calendar-sync') + (attributes.view === 'month' ? __('Month Grid', 'wpcalibrate-calendar-sync') : __('Agenda List', 'wpcalibrate-calendar-sync'))
          ),
          el('p', { className: 'wpcs-block-preview-info' },
            __('Calendar will render synchronized events on the frontend.', 'wpcalibrate-calendar-sync')
          )
        )
      );
    },
    save: function () {
      // Dynamic server rendered block.
      return null;
    }
  });
})(window.wp);
