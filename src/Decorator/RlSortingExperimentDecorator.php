<?php

namespace Drupal\rl_sorting\Decorator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rl\Decorator\ExperimentDecoratorInterface;
use Drupal\views\Views;

/**
 * Experiment decorator for rl_sorting experiments.
 *
 * Provides human-readable entity labels for arm IDs in RL reports.
 */
class RlSortingExperimentDecorator implements ExperimentDecoratorInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Cache of view base entity types.
   *
   * @var array
   */
  protected array $viewEntityTypeCache = [];

  /**
   * Constructs an RlSortingExperimentDecorator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function decorateExperiment(string $experiment_id): ?array {
    // Could optionally return a decorated experiment name here.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function decorateArm(string $experiment_id, string $arm_id): ?array {
    // Only handle rl_sorting experiments.
    if (!str_starts_with($experiment_id, 'rl_sorting-')) {
      return NULL;
    }

    // Extract view_id from "rl_sorting-{view_id}-{display_id}".
    $parts = explode('-', $experiment_id);
    if (count($parts) < 3) {
      return NULL;
    }

    // The view_id might contain hyphens, so we need to handle that.
    // Format: rl_sorting-{view_id}-{display_id}
    // Remove 'rl_sorting' prefix and last part (display_id).
    array_shift($parts);
    array_pop($parts);
    $view_id = implode('-', $parts);

    if (empty($view_id)) {
      return NULL;
    }

    // Get the base entity type for this view.
    $base_entity_type_id = $this->getViewBaseEntityType($view_id);
    if (!$base_entity_type_id) {
      return NULL;
    }

    // Load the entity.
    try {
      $entity = $this->entityTypeManager
        ->getStorage($base_entity_type_id)
        ->load($arm_id);

      if ($entity) {
        $label = $entity->label();
        // Return render array with the entity label and ID.
        // Use |raw to avoid double-encoding - entity labels are admin content.
        return [
          '#type' => 'inline_template',
          '#template' => '{{ label|raw }} <small>({{ id }})</small>',
          '#context' => [
            'label' => $label,
            'id' => $arm_id,
          ],
        ];
      }
    }
    catch (\Exception $e) {
      // Entity might have been deleted or storage doesn't exist.
      return NULL;
    }

    return NULL;
  }

  /**
   * Get the base entity type ID for a view.
   *
   * @param string $view_id
   *   The view ID.
   *
   * @return string|null
   *   The entity type ID, or NULL if not found.
   */
  protected function getViewBaseEntityType(string $view_id): ?string {
    // Check cache first.
    if (isset($this->viewEntityTypeCache[$view_id])) {
      return $this->viewEntityTypeCache[$view_id];
    }

    // Load the view.
    $view = Views::getView($view_id);
    if (!$view) {
      $this->viewEntityTypeCache[$view_id] = NULL;
      return NULL;
    }

    // Get the base entity type.
    $base_entity_type = $view->getBaseEntityType();
    if (!$base_entity_type) {
      $this->viewEntityTypeCache[$view_id] = NULL;
      return NULL;
    }

    $entity_type_id = $base_entity_type->id();
    $this->viewEntityTypeCache[$view_id] = $entity_type_id;

    return $entity_type_id;
  }

}
