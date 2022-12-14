<?php

namespace Drupal\drupal_document\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\drupal_document\Ajax\DownloadCommand;
use Drupal\drupal_document\Services\DocumentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
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
   * @inheritDoc
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DocumentManager $documentManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->documentManager = $documentManager;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('document.manager')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'download_documents.form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entityIds = NULL, $fieldName = NULL) {
    $form['#prefix'] = '<div id="download-documents-modal">';
    $form['#suffix'] = '</div>';
    $this->fieldName = $fieldName ?? $form_state->getUserInput()['entity_field_name'];
    $this->entityIds = $entityIds ?? explode(' ', $form_state->getUserInput()['entity_ids']);
    $availableLanguages = $availableFormats = [];
    $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($this->entityIds);
    foreach ($entities as $entity) {
      $langcodes = array_keys($entity->getTranslationLanguages());
      foreach ($langcodes as $langcode) {
        $urls = $this->documentManager->getFilesByLanguage($entity, $langcode, $this->fieldName);
        if (empty($urls)) {
          continue;
        }
        $availableLanguages[] = $langcode;
        foreach ($urls as $url) {
          $availableFormats[] = $this->documentManager->getUriType($url);
        }
      }
    }
    $availableFormats = array_unique($availableFormats);
    $availableLanguages = array_unique($availableLanguages);

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
    ];
    if (empty($languageOptions)) {
      $form['warning_messages'] = [
        '#type' => 'container',
        '#markup' => $this->t('Couldn\'t find any file to download!'),
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
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];
    if ($form_state->getErrors()) {
      $response->addCommand(new PrependCommand('#download-documents-header', $form['status_messages']));
      return $response;
    }
    $filesUrls = $this->getSelectedFiles($form_state);
    $path = (count($filesUrls) < 2) ? $this->documentManager->downloadFile($filesUrls) : $this->documentManager->archiveFiles($filesUrls);
    if (empty($path)) {
      $response->addCommand(new PrependCommand('#download-documents-header', $form['status_messages']));
      return $response;
    }
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new DownloadCommand($path));

    return $response;
  }

  /**
   * Returns an array of filtered files.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSelectedFiles(FormStateInterface $formState) {
    $urls = [];
    $formats = array_filter($formState->getUserInput()['format']);
    $languages = array_filter($formState->getUserInput()['language']);
    $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($this->entityIds);
    foreach ($entities as $entity) {
      foreach ($languages as $language) {
        if (!$entity->hasTranslation($language)) {
          continue;
        }
        $urls = array_merge($urls, $this->documentManager->getFilesByLanguage($entity, $language, $this->fieldName));
      }
    }

    foreach ($urls as $key => $url) {
      if (!in_array($this->documentManager->getUriType($url), $formats)) {
        unset($urls[$key]);
      }
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
