(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.documentsForm = {
    attach: function (context) {
      $(once('documentsBulkTable', '.documents-bulk-form>table'))
          .each(function () {
            var columns = $(this).find('>thead >tr th').length;
            var emptyTable = $(this).find('td.views-empty').length !== 0;
            if (emptyTable) {
              while (--columns) {
                // dataTables crashes if number of thead columns != number of tbody columns
                $(this).find('>tbody >tr').append('<td class=\'hidden\'></td>');
              }
            }
          });
      $('.dataTables_wrapper input[type="search"]').on('keyup', function (e) {
        var parentForm = $(this).closest('.documents-bulk-form');
        rebuildFormElements(parentForm);
      });
      $(once('bindToDownloadForm', '.download-documents-clear-button'))
          .on('click', function (event) {
            event.preventDefault();
            var parentForm = $(this).closest('form.documents-bulk-form');
            var checkboxes = parentForm.find('table').find('input[type="checkbox"]');
            uncheckCheckboxes(checkboxes);
            rebuildFormElements(parentForm);
          });

      function uncheckCheckboxes(checkboxes) {
        checkboxes.each(function () {
          $(this).prop('checked', false).trigger('change');
        });
      }

      var index = 0;
      jQuery('.documents-bulk-form table').each(function () {
        var id = 'select-all-elems_' + index;
        index++;
        jQuery(this).find('th.select-all input')
            .attr('id', id)
            .after('<label for="' + id + '"></label>')
            .bind('click', function (event) {
              if (jQuery(event.target).is('input[type="checkbox"]')) {
                var checkboxes = jQuery(this).closest('table').find('tbody input[type="checkbox"]');
                checkboxes.each(function () {
                  var $checkbox = jQuery(this);
                  var stateChanged = $checkbox.prop('checked') !== event.target.checked;
                  if (stateChanged) {
                    $checkbox.prop('checked', event.target.checked).trigger('change');
                  }
                  $checkbox.closest('tr').toggleClass('selected', this.checked);
                });
                event.stopPropagation();
              }
            });
        jQuery(this).find('input[type="checkbox"]').bind('click', function (event) {
          var parentForm = jQuery(this).closest('.documents-bulk-form');
          rebuildFormElements(parentForm);
        });
      });
    }
  };

  function rebuildFormElements(parentForm) {
    var selected = parentForm.find('input[type="checkbox"][title!="Deselect all rows in this table"][title!="Select all rows in this table"]:checked');
    var selectedCount = 0;
    jQuery.each(selected, function (key, element) {
      var parentType = jQuery(element).parent().parent().parent().prop('nodeName');
      if (parentType !== 'CAPTION' && parentType !== 'DIV' && parentType !== 'THEAD') {
        selectedCount++;
      }
    });
    var submitButton = parentForm.find('input[type="submit"]');
    var clearButton = parentForm.find('a.download-documents-clear-button');
    var title = Drupal.t("No documents selected");
    if (selectedCount !== 0) {
      title = selectedCount === 1 ? Drupal.t('Download one document') : Drupal.t("Download @count documents", {
        '@count': selectedCount
      });
      submitButton.attr("value", title);
      submitButton.attr("disabled", false);
      submitButton.removeClass("is-disabled");
      submitButton.removeClass("hidden");
      clearButton.removeClass("hidden");
    } else {
      submitButton.attr("value", title);
      submitButton.attr("disabled", true);
      submitButton.addClass("is-disabled");
      submitButton.addClass("hidden");
      clearButton.addClass("hidden");
    }
  }

}(jQuery, Drupal, once));
