<?php

namespace Drupal\forms_steps\Commands;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\forms_steps\Entity\FormsSteps;
use Drupal\forms_steps\Entity\Workflow;
use Drush\Commands\DrushCommands;

/**
 * Class FormsStepsCommands
 *
 * @package Drupal\forms_steps\Commands
 */
class FormsStepsCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * EntityTypeManager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * UUID Service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  private $uuidService;

  /**
   * Constructor().
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid_service) {
    parent::__construct();

    $this->entityTypeManager = $entity_type_manager;
    $this->uuidService = $uuid_service;
  }


  /**
   * Existing or migrated content to be added to a workflow.
   *
   * @param string $workflow
   *  The forms_steps name machine.
   * @param $entity_type
   *  The entity type.
   * @param $bundle
   *  The bundle.
   * @param $id
   *  The entity id.
   * @param $form_mode
   *  The form_mode id.
   * @param $step
   *  The step id.
   * @param array $options
   *  A list of options.
   *
   * @return array|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @command forms_steps:attach-entity
   * @aliases fs-attach-entity
   * @options instance_id An existing instance id.
   * @options ignore_entity_id_check Ignore entity id check.
   * @usage forms_steps:attach-entity example_1 node article 12345678 default step1
   *  Attach a specific entity entry to a new forms steps workflow.
   * @usage forms_steps:attach-entity example_1 node article 12345678 default step1 --instance_id=51e4e52a-d9d9-44c4-9aa1-9b075255e18c
   *  Attach a specific entity entry to an existing forms steps workflow.
   */
  public function attachEntityToStep(
    $workflow,
    $entity_type,
    $bundle,
    $id,
    $form_mode,
    $step,
    $options = [
      'instance_id' => NULL,
      'ignore_entity_id_check' => false,
    ]
  ) {

    /** @var FormsSteps $forms_steps */
    $forms_steps = FormsSteps::load($workflow);
    if (!$forms_steps) {
      $message = $this->t("The workflow '@workflow' doesn't exists.", ['@workflow' => $workflow]);
      $this->outputError($message->render());

      return;
    }

    if (!$forms_steps->hasStep($step)) {
      $message = $this->t(
        "The step '@step' doesn't exists for the workflow '@workflow'.",
        ['@workflow' => $workflow, '@step' => $step]
      );
      $this->outputError($message->render());

      return;
    }

    if (!$options['ignore_entity_id_check']) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
      if (!$entity) {
        $message = $this->t(
          "The @entity_type entity id '@id' doesn't exists.",
          ['@entity_type' => $entity_type, '@id' => $id]
        );
        $this->outputError($message->render());

        return;
      }
    }

    try {
      $instanceId = $options['instance_id'] ?? $this->uuidService->generate();

      /** @var FormsSteps $workflow */
      $workflow = $this->entityTypeManager
        ->getStorage(Workflow::ENTITY_TYPE)
        ->create(
          [
            'instance_id' => $instanceId,
            'entity_type' => $entity_type,
            'bundle' => $bundle,
            'step' => $step,
            'entity_id' => $id,
            'form_mode' => $form_mode,
            'forms_steps' => $workflow,
          ]
        );

      $workflow->save();

      return ['id' => $workflow->id(), 'instance_id' => $instanceId];
    } catch (\Exception $ex) {
      $this->logger()->critical(
        'An exception has been raised while attaching an entity to a workflow step using drush. @exception',
        [
          '@exception' => $ex->getMessage()
        ]
      );
      $this->logger()->critical($ex->getTraceAsString());
    }

    $message = $this->t('Error while inserting the workflow. Check watch dog for more information.');
    $this->outputError($message->render());

    return;
  }

  private function outputError($message) {
    $this->yell($message, 40, 'red');
  }

}
