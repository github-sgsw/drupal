<?php

namespace Drupal\content_change_status_send_mail\Hook;

use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

class MailTriggerHooks {

  #[Hook('node_update')]
  public function entityUpdate(NodeInterface $entity) {

    if ($this->getWorkflowMachinName($entity) == 'draft') return;

    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['subject'] = $entity->label();
    $params['message'] = <<<TEXT
      {$this->getWorkflowDispName($entity)}

      test １つ前のリビジョンタイトル
      {$this->getPreviousRevisionNode($entity)->label()}

      送信者
      {$entity->getRevisionUser()->getDisplayName()}

      対象コンテンツ編集画面
      {$entity->toUrl('edit-form', ['absolute' => TRUE])->toString()}
    TEXT;

    $mailManager->mail(
      'content_change_status_send_mail',
      'content_moderation_notification',
      'example@example.com',
      $langcode,
      $params
    );
  }

  #[Hook('mail')]
  function sendMail($key, &$message, $params) {
    $options = [
      'langcode' => $message['langcode'],
    ];
    $message['from'] = \Drupal::config('system.site')->get('mail');
    $message['subject'] = t('@subject', ['@subject' => $params['subject']], $options);
    $message['body'][] = $params['message'];
  }

  /**
   * @return string ワークフローステータスのシステム内部名称
   */
  private function getWorkflowMachinName(NodeInterface $entity) {
    return $entity->get('moderation_state')->value;
  }

  /**
   * @return string ワークフローステータスの表示ラベル
   */
  private function getWorkflowDispName(NodeInterface $entity) {
    return \Drupal::service('content_moderation.moderation_information')
      ->getWorkflowForEntity($entity)
      ->getTypePlugin()
      ->getState($this->getWorkflowMachinName($entity))
      ->label();
  }

  /**
   * 1つ前のリビジョンを取得する
   * @return string 差し戻し対象コンテンツのリビジョン
   */
  private function getPreviousRevisionNode(NodeInterface $entity) {
    /** @var RevisionableStorageInterface */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    return $node_storage->getQuery()
      ->accessCheck()
      ->allRevisions()
      ->condition('nid', $entity->id())
      ->condition('vid', $entity->getRevisionId(), '<')
      ->sort('vid', 'DESC')
      ->range(0, 1)
      ->execute()
      |> key(...)
      |> $node_storage->loadRevision(...);
  }
}

