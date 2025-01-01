<?php

namespace Drupal\notfoundpassthrough\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'notfoundpassthrough_settings';
  }

  /**
   * Constructs a SiteInformationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasManagerInterface $alias_manager, PathValidatorInterface $path_validator, RequestContext $request_context,TypedConfigManagerInterface $typedConfigManager) {
    parent::__construct($config_factory,$typedConfigManager);

    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
    $this->typedConfigManager = $typedConfigManager;
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
      '#title' => t('Redirect options'),
    ];

    $form['redirect_options']['servers'] = [
      '#type' => 'textarea',
      '#title' => t('Legacy servers'),
      '#description' => t('Please enter server addresses (ex: http://legacy.example.com). If you exclude the protocol, it will be preserved based on the current connection type. Enter one server per line, the order determines the preference. If you require the request URI to be a part of a longer address, use the [request_uri] token (ex: http://legacy.example.com/[request_uri]/index.html). Please note that the resulting URI will be verified prior to the redirect. '),
      '#default_value' => $settings->get('servers'),
    ];

    $form['redirect_options']['search'] = [
      '#type' => 'textfield',
      '#title' => t('Search path'),
      '#description' => t('Please enter the search path (ex: http://search.example.com) you wish to use if no legacy servers are found. If you require the request URI to be a part of a longer address, use the [request_uri] token (ex: http://search.example.com/[request_uri]/results)'),
      '#default_value' => $settings->get('search'),
    ];

    $form['redirect_options']['redirect'] = [
      '#type' => 'textfield',
      '#title' => t('Direct redirect'),
      '#description' => t('If no options above have resulted in a successful redirect, specify a path to redirect the user to (without checking if it results in a valid page). The Request URI is not included in this redirect, unless specified with the [request_uri] token (ex: http://legacy.example.com/[request_uri]).'),
      '#default_value' => $settings->get('redirect'),
    ];

    $form['redirect_options']['site_404'] = [
      '#type' => 'textfield',
      '#title' => t('Fallback regular 404 (not found) page'),
      '#default_value' => $settings->get('site_404'),
      '#size' => 40,
      '#description' => t('By default, this page is what was used before Page Not Found Passthrough was installed. This page is displayed when no other content on the configured passthrough sites matches the requested document.'),
    ];

    $form['redirect_options']['save_redirect'] = [
      '#type' => 'checkbox',
      '#title' => t('Save Redirect?'),
      '#default_value' => $settings->get('save_redirect'),
      '#description' => t('Save a found 404 Redirect using the <a href=":url">Redirect module</a>. If checked, new requests will not result in 404, but instead will be redirected.', [
        ':url' => Url::fromRoute('redirect.settings')->toString(),
        ]),
      '#access' => \Drupal::moduleHandler()->moduleExists('redirect'),
    ];

    $form['redirect_options']['redirect_code'] = [
      '#type' => 'select',
      '#title' => t('Select a redirect code'),
      '#description' => t('Select a redirect code to use when redirecting to a legacy server (default: 302 Found). This setting will not be used when redirecting to "Search path" or "Direct redirect" (default 302 is used for them). More information about redirect and response codes can be found on <a href="http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#3xx_Redirection" target="_blank">Wikipedia</a>'),
      '#default_value' => $settings->get('redirect_code'),
      '#options' => _notfoundpassthrough_redirect_options(),
    ];

    $form['redirect_options']['force_redirect_code'] = [
      '#type' => 'checkbox',
      '#title' => t('Force redirect code'),
      '#description' => t('When legacy server responds with a redirect for the requested URI, the redirect code is reused. Check this box if you want override this behavior and always use the selected redirect code.'),
      '#default_value' => $settings->get('force_redirect_code'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates the submitted settings form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get the normal paths of the 404 page; this and the following validation
    // is borrowed from core/modules/system/src/Form/SiteInformationForm.php.
    if (!$form_state->isValueEmpty('site_404')) {
      $form_state->setValueForElement($form['redirect_options']['site_404'], $this->aliasManager->getPathByAlias($form_state->getValue('site_404')));
    }
    // Validate the 404 page formatting.
    if (($value = $form_state->getValue('site_404')) && $value[0] !== '/') {
      $form_state->setErrorByName('site_404', $this->t("The path '%path' has to start with a slash.", ['%path' => $form_state->getValue('site_404')]));
    }
    // Validate 404 error path.
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
      ->set('title', $form_state->getValue('title'))
      ->set('content', $form_state->getValue('content'))
      ->set('save_redirect', $form_state->getValue('save_redirect'))
      ->set('redirect_code', $form_state->getValue('redirect_code'))
      ->set('force_redirect_code', $form_state->getValue('force_redirect_code'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
