<?php

namespace Drupal\add_status_element_for_node_translation_view\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class addStatusElementForNodeTranslationView implements ContainerInjectionInterface {

  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('language_manager'),
      $container->get('entity_type.manager')
    );
  }

  #[Hook('preprocess_table')]
  public function node_language_select_page_add_custom_moderation_preprocess_table(&$variables): void {
    if ($this->routeMatch->getRouteName() !== 'entity.node.content_translation_overview') return;

    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) return;

    [$updated_header, $published_cell_index, $add_array_index] = $this->transformHeaders($variables['header']);
    $variables['header'] = $updated_header;

    $languages = array_values($this->languageManager->getLanguages());
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    $variables['rows'] = array_map(
      fn(array $row, LanguageInterface $language) => $this->transformRow(
        $row,
        $language,
        $node->id(),
        $node_storage,
        $published_cell_index,
        $add_array_index
      ),
      $variables['rows'],
      $languages
    );
  }

  /**
   * ヘッダー配列の変換
   */
  private function transformHeaders(array $headers): array {
    $published_cell_index = 0;
    $add_array_index = 0;

    $transformed = array_map(function ($index, $th) use (&$published_cell_index, &$add_array_index) {
      /** @var TranslatableMarkup $th_class */
      $th_class = $th['content'];
      $untranslated = $th_class->getUntranslatedString();

      if ($untranslated === 'Status') {
        $published_cell_index = $index;
        $add_array_index = $index + 1;
        return array_merge($th, ['content' => new TranslatableMarkup('公開')]);
      }
      if ($untranslated === 'Translation') {
        return array_merge($th, ['content' => new TranslatableMarkup('タイトル')]);
      }

      return $th;
    }, array_keys($headers), $headers);

    $custom_moderation_th = [
      [
        'tag' => 'th',
        'attributes' => new Attribute(),
        'content' => new TranslatableMarkup('状態'),
      ],
    ];

    $final_headers = $this->arrayInsert($transformed, $add_array_index, $custom_moderation_th);

    return [$final_headers, $published_cell_index, $add_array_index];
  }

  private function transformRow(
    array $row,
    LanguageInterface $language,
    int|string $nid,
    NodeStorageInterface $node_storage,
    int $published_cell_index,
    int $add_array_index
  ): array {
    $node_status = 'Not translated';
    $cells = $row['cells'];

    /** @var \Drupal\node\NodeInterface|null $default_node */
    $default_node = $node_storage->load($nid);

    if ($default_node?->hasTranslation($language->getId())) {
      $translated_node = $default_node->getTranslation($language->getId());

      // 公開状態セルの置き換え
      $cells[$published_cell_index] = $this->createPublishedCell($translated_node);

      // モデレーションステータス取得
      $latest_vid = $node_storage->getLatestTranslationAffectedRevisionId($nid, $language->getId());
      if ($latest_vid) {
        /** @var \Drupal\node\NodeInterface $latest_revision_node */
        $latest_revision_node = $node_storage->loadRevision($latest_vid);
        $node_status = $latest_revision_node->getTranslation($language->getId())->get('moderation_state')->getString();
      }
    }

    $moderation_cell = $this->createModerationCell($node_status);
    $row['cells'] = $this->arrayInsert($cells, $add_array_index, $moderation_cell);

    return $row;
  }

  /**
   * 配列の特定位置へ要素を挿入）
   */
  private function arrayInsert(array $array, int $index, array $insert): array {
    return array_merge(
      array_slice($array, 0, $index),
      $insert,
      array_slice($array, $index)
    );
  }

  /**
   * デフォルトリビジョンの公開・非公開セル構造の生成
   */
  private function createPublishedCell(NodeInterface $node): array {
    return [
      'tag' => 'td',
      'attributes' => new Attribute(),
      'content' => [
        '#type' => 'inline_template',
        '#template' => '<span class="status">{% if status %}{{ "Published"|t }}{% else %}{{ "Not published"|t }}{% endif %}</span>{% if outdated %}<span class="marker">{{ "outdated"|t }}</span>{% endif %}',
        '#context' => [
          'status' => $node->isPublished(),
          'outdated' => FALSE,
        ],
      ],
    ];
  }

  /**
   * モデレーションステータスのセル構造の生成
   */
  private function createModerationCell(string $node_status): array {
    return [
      [
        'tag' => 'td',
        'attributes' => new Attribute(),
        'content' => [
          '#type' => 'inline_template',
          '#template' => '<span class="status">' . t($node_status) . '</span>{% if outdated %} <span class="marker">{{ "outdated"|t }}</span>{% endif %}',
          '#context' => [
            'status' => FALSE,
            'outdated' => FALSE,
          ],
        ],
      ],
    ];
  }

}
