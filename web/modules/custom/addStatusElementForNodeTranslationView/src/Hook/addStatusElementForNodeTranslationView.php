<?php

namespace Drupal\add_status_element_for_node_translation_view\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\node\NodeInterface;

class addStatusElementForNodeTranslationView {
  #[Hook('preprocess_table')]
  public function node_language_select_page_add_custom_moderation_preprocess_table(&$variables):void {

    $route_match = \Drupal::routeMatch();
    // 該当する翻訳一覧ページ（content_translation.local_tasks:entity.node.content_translation_overview）か判定
    if ($route_match->getRouteName() !== 'entity.node.content_translation_overview') return;

    // 公開・非公開ラベルのカラムキーを保持 $published_cell_index
    // 公開・非公開の右にカスタムモデレーションのカラムを追加するため要素のキーを保持 $add_array_index
    $published_cell_index = 0;
    $add_array_index = 0;

    /*
      * thの名称の日本語訳がおかしいため書き換える。管理画面の話なので直で日本語を入れる。
      * 状態 -> 公開
      * 翻訳 -> タイトル
    */
    foreach ($variables['header'] as $index => $th) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $th_class */
      $th_class = $th["content"];

      if ($th_class->getUntranslatedString() == 'Status') {
        $published_cell_index = $index;
        $overrideClass = new TranslatableMarkup('公開');
        $variables['header'][$published_cell_index]["content"] = $overrideClass;

        $add_th_custom_moderation = [
          [
            'tag' => 'th',
            'attributes' => new \Drupal\Core\Template\Attribute(),
              'content' => new TranslatableMarkup('状態')
          ]
        ];
        $add_array_index = $index + 1;
        array_splice($variables['header'], $add_array_index, 0, $add_th_custom_moderation);
      }
      if ($th_class->getUntranslatedString() == 'Translation') {
        $overrideClass = new TranslatableMarkup('タイトル');
        $variables['header'][$index]["content"] = $overrideClass;
      }
    }

    /*
     * パスからnidを取得してノードをロード
     * カスタムモデレーションのラベルセットして配列に追加
    */

    foreach (array_map(null, $variables['rows'], \Drupal::languageManager()->getLanguages()) as $index => [$tr, $language]) {
      $entity = \Drupal::routeMatch()->getParameter('node');
      $node_status = 'Not translated';

      $default_node = NodeInterface::load($matches[1]);

      if ($default_node?->hasTranslation($language->getId())) {
        /*
         * 最新リビジョンのモデレーションステータスを見て公開・非公開を表示させているため
         * デフォルトリビジョンの公開・非公開を取得し、上書きする
        */
        $default_node = $default_node->getTranslation($language->getId());
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $add_td_custom_moderation */
        $published_cell_td = [
          'tag' => 'td',
          'attributes' => new \Drupal\Core\Template\Attribute(),
          'content' => [
            '#type' => 'inline_template',
            '#template' => '<span class="status">{% if status %}{{ "Published"|t }}{% else %}{{ "Not published"|t }}{% endif %}</span>{% if outdated %}<span class="marker">{{ "outdated"|t }}</span>{% endif %}',
            '#context' => [
              'status' => $default_node->isPublished(),
              'outdated' => FALSE
            ]
          ]
        ];

        $variables['rows'][$index]['cells'][$published_cell_index] = $published_cell_td;

        /*
         * 最新リビジョンのステータスを取得し
         * $variables['header']に追加したカラムに入れる
         */

        $storage = \Drupal::service('entity_type.manager')->getStorage('node');
        $latest_vid = $storage->getLatestTranslationAffectedRevisionId($matches[1], $language->getId());
        $latest_revision_node = $storage->loadRevision($latest_vid);

        $node_status = $latest_revision_node->getTranslation($language->getId())->get('moderation_state')->getString();

      }

      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $add_td_custom_moderation */
      $add_td_custom_moderation = [
        [
          'tag' => 'td',
          'attributes' => new \Drupal\Core\Template\Attribute(),
          'content' => [
            '#type' => 'inline_template',
            '#template' => '<span class="status">'.t($node_status).'</span>{% if outdated %} <span class="marker">{{ "outdated"|t }}</span>{% endif %}',
            '#context' => [
              'status' => false,
              'outdated' => false
            ]
          ]
        ]
      ];

      array_splice($variables['rows'][$index]['cells'], $add_array_index, 0, $add_td_custom_moderation);

    }
  }
}
