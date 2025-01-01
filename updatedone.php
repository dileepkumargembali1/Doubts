<?php

namespace Drupal\notfoundpassthrough\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AliasManagerInterface $alias_manager,
    PathValidatorInterface $path_validator,
    RequestContext $request_context
  ) {
    parent::__construct($config_factory);

    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('router.request_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['notfoundpassthrough.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'notfoundpassthrough_settings';
  }

  /**
   * Builds the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('notfoundpassthrough.settings');

    $form = [];
    $form['redirect_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Redirect options'),
    ];

    $form['redirect_options']['servers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Legacy servers'),
      '#description' => $this->t('Enter server addresses. One per line.'),
      '#default_value' => $settings->get('servers'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates the settings form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (($value = $form_state->getValue('site_404')) && $value[0] !== '/') {
      $form_state->setErrorByName('site_404', $this->t("The path '%path' must start with a slash.", ['%path' => $value]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('notfoundpassthrough.settings')
      ->set('servers', $form_state->getValue('servers'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
