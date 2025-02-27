
(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.documentsForm = {
    attach: function (context) {
      once('documentsBulkTable', '.documents-bulk-form>table', context).forEach((table) => {
        var columns = $(table).find('>thead >tr th').length;
        var emptyTable = $(table).find('td.views-empty').length !== 0;
        if (emptyTable) {
          while (--columns) {
            // dataTables crashes if number of thead columns != number of tbody columns
            $(table).find('>tbody >tr').append('<td class=\'hidden\'></td>');
          }
        }
      });

      $('.dataTables_wrapper input[type="search"]').on('keyup', function (e) {
        var parentForm = $(this).closest('.documents-bulk-form');
        rebuildFormElements(parentForm);
      });

      once('bindToDownloadForm', '.download-documents-clear-button', context).forEach((button) => {
        $(button).on('click', function (event) {
          event.preventDefault();
          var parentForm = $(this).closest('form.documents-bulk-form');
          var checkboxes = parentForm.find('table').find('input[type="checkbox"]');
          uncheckCheckboxes(checkboxes);
          rebuildFormElements(parentForm);
        });
      });

      function uncheckCheckboxes(checkboxes) {
        checkboxes.each(function () {
          $(this).prop('checked', false).trigger('change');
        });
      }

      var index = 0;
      once('selectAllElems', '.documents-bulk-form table', context).forEach((table) => {
        var id = 'select-all-elems_' + index;
        index++;

        $(table).find('th.select-all input')
          .attr('id', id)
          .after('<label for="' + id + '"></label>')
          .on('click', function (event) {
            if ($(event.target).is('input[type="checkbox"]')) {
              var checkboxes = $(this).closest('table').find('tbody input[type="checkbox"]');
              checkboxes.each(function () {
                var $checkbox = $(this);
                var stateChanged = $checkbox.prop('checked') !== event.target.checked;
                if (stateChanged) {
                  $checkbox.prop('checked', event.target.checked).trigger('change');
                }
                $checkbox.closest('tr').toggleClass('selected', this.checked);
              });
              event.stopPropagation();
            }
          });

        $(table).find('input[type="checkbox"]').on('click', function () {
          var parentForm = $(this).closest('.documents-bulk-form');
          rebuildFormElements(parentForm);
        });
      });
    }
  };

  function rebuildFormElements(parentForm) {
    var selected = parentForm.find('input[type="checkbox"][title!="Deselect all rows in this table"][title!="Select all rows in this table"]:checked');
    var selectedCount = 0;

    $.each(selected, function (key, element) {
      var parentType = $(element).parent().parent().parent().prop('nodeName');
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
      submitButton.attr("value", title).attr("disabled", false).removeClass("is-disabled hidden");
      clearButton.removeClass("hidden");
    } else {
      submitButton.attr("value", title).attr("disabled", true).addClass("is-disabled hidden");
      clearButton.addClass("hidden");
    }
  }

}(jQuery, Drupal, once));
