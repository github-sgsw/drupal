<?php

namespace Drupal\content_change_status_send_mail\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

class MailTriggerHooks {
  #[Hook('node_update')]
  public function entity_update(EntityInterface $entity) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['subject'] = $entity->label();
    $params['message'] = <<<TEXT
      {$entity->getRevisionUser()->getDisplayName()}
      {$this->subject($entity)}
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
  function send_mail($key, &$message, $params) {
    $options = [
      'langcode' => $message['langcode'],
    ];
    $message['to'] = 'example@example.com';
    $message['from'] = \Drupal::config('system.site')->get('mail');
    $message['subject'] = t('@subject', ['@subject' => $params['subject']], $options);
    $message['body'][] = $params['message'];
  }

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
}

