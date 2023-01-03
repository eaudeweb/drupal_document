(function ($, Drupal) {

  Drupal.AjaxCommands.prototype.downloadFileCommand = function (ajax, response) {
    window.open(response.filePath, '_blank');
  };

}(jQuery, Drupal));
