(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.rlSortingTracking = {
    attach(context, settings) {
      if (!settings.rlSorting || !settings.rlSorting.views) {
        return;
      }

      once('rl-sorting-tracking', '.view', context).forEach((view) => {
        const viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        const displayIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-display-id-'));
        const viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';
        const displayId = displayIdClass ? displayIdClass.replace('view-display-id-', '') : 'unknown';
        const viewDisplayKey = `${viewId}.${displayId}`;

        if (!settings.rlSorting.views[viewDisplayKey]) {
          return;
        }

        const { experimentId, entityUrlMap, rlEndpointUrl }
          = settings.rlSorting.views[viewDisplayKey];

        // Fail hard if required data is missing
        if (!experimentId || !rlEndpointUrl) {
          throw new Error('RL Sorting: Missing required experiment data (experimentId or rlEndpointUrl)');
        }

        // Track rewards (when links are clicked) and turns (when links become visible)
        if (entityUrlMap && Object.keys(entityUrlMap).length > 0) {
          const links = view.querySelectorAll('a');
          let visibleArms = [];
          let batchTimer = null;

          // Send batched turn impressions to the RL endpoint
          const sendBatchedTurns = () => {
            if (visibleArms.length > 0) {
              const formData = new FormData();
              formData.append('action', 'turns');
              formData.append('experiment_id', experimentId);
              formData.append('arm_ids', visibleArms.join(','));

              navigator.sendBeacon(rlEndpointUrl, formData);
              visibleArms = [];
            }
          };

          // Create a single observer for all links in this view
          const turnObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting) {
                const entityId = entry.target.dataset.entityId;
                if (entityId && !entry.target.dataset.tracked) {
                  visibleArms.push(entityId);
                  entry.target.dataset.tracked = 'true';
                  turnObserver.unobserve(entry.target);

                  // Clear existing timer and set new one
                  clearTimeout(batchTimer);
                  batchTimer = setTimeout(sendBatchedTurns, 100);
                }
              }
            });
          }, { threshold: 0.1 });

          // Session storage key for tracking rewarded experiments in this page load
          const storageKey = `rl_sorting_rewarded_${experimentId}`;

          links.forEach((link) => {
            const href = link.getAttribute('href');
            const entityId = Object.keys(entityUrlMap).find(id => entityUrlMap[id] === href);

            if (entityId) {
              link.dataset.entityId = entityId;

              // Observe for visibility tracking
              turnObserver.observe(link);

              // Track reward when clicked
              link.addEventListener('click', () => {
                // Check if we've already sent a reward for this experiment in this page load.
                if (sessionStorage.getItem(storageKey)) {
                  // Already rewarded this turn, skip.
                  return;
                }

                // Mark this experiment as rewarded for this page load.
                sessionStorage.setItem(storageKey, '1');

                // Create FormData for POST request to rl.php
                const formData = new FormData();
                formData.append('action', 'reward');
                formData.append('experiment_id', experimentId);
                formData.append('arm_id', entityId);

                // Use sendBeacon for non-blocking request
                navigator.sendBeacon(rlEndpointUrl, formData);
              });
            }
          });
        }
      });
    },
  };
})(Drupal, once);
