<?php

namespace Drupal\rl_sorting\Service;

use Drupal\rl\Registry\ExperimentRegistryInterface;

/**
 * Service for registering RL Sorting experiments.
 */
class ExperimentRegistrationService {

  /**
   * The experiment registry.
   *
   * @var \Drupal\rl\Registry\ExperimentRegistryInterface
   */
  protected ExperimentRegistryInterface $experimentRegistry;

  /**
   * Constructs a new ExperimentRegistrationService.
   *
   * @param \Drupal\rl\Registry\ExperimentRegistryInterface $experiment_registry
   *   The experiment registry.
   */
  public function __construct(ExperimentRegistryInterface $experiment_registry) {
    $this->experimentRegistry = $experiment_registry;
  }

  /**
   * Register an experiment ID.
   *
   * @param string $experiment_id
   *   The experiment ID to register.
   * @param string $experiment_name
   *   Optional human-readable experiment name.
   */
  public function registerExperiment(string $experiment_id, ?string $experiment_name = NULL): void {
    $this->experimentRegistry->register($experiment_id, 'rl_sorting', $experiment_name);
  }

}
