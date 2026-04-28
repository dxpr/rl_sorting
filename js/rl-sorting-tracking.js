(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.rlSortingTracking = {
    attach: function (context, settings) {
      if (!settings.rlSorting || !settings.rlSorting.views) {
        return;
      }

      once('rl-sorting-tracking', '.view', context).forEach(function(view) {
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var displayIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-display-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';
        var displayId = displayIdClass ? displayIdClass.replace('view-display-id-', '') : 'unknown';
        var viewDisplayKey = viewId + '.' + displayId;

        if (!settings.rlSorting.views[viewDisplayKey]) {
          return;
        }

        var viewSettings = settings.rlSorting.views[viewDisplayKey];
        var experimentId = viewSettings.experimentId;
        var entityIds = viewSettings.entityIds;
        var entityUrlMap = viewSettings.entityUrlMap;
        var rlEndpointUrl = viewSettings.rlEndpointUrl;

        // Fail hard if required data is missing
        if (!experimentId || !rlEndpointUrl) {
          throw new Error('RL Sorting: Missing required experiment data (experimentId or rlEndpointUrl)');
        }

        // Track rewards (when links are clicked) and turns (when links become visible)
        if (entityUrlMap && Object.keys(entityUrlMap).length > 0) {
          var links = view.querySelectorAll('a');
          var visibleArms = [];
          var batchTimer = null;
          
          // Function to send batched turns
          function sendBatchedTurns() {
            if (visibleArms.length > 0) {
              var formData = new FormData();
              formData.append('action', 'turns');
              formData.append('experiment_id', experimentId);
              formData.append('arm_ids', visibleArms.join(','));
              
              navigator.sendBeacon(rlEndpointUrl, formData);
              visibleArms = [];
            }
          }
          
          // Create a single observer for all links in this view
          var turnObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
              if (entry.isIntersecting) {
                var entityId = entry.target.dataset.entityId;
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
          }, {threshold: 0.1});
          
          links.forEach(function(link) {
            var href = link.getAttribute('href');
            var entityId = Object.keys(entityUrlMap).find(id => entityUrlMap[id] === href);
            
            if (entityId) {
              link.dataset.entityId = entityId;

              // Observe for visibility tracking
              turnObserver.observe(link);

              // Session storage key for tracking rewarded experiments in this page load.
              var storageKey = 'rl_sorting_rewarded_' + experimentId;

              // Track reward when clicked
              link.addEventListener('click', function() {
                // Check if we've already sent a reward for this experiment in this page load.
                if (sessionStorage.getItem(storageKey)) {
                  // Already rewarded this turn, skip.
                  return;
                }

                // Mark this experiment as rewarded for this page load.
                sessionStorage.setItem(storageKey, '1');

                // Create FormData for POST request to rl.php
                var formData = new FormData();
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
    }
  };
})(Drupal, once);