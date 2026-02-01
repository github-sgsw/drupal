<?php

namespace Drupal\custom_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Hello' Block.
 *
 * @Block(
 *   id = "hello_custom_block",
 *   admin_label = @Translation("Hello Custom Block Label"),
 *   category = @Translation("Hello Custom Block Category"),
 * )
 */
class HelloBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $some_array = [
      0 => [
        'is_active' => 'active',
        'label' => 'google',
        'url' => 'https://google.com',
      ],
      1 => [
        'is_active' => 'inactive',
        'label' => 'amazon',
        'url' => 'https://amazon.com',
      ],
    ];

    return [
      '#theme' => 'custom_block',
      '#active_tab' => 'some_string',
      '#body_text' => [
        '#markup' => 'Hello!! customBlock',
      ],
      '#tabs' => $some_array,
    ];
  }

}
