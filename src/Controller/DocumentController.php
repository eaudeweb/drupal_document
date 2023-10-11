<?php

namespace Drupal\drupal_document\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\drupal_document\Form\DownloadDocumentsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for documents.
 */
class DocumentController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new \Drupal\Core\Controller\FormController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function modal(ContentEntityInterface $entity, $entity_type = 'node', string $field_name = NULL) {
    $form = $this->formBuilder->getForm(DownloadDocumentsForm::class, $entity_type, [$entity->id()], $field_name, $entity_type);

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(NULL, $form, [
      'height' => 600,
      'width' => 900,
      'dialogClass' => 'no-titlebar',
    ]));
    return $response;
  }

}
