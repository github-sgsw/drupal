<?php

namespace Drupal\content_change_status_send_mail\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

class MailTriggerHooks {
  #[Hook('node_update')]
  public function entity_update(EntityInterface $entity) {
    \Drupal::logger('custom_mail_module')->notice('yahho');
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['subject'] = 'さぶじぇくと';
    $params['message'] = 'test送信';

    $result = $mailManager->mail(
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
    $message['to'] = 'p20prosugi@gmail.com';
    $message['from'] = \Drupal::config('system.site')->get('mail');
    $message['subject'] = t('@subject', ['@subject' => $params['subject']], $options);
    $message['body'][] = $params['message'];
  }
}

