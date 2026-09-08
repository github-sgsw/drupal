<?php

namespace Drupal\content_change_status_send_mail\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableEntityStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;

class MailTriggerHooks {
  #[Hook('node_update')]
  public function entityUpdate(EntityInterface $entity) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['subject'] = $entity->label();
    $params['message'] = <<<TEXT
      {$this->subject($entity)}

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
    $message['to'] = 'example@example.com';
    $message['from'] = \Drupal::config('system.site')->get('mail');
    $message['subject'] = t('@subject', ['@subject' => $params['subject']], $options);
    $message['body'][] = $params['message'];
  }

  /**
   * @param EntityInterface $entity ワークフロー対象コンテンツ
   * @return string ワークフローの概要
   */
  private function subject(EntityInterface $entity) {
    $workflow_machin_name = $entity->get('moderation_state')->value;
    $workflow_displaf_name = \Drupal::service('content_moderation.moderation_information')
      ->getWorkflowForEntity($entity)
      ->getTypePlugin()
      ->getState($workflow_machin_name)
      ->label();
    return match ($workflow_machin_name) {
      'published' => "が{$workflow_displaf_name}されました。",
      'unpublished' => "が{$workflow_displaf_name}になりました。",
      'pending_approval', 'reject' => "{$workflow_displaf_name}のワークフローが届きました。"
    };
  }

  /**
   * 1つ前のリビジョンを取得する
   * @return string 差し戻し対象コンテンツのリビジョン
   */
  private function getPreviousRevisionNode(EntityInterface $entity) {
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

