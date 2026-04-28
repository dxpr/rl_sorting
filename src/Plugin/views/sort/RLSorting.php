<?php

namespace Drupal\rl_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rl\Service\ExperimentManagerInterface;
use Drupal\rl\Service\CacheManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * AI-based sorting plugin for Views using Reinforcement Learning.
 *
 * @ViewsSort("rl_sorting")
 */
class RLSorting extends SortPluginBase {

  /**
   * The RL experiment manager.
   *
   * @var \Drupal\rl\Service\ExperimentManagerInterface
   */
  protected ExperimentManagerInterface $experimentManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The RL cache manager.
   *
   * @var \Drupal\rl\Service\CacheManager
   */
  protected CacheManager $cacheManager;

  /**
   * Constructs a new RLSorting object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\rl\Service\ExperimentManagerInterface $experiment_manager
   *   The RL experiment manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\rl\Service\CacheManager $cache_manager
   *   The RL cache manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExperimentManagerInterface $experiment_manager, RequestStack $request_stack, LoggerChannelFactoryInterface $logger_factory, CacheManager $cache_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->experimentManager = $experiment_manager;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
    $this->cacheManager = $cache_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore new.static
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('rl.experiment_manager'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('rl.cache_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['order'] = ['default' => ''];
    $options['cache_max_age'] = ['default' => 1];
    $options['favor_recent'] = ['default' => FALSE];
    // 3 months default
    $options['time_window_seconds'] = ['default' => 7776000];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    try {
      $this->ensureMyTable();

      // Generate deterministic experiment ID from view and display.
      $experiment_id = 'rl_sorting-' . $this->view->id() . '-' . $this->view->current_display;
      // Sanitize to ensure database-safe characters.
      $experiment_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $experiment_id);

      $time_window_seconds = $this->options['favor_recent'] ? $this->options['time_window_seconds'] : NULL;

      // Get the base field from the view configuration.
      $base_field = $this->view->storage->get('base_field');

      // If no base field is defined, we can't sort.
      if (empty($base_field)) {
        throw new \RuntimeException('RL Sorting requires a base_field to be defined in the view.');
      }

      // Get all possible arm IDs that will be in the result set.
      // We need to execute a query to get these IDs.
      $arm_ids = [];

      // Clone the current query to get IDs without affecting the main query.
      // The view's query at this point has all filters/conditions applied.
      $id_query = clone $this->query;

      // We only need the base field (ID field).
      // Clear fields and add only the base field.
      // @phpstan-ignore method.notFound
      $id_query->clearFields();
      // @phpstan-ignore method.notFound
      $id_alias = $id_query->addField($this->tableAlias, $base_field);

      // Remove any existing grouping and ordering.
      // @phpstan-ignore property.notFound
      $id_query->groupby = [];
      // @phpstan-ignore property.notFound
      $id_query->orderby = [];
      // @phpstan-ignore method.notFound
      $id_query->addGroupBy($id_alias);

      // Build and execute the query to get all IDs.
      // The query() method returns a SelectQuery object.
      $query_obj = $id_query->query();

      // Remove limit and offset from the query object.
      $query_obj->range();

      $result = $query_obj->execute();

      foreach ($result as $row) {
        if (isset($row->$base_field)) {
          $arm_ids[] = (string) $row->$base_field;
        }
      }

      // If no content to sort, skip AI scoring entirely.
      if (empty($arm_ids)) {
        return;
      }

      // Pass all arm IDs to the RL module to get scores.
      // The RL module will handle new arms by initializing them.
      $scores = $this->experimentManager->getThompsonScores(
        $experiment_id,
        $time_window_seconds,
        $arm_ids
      );

      // Fail hard if RL module doesn't return scores - no silent fallbacks!
      if (empty($scores)) {
        throw new \RuntimeException(sprintf(
          'RL Sorting FAILED: No scores returned for experiment "%s". RL module must always return scores for requested arms. Check RL module configuration and database connectivity.',
          $experiment_id
        ));
      }

      // Build the CASE statement for sorting.
      $case_statement = 'CASE ' . $this->tableAlias . '.' . $base_field . ' ';

      foreach ($scores as $arm_id => $score) {
        if (is_numeric($arm_id)) {
          $case_statement .= "WHEN " . (int) $arm_id . " THEN " . (float) $score . " ";
        }
        else {
          $escaped_id = addslashes($arm_id);
          $case_statement .= "WHEN '" . $escaped_id . "' THEN " . (float) $score . " ";
        }
      }

      // This should never be reached since we passed all IDs to RL module.
      $case_statement .= 'ELSE 0 END';

      // @phpstan-ignore method.notFound
      $this->query->addOrderBy(
        NULL,
        $case_statement,
        'DESC',
        'rl_sorting_score'
      );

      // Override page cache if RL Sorting cache is shorter than site cache.
      $view_config = $this->view->storage->get('display');
      $rl_sorting_cache = (int) ($view_config['default']['display_options']['sorts']['rl_sorting']['cache_max_age'] ?? 1);

      $this->cacheManager->overridePageCacheIfShorter($rl_sorting_cache);

    }
    catch (\Exception $e) {
      $logger = $this->loggerFactory->get('rl_sorting');
      $logger->error('RL Sorting: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    unset($form['order']);

    $form['rl_sorting_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('RL Sorting Settings'),
      '#open' => TRUE,
      '#description' => $this->t('<strong>What does RL Sorting do?</strong><br>
        RL Sorting automatically orders content based on user engagement. It learns which content gets clicked more often and shows the most engaging content first, while still giving new content a chance to be discovered.<br><br>
        <strong>How it works:</strong><br>
        • <em>Impressions</em>: When content appears in this view<br>
        • <em>Conversions</em>: When users click on that content<br>
        • The system balances showing popular content with exploring new options.'),
    ];

    $form['rl_sorting_settings']['favor_recent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Favor recent content'),
      '#default_value' => $this->options['favor_recent'] ?? FALSE,
      '#description' => $this->t('Enable this for content that becomes outdated (news, campaigns, seasonal products).'),
    ];

    $form['rl_sorting_settings']['time_window_seconds'] = [
      '#type' => 'select',
      '#title' => $this->t('Count interactions from'),
      '#default_value' => $this->options['time_window_seconds'] ?? 7776000,
      '#options' => [
    // 30 * 24 * 60 * 60
        2592000 => $this->t('Last month'),
    // 90 * 24 * 60 * 60
        7776000 => $this->t('Last 3 months'),
    // 180 * 24 * 60 * 60
        15552000 => $this->t('Last 6 months'),
    // 365 * 24 * 60 * 60
        31536000 => $this->t('Last year'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="options[rl_sorting_settings][favor_recent]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('Only recently active content influences recommendations.'),
    ];

    $form['rl_sorting_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['rl_sorting_settings']['advanced']['cache_max_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache duration'),
      '#default_value' => $this->options['cache_max_age'] ?? 1,
      '#options' => [
        0 => $this->t('No caching (fastest learning)'),
        1 => $this->t('1 second'),
        5 => $this->t('5 seconds'),
        30 => $this->t('30 seconds'),
        60 => $this->t('1 minute'),
        300 => $this->t('5 minutes'),
      ],
      '#description' => $this->t('How long browsers can cache content. Shorter times mean faster learning but more server load.'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $options = &$form_state->getValue('options');

    if (isset($options['rl_sorting_settings']['favor_recent'])) {
      $this->options['favor_recent'] = $options['rl_sorting_settings']['favor_recent'];
    }

    if (isset($options['rl_sorting_settings']['time_window_seconds'])) {
      $this->options['time_window_seconds'] = $options['rl_sorting_settings']['time_window_seconds'];
    }

    if (isset($options['rl_sorting_settings']['advanced']['cache_max_age'])) {
      $this->options['cache_max_age'] = $options['rl_sorting_settings']['advanced']['cache_max_age'];
    }

    $cache_max_age = $this->options['cache_max_age'] ?? 60;
    $current_cache = $this->view->display_handler->getOption('cache');

    if ($cache_max_age > 0) {
      if ($current_cache['type'] !== 'time' || $current_cache['options']['output_lifespan'] != $cache_max_age) {
        $this->view->display_handler->setOption('cache', [
          'type' => 'time',
          'options' => [
            'output_lifespan' => $cache_max_age,
            'results_lifespan' => $cache_max_age,
          ],
        ]);

        // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
        \Drupal::messenger()->addStatus($this->t('Views cache has been automatically set to @seconds seconds to match your AI sorting refresh rate.', ['@seconds' => $cache_max_age]));
      }
    }
    else {
      if ($current_cache['type'] !== 'none') {
        $this->view->display_handler->setOption('cache', ['type' => 'none']);

        // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
        \Drupal::messenger()->addWarning($this->t('Views cache has been automatically disabled because AI sorting cache is set to "Never cache".'));
      }
    }

    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    \Drupal::service('plugin.manager.views.sort')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = [];

    // Time window summary.
    if (!empty($this->options['favor_recent'])) {
      $time_window_seconds = $this->options['time_window_seconds'];
      if ($time_window_seconds == 2592000) {
        $summary[] = $this->t('Time window: Last month');
      }
      elseif ($time_window_seconds == 7776000) {
        $summary[] = $this->t('Time window: Last 3 months');
      }
      elseif ($time_window_seconds == 15552000) {
        $summary[] = $this->t('Time window: Last 6 months');
      }
      elseif ($time_window_seconds == 31536000) {
        $summary[] = $this->t('Time window: Last year');
      }
      else {
        $days = round($time_window_seconds / 86400);
        $summary[] = $this->t('Time window: Last @days days', ['@days' => $days]);
      }
    }

    // Cache summary.
    $cache_max_age = $this->options['cache_max_age'];
    if ($cache_max_age == -1) {
      $summary[] = $this->t('Cache: Use site default');
    }
    elseif ($cache_max_age == 0) {
      $summary[] = $this->t('Cache: Never cache');
    }
    elseif ($cache_max_age < 60) {
      $summary[] = $this->t('Cache: @seconds seconds', ['@seconds' => $cache_max_age]);
    }
    elseif ($cache_max_age < 3600) {
      $minutes = $cache_max_age / 60;
      $summary[] = $this->t('Cache: @minutes minute(s)', ['@minutes' => $minutes]);
    }
    else {
      $hours = $cache_max_age / 3600;
      $summary[] = $this->t('Cache: @hours hour(s)', ['@hours' => $hours]);
    }

    return implode(', ', $summary);
  }

}
