<?php

namespace Drupal\custom_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Hello' Block.
 *
 * @Block(
 *   id = "bay_custom_block",
 *   admin_label = @Translation("Bay Custom Block Label"),
 *   category = @Translation("Bay Custom Block Category"),
 * )
 */
class BayBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $some_array = [
      0 => [
        'is_active' => 'active',
        'label' => 'youtube',
        'url' => 'https://youtube.com',
      ],
      1 => [
        'is_active' => 'inactive',
        'label' => 'drupal',
        'url' => 'https://drupal.org',
      ],
    ];

    return [
      '#theme' => 'custom_block',
      '#active_tab' => 'some_string',
      '#body_text' => [
        '#markup' => 'GoodBay!! customBlock',
      ],
      '#tabs' => $some_array,
    ];
  }

}
