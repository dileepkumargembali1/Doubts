<?php

namespace Drupal\notfoundpassthrough\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'notfoundpassthrough_settings';
  }

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
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AliasManagerInterface $alias_manager,
    PathValidatorInterface $path_validator,
    RequestContext $request_context,
    TypedConfigManagerInterface $typed_config_manager
  ) {
    parent::__construct($config_factory);

    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
    $this->typedConfigManager = $typed_config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('router.request_context'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['notfoundpassthrough.settings'];
  }

  /**
   * Display Redirects 404 settings form.
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
      '#description' => $this->t('Please enter server addresses (ex: http://legacy.example.com). If you exclude the protocol, it will be preserved based on the current connection type. Enter one server per line, the order determines the preference. If you require the request URI to be a part of a longer address, use the [request_uri] token (ex: http://legacy.example.com/[request_uri]/index.html). Please note that the resulting URI will be verified prior to the redirect.'),
      '#default_value' => $settings->get('servers'),
    ];

    // Additional fields here...

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates the submitted settings form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('site_404')) {
      $form_state->setValueForElement($form['redirect_options']['site_404'], $this->aliasManager->getPathByAlias($form_state->getValue('site_404')));
    }
    if (($value = $form_state->getValue('site_404')) && $value[0] !== '/') {
      $form_state->setErrorByName('site_404', $this->t("The path '%path' has to start with a slash.", ['%path' => $value]));
    }
    if (!$form_state->isValueEmpty('site_404') && !$this->pathValidator->isValid($form_state->getValue('site_404'))) {
      $form_state->setErrorByName('site_404', $this->t("Either the path '%path' is invalid or you do not have access to it.", ['%path' => $form_state->getValue('site_404')]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('notfoundpassthrough.settings')
      ->set('servers', $form_state->getValue('servers'))
      ->set('search', $form_state->getValue('search'))
      ->set('redirect', $form_state->getValue('redirect'))
      ->set('site_404', $form_state->getValue('site_404'))
      ->set('save_redirect', $form_state->getValue('save_redirect'))
      ->set('redirect_code', $form_state->getValue('redirect_code'))
      ->set('force_redirect_code', $form_state->getValue('force_redirect_code'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
