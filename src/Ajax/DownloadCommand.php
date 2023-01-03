<?php

namespace Drupal\drupal_document\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * An AJAX command for download files via ajax.
 */
class DownloadCommand implements CommandInterface {

  /**
   * @var string
   */
  protected $filePath;

  /**
   * {@inheritdoc}
   */
  public function __construct($filePath) {
    $this->filePath = $filePath;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'downloadFileCommand',
      'filePath' => $this->filePath,
    ];
  }

}
