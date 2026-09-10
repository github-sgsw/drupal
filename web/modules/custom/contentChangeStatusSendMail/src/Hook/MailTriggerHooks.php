<?php

namespace Drupal\content_change_status_send_mail\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\node\NodeInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MailTriggerHooks implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entity_type_manager,
    protected MailManagerInterface $mail_manager,
    protected ModerationInformationInterface $moderation_information,
    protected LanguageManagerInterface $language_manager,
    protected AccountInterface $current_user,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('content_moderation.moderation_information'),
      $container->get('language_manager'),
      $container->get('current_user')
    );
  }

  #[Hook('node_update')]
  public function entityUpdate(NodeInterface $entity) {
    $work_flow_machin_name = $this->getWorkflowMachinName($entity);

    if ($work_flow_machin_name == 'draft') return;

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
      'published' => $this->rejectMail($entity, $params),
      'unpublished' => $this->rejectMail($entity, $params),
      'pending_approval' => $this->pendingApprovalMail($params),
      'reject' => $this->rejectMail($entity, $params),
      default => $this->rejectMail($entity, $params)
    };
  }

  private function rejectMail(NodeInterface $entity, array $params) {
    $this->mail_manager->mail(
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
  private function pendingApprovalMail(array $params) {
    $users = $this->getPermissionRole('use default transition publish')
        |> $this->getRoleUser(...)
        |> $this->entity_type_manager->getStorage('user')->loadMultiple(...);

    $this->mail_manager->mail(
      'content_change_status_send_mail',
      'content_moderation_notification',
      implode(',', array_map(fn($user) => $user->getEmail(), $users)),
      'ja',
      $params
    );
  }

  /**
   * @param String $permission 権限名
   * @return RoleInterface[] 特定の権限を所有するロール
   */
  private function getPermissionRole(String $permission) {
    $roles = $this->entity_type_manager->getStorage('user_role')->loadMultiple();
    return array_filter($roles, function ($role) use ($permission) {
      /** @var \Drupal\user\RoleInterface $role */
      return $role->hasPermission($permission);
    });
  }

  /**
   * @param RoleInterface[] $roles ロール
   * @return UserInterface[] ロールに紐づくユーザー
   */
  private function getRoleUser(array $roles) {
    return $this->entity_type_manager->getStorage('user')
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
    $message['subject'] = $this->t('@subject', ['@subject' => $params['subject']], $options);
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
    return $this->moderation_information
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
    $node_storage = $this->entity_type_manager->getStorage('node');
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

