<?php

namespace Drupal\drupal_document\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\drupal_document\Ajax\DownloadCommand;
use Drupal\drupal_document\Services\DocumentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to download documents.
 */
class DownloadDocumentsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A list with nodes selected. One single node if form is called from modal.
   *
   * @var array*/
  protected $entityIds = [];

  /**
   * The field name passed from formatter.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The document manager.
   *
   * @var \Drupal\drupal_document\Services\DocumentManager*/
  protected $documentManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DocumentManager $documentManager, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->documentManager = $documentManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('document.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_document_download_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entityIds = NULL, $fieldName = NULL) {
    $form['#prefix'] = '<div id="download-documents-modal">';
    $form['#suffix'] = '</div>';
    $this->fieldName = $fieldName ?? $form_state->getUserInput()['entity_field_name'];
    $this->entityIds = $entityIds ?? explode(' ', $form_state->getUserInput()['entity_ids']);
    [$availableFormats, $availableLanguages] = $this->documentManager->getOptions($this->entityIds, $this->fieldName);
    $linksFieldName = 'field_external_links';
    // Allow other modules to alter the machine name for external links.
    $this->alterExternalLinkField($linksFieldName);
    $form['status_message'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#prefix' => "<div id='download-documents-header'>",
      '#suffix' => '</div>',
    ];
    $form['entity_type_id'] = [
      '#type' => 'hidden',
      '#value' => 'node',
    ];
    $form['entity_field_name'] = [
      '#type' => 'hidden',
      '#value' => $this->fieldName,
    ];
    $form['entity_ids'] = [
      '#type' => 'hidden',
      '#value' => $this->entityIds,
    ];
    $icons = $this->documentManager->getIcons($availableFormats);
    $form['format'] = [
      '#type' => 'checkboxes',
      '#options' => $icons,
      '#title' => $this->t('Document type'),
      '#required' => TRUE,
      '#description' => $this->t('Select at least one format'),
      '#access' => !empty($this->entityIds) && !empty($icons),
      '#attributes' => ['class' => ['download-from-formats']],
      '#default_value' => count($icons) > 1 ? [] : array_keys($icons),
      '#disabled' => !(count($icons) > 1),
      '#required_error' => $this->t('Format is required. Select at least one format.'),
    ];
    $languageOptions = $this->documentManager->getFilteredLanguages($availableLanguages);
    $form['language'] = [
      '#type' => 'checkboxes',
      '#options' => $languageOptions,
      '#attributes' => ['class' => ['download-from-languages']],
      '#title' => $this->t('Language'),
      '#required' => TRUE,
      '#description' => $this->t('Select at least one language'),
      '#access' => !empty($this->entityIds) && !empty($languageOptions),
      '#default_value' => count($languageOptions) > 1 ? [] : array_keys($languageOptions),
      '#disabled' => !(count($languageOptions) > 1),
      '#required_error' => $this->t('Language is required. Select at least one language.'),
    ];
    $links = $this->documentManager->getExternalLinks($this->entityIds, $linksFieldName);
    if ($links) {
      $form['external_links'] = [
        '#theme' => 'item_list',
        '#items' => $links,
        '#title' => $this->t('See external documents'),
        '#type' => 'ul',
        '#context' => ['list_style' => 'comma-list'],
        '#attributes' => ['class' => ['open-website']],
        '#wrapper_attributes' => ['class' => 'container'],
        '#access' => !empty($this->entityIds) && !empty($links),
      ];
    }
    if (empty($languageOptions) && empty($links)) {
      $form['warning_messages'] = [
        '#type' => 'container',
        '#markup' => $this->t("Couldn't find any file to download!"),
        '#attributes' => [
          'class' => ['alert alert-danger'],
        ],
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download'),
      '#access' => !empty($this->entityIds) && !empty($languageOptions),
      '#attributes' => [
        'class' => ['use-ajax', 'download-button', 'btn-green'],
      ],
      '#ajax' => [
        'callback' => [$this, 'ajaxSubmit'],
        'event' => 'click',
        'url' => Url::fromRoute('document.download_documents_form'),
        'options' => [
          'query' => [
            'ajax_form' => 1,
          ],
        ],
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['use-ajax', 'cancel-button', 'btn-green'],
        'onclick' => 'jQuery(".ui-dialog-titlebar-close").click();',
      ],
    ];
    $form['actions']['#type'] = 'container';
    $form['actions']['#attributes']['class'][] = 'form-actions';
    $form['#attributes']['class'][] = 'download-form';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'drupal_document/download';

    return $form;
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('.messages__wrapper'));
    $form['status_message'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];
    if ($form_state->getErrors()) {
      $response->addCommand(new PrependCommand('#download-documents-header', $form['status_message']));
      return $response;
    }
    $this->preselectDefaultValues($form, $form_state);
    $formats = array_filter($form_state->getUserInput()['format']);
    $languages = array_filter($form_state->getUserInput()['language']);
    $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($this->entityIds);
    $filesUrls = $this->documentManager->getFilteredFiles(array_keys($entities), $this->fieldName, $formats, $languages);
    $path = (count($filesUrls) < 2) ? $this->documentManager->downloadFile($filesUrls) : $this->documentManager->archiveFiles($filesUrls);
    if (empty($path)) {
      $response->addCommand(new PrependCommand('#download-documents-header', $form['status_message']));
      return $response;
    }
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new DownloadCommand($path));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function preselectDefaultValues(array &$form, FormStateInterface &$formState) {
    foreach (['language', 'format'] as $element) {
      $value = $formState->getValue($element);
      if (count($value) < 2) {
        $userInput = $formState->getUserInput();
        $userInput[$element] = $value;
        $formState->setUserInput($userInput);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function alterExternalLinkField(string &$linksFieldName = 'field_external_links') {
    $this->moduleHandler->alter('field_external_links', $linksFieldName);
  }

}
