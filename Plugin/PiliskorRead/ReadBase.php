<?php

namespace Drupal\piliskor_qr\Plugin\PiliskorRead;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\piliskor_run\RunManager;
use Drupal\piliskor_run\RunPageElementsInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

abstract class ReadBase extends PluginBase implements ReadInterface, ContainerFactoryPluginInterface {

  use PluginWithFormsTrait;
  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The run manager.
   *
   * @var \Drupal\piliskor_run\RunManager;
   */
  protected $runManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface;
   */
  protected $routeMatch;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The run page elements.
   *
   * @var \Drupal\piliskor_run\RunPageElementsInterface
   */
  protected $runPageElements;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new PaymentGatewayBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\piliskor_run\RunPageElementsInterface $run_page_elements
   *   The run page elements.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, RunManager $run_manager, EntityTypeManagerInterface $entity_type_manager, TranslationManager $string_translation, ConfigFactoryInterface $config_factory, RouteMatchInterface $route_match, FormBuilderInterface $form_builder, RunPageElementsInterface $run_page_elements, TimeInterface $time, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $current_user;
    $this->runManager = $run_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
    $this->formBuilder = $form_builder;
    $this->runPageElements = $run_page_elements;
    $this->time = $time;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('piliskor_run.run_manager'),
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('config.factory'),
      $container->get('current_route_match'),
      $container->get('form_builder'),
      $container->get('piliskor_run.run_page_elements'),
      $container->get('datetime.time'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function route() {
    return new Route(
      '/'. $this->getPluginId() . '/{run}/{account}',
      [
        '_controller' => '\Drupal\piliskor_qr\Controller\Read::read',
      ],
      [
        '_custom_access' => '\Drupal\piliskor_qr\Controller\Read::access',
      ],
      [
        'parameters' => [
          'run' => [
            'type' => 'entity:commerce_order_item',
          ],
          'account' => [
            'type' => 'entity:user',
          ],
        ],
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createResponse(OrderItemInterface $run, UserInterface $account, Request $request, RouteMatchInterface $route_match) {
    $response = new AjaxResponse();
    $response->setCache(['max_age' => 0]);
    if (!in_array($run->get('field_run_status')->value, ['new', 'in_progress'])) {
      $response->addCommand(new MessageCommand($this->t('Run already finished.'), NULL, ['type' => 'status']));
      $response->addCommand(new RedirectCommand('/qr-reader'));
      return $response;
    }

    // Check if account allows reading on their behalf.
    if ($this->currentUser->id() != $account->id()) {
      if ($account->get('field_allow_delegate_reading')->isEmpty() || $account->get('field_allow_delegate_reading')->value != 1) {
        $response->addCommand(new MessageCommand($this->t('You cannot read on behalf of this user.'), NULL, ['type' => 'error']));
        return $response;
      }
    }

    // Check if we're still in time.
    if ($this->runManager->hasRunnerRunOutOfTime($run)) {
      $this->runManager->failRun($run);
      $response->addCommand(new MessageCommand($this->t('You have run out of time.'), NULL, ['type' => 'error']));
      return $response;
    }

    /** @var \Drupal\node\Entity\Node $last_read */
    $last_read = $this->runManager->getLastValidRead($run);

    // Run has not started yet.
    if (!$last_read) {
      $first_point_id = $this->runManager->getNextPointId($run);
      $match = $this->matchCodeToPoint($run, $account, $first_point_id, $request, $route_match);
      if ($match['success']) {
        $this->runManager->startRun($run);
        $this->startRun($run, $response);
      }
    }
    elseif ($last_read->getOwnerId() == $account->id()) {
      $next_point_id = $this->runManager->getNextPointId($run);
      $match = $this->matchCodeToPoint($run, $account, $next_point_id, $request, $route_match);
      if ($match['success']) {
        $this->runManager->continueRun($run, $account->id());
        $this->continueRun($run, $response);
      }
    }
    // Change of runners.
    else {
      $match = $this->matchCodeToPoint($run, $account, $last_read->get('field_point')->target_id, $request, $route_match);
      if ($match['success']) {
        $this->runManager->takeOverRun($run);
        $this->takeOverRun($run, $response);
      }
    }

    if (!$match['success']) {
      $this->failedRead($run, $response, $match);
    }

    // Check if the run has finished.
    $next_point_id = $this->runManager->getNextPointId($run);
    if ($next_point_id == -1) {
      $this->runManager->finishRun($run);
      $this->finishRun($run, $response);
    }

    return $response;
  }

  /**
   * Finds out if a point has a code/coordinate/whatever and acts.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $run
   *   The run order item.
   * @param int $point_id
   *   The point id to match the read against.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request containing information on the read.
   *
   * @return number|array|bool
   *   - -1 if point_id is empty.
   *   - FALSE if the point_id is not a valid point id.
   *   - Otherwise an array with the match results. The only required key is
   *     "success" with a boolean value.
   */
  protected function matchCodeToPoint(OrderItemInterface $run, UserInterface $account, $point_id, Request $request, RouteMatchInterface $route_match) {
    if (!$point_id) {
      return -1;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $point = $storage->load($point_id);
    if ($point) {
      $match = $this->comparePointAndRequest($point, $request, $route_match);
      $this->runManager->createRead($run, $point, $match, $match['success'], $account);
      return $match;
    }
    return FALSE;
  }

  /**
   * Compare a point and the request.
   *
   * @param \Drupal\node\NodeInterface $point
   *   The point to compare to.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request containing data to compare.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array
   *   An arbitrary array with one required key: success. Its value should be
   *   TRUE if the comparison result was positive and FALSE otherwise. The
   *   array will be merged into the point entity to be created so field
   *   values can be added.
   */
  abstract protected function comparePointAndRequest(NodeInterface $point, Request $request, RouteMatchInterface $route_match);

  /**
   * {@inheritdoc}
   */
  public function startRun(OrderItemInterface $run, AjaxResponse $response) {
    $next_point = $this->entityTypeManager->getStorage('node')->load($this->runManager->getNextPointId($run));
    $vars = [
      '%next' => $next_point->toLink()->toString(),
    ];
    $this->messenger->addStatus($this->t('Successful read! Next point is %next.', $vars));
    $this->commonNextPointCommands($run, $vars, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function continueRun(OrderItemInterface $run, AjaxResponse $response) {
    $next_point = $this->entityTypeManager->getStorage('node')->load($this->runManager->getNextPointId($run));
    if ($next_point) {
      $vars = [
        '%next' => $next_point->toLink()->toString(),
      ];
      $this->messenger->addStatus($this->t('Successful read! Next point is %next.', $vars));
      $this->commonNextPointCommands($run, $vars, $response);
    }
  }

  /**
   * Refresh next point and segment times table.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $run
   *   The run.
   * @param array $vars
   *   The variables passed to t(), see below.
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response to communicate with the runner.
   */
  protected function commonNextPointCommands(OrderItemInterface $run, $vars, AjaxResponse $response) {
    $url = Url::fromRoute('piliskor_run.run', ['run' => $run->id()])->toString();
    $response->addCommand(new RedirectCommand($url));
  }

  /**
   * {@inheritdoc}
   */
  public function takeOverRun(OrderItemInterface $run, AjaxResponse $response) {
    $next_point = $this->entityTypeManager->getStorage('node')->load($this->runManager->getNextPointId($run));
    $vars = [
      '%next' => $next_point->toLink()->toString(),
    ];
    $this->messenger->addStatus($this->t('You have succesfully taken over the run. Run on! Next point is %next.', $vars));
    $this->commonNextPointCommands($run, $vars, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function finishRun(OrderItemInterface $run, AjaxResponse $response) {
    $this->messenger->addStatus($this->t('You have finished the run! Congratulations!'));
    $this->commonNextPointCommands($run, ['%next' => ''], $response);
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, $account = NULL, $return_as_object = FALSE) {
    if ($return_as_object) {
      return new AccessResultAllowed();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
