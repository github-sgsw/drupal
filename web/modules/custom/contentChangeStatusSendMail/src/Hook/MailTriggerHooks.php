<?php

namespace Drupal\content_change_status_send_mail\Hook;

use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\node\NodeInterface;

class MailTriggerHooks {

  #[Hook('node_update')]
  public function entityUpdate(NodeInterface $entity) {
    $work_flow_machin_name = $this->getWorkflowMachinName($entity);

    if ($work_flow_machin_name == 'draft') return;

    /** @var MailManagerInterface $mailManager */
    $mail_manager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    $params['subject'] = $entity->label();
    $params['message'] = <<<TEXT
      {$this->getWorkflowDispName($entity)}

      {$entity->getRevisionUser()->getDisplayName()} から、ワークフローが{$this->getWorkflowDispName($entity)}になりました。
      内容の確認をお願いします。

      対象コンテンツ編集画面
      {$entity->toUrl('edit-form', ['absolute' => TRUE])->toString()}

      メッセージ
      {$entity->getRevisionLogMessage()}
    TEXT;

    match ($work_flow_machin_name) {
      'published' => $this->rejectMail($entity, $mail_manager, $params),
      'unpublished' => $this->rejectMail($entity, $mail_manager, $params),
      'pending_approval' => $this->pendingApprovalMail($entity, $mail_manager, $params),
      'reject' => $this->rejectMail($entity, $mail_manager, $params),
      default => $this->rejectMail($entity, $mail_manager, $params)
    };
  }

  private function rejectMail(NodeInterface $entity, MailManagerInterface $mail_manager, $params) {
    $mail_manager->mail(
      'content_change_status_send_mail',
      'content_moderation_notification',
      $this->getPreviousRevisionNode($entity)->getRevisionUser()->getEmail(),
      'ja',
      $params
    );
  }

  /**
   * コンテンツの公開権限を持つユーザーのみに承認待ちメールを送付する
   */
  private function pendingApprovalMail(NodeInterface $entity, MailManagerInterface $mail_manager, $params) {

    $users = $this->getPermissionRole('use default transition publish')
        |> $this->getRoleUser(...)
        |>\Drupal::entityTypeManager()->getStorage('user')->loadMultiple(...);

    $mail_manager->mail(
      'content_change_status_send_mail',
      'content_moderation_notification',
      implode(',', array_map(fn($user) => $user->getEmail(), $users)),
      'ja',
      $params
    );
  }

  /**
   * @param String $permission 権限名
   * @return array 特定の権限を所有するロール
   */
  private function getPermissionRole(String $permission) {
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    return array_filter($roles, function ($role) use ($permission) {
      /** @var \Drupal\user\RoleInterface $role */
      return $role->hasPermission($permission);
    });
  }

  /**
   * @return array ロールに紐づくユーザー
   */
  private function getRoleUser(array $roles) {
    return \Drupal::entityTypeManager()->getStorage('user')
      ->getQuery()
      ->accessCheck()
      ->condition('status', 1)
      ->condition('roles', array_values(array_keys($roles)), 'IN')
      ->execute();
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
   * @return NodeInterface 差し戻し対象コンテンツのリビジョン
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

