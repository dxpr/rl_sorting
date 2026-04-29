> **RL Sorting** is a Drupal module by [DXPR](https://dxpr.com) that applies
> Thompson Sampling reinforcement learning to Views sort orders, automatically
> surfacing high-performing content variants without manual A/B test configuration.
>
> [Getting Started](https://dxpr.com/c/getting-started) |
> [Pricing](https://dxpr.com/pricing) |
> [Try Free Demo](https://dxpr.com/try)

# RL Sorting - Reinforcement Learning Content Optimization for Drupal Views

Advanced A/B testing for Drupal Views that learns automatically. Instead of
manually setting up A/B tests, RL Sorting continuously tests all content
simultaneously using Thompson Sampling machine learning to surface winners
while giving new content fair exposure.

## Features

- **Advanced A/B Testing Engine** - Powered by Thompson Sampling ML
  - Multi-variant testing of unlimited content pieces simultaneously  
  - Zero setup required - no manual test configuration or statistical analysis
  - Continuous learning - tests never end, performance improves over time
  - Automatic statistical significance handling
- **Universal entity support** - Works with nodes, users, taxonomy terms, media,
  custom entities, and external data
- **Cold start handling** - New items get proper exploration scores
  automatically  
- **Views integration** - Simple sort plugin that works with any
  Views-compatible data source
- **Real-time learning** - Continuous adaptation based on user interactions
- **Fail-hard debugging** - No silent fallbacks, issues are immediately visible

## Setup

1. Install the module (requires [RL module](https://www.drupal.org/project/rl))
2. Edit any View display (nodes, users, terms, media, custom entities)
3. Add "RL Sorting" as a sort criteria
4. Configure cache refresh rate and time window options
5. Save - items immediately begin learning from user interactions

## How It Works

1. **Track Engagement** - JavaScript monitors when items are viewed and clicked
2. **Learn Patterns** - Thompson Sampling identifies high-performing items  
3. **Optimize Order** - Best items automatically move to prominent positions
4. **Handle New Items** - New content gets exploration scores for fair exposure
5. **Continuous Improvement** - Performance gets better with every visitor

## Beyond Traditional A/B Testing

Unlike traditional A/B testing tools, RL Sorting provides:

- **Multi-Variant Testing** - Test unlimited content pieces simultaneously,
  not just A vs B
- **Zero Manual Work** - No test setup, statistical analysis, or winner
  selection required  
- **Continuous Optimization** - Tests never end, performance improves over time
- **Smart Traffic Allocation** - Reduces traffic waste by promoting winners
  faster
- **Cold Start Problem Solved** - New content automatically gets fair exposure

## Use Cases

- **Blog Posts** - Surface most engaging articles first
- **Product Pages** - Highlight items that convert best  
- **News Feeds** - Breaking stories get automatic priority
- **Resource Centers** - Most valuable downloads rise to the top
- **Case Studies** - Showcase most compelling success stories

## Configuration

- **Cache Lifetime** - How often content order refreshes
- **Automatic Cache Setup** - Views cache automatically configured for optimal
  performance

## Dependencies

- [RL (Reinforcement Learning) module](https://www.drupal.org/project/rl)

## Related Modules

- [Reinforcement Learning (RL)](https://www.drupal.org/project/rl) - Thompson Sampling A/B testing framework for Drupal
- [Analyze](https://www.drupal.org/project/analyze) - unified content analysis framework
- [DXPR Builder](https://www.drupal.org/project/dxpr_builder) - AI-powered drag-and-drop page building
- [Google Tag](https://www.drupal.org/project/google_tag) - analytics tag management
- [ECA](https://www.drupal.org/project/eca) - event-condition-action automation
