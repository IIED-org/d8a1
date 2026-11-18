<?php

namespace Drupal\purge_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for purger configuration forms.
 */
class PurgerFormController extends ControllerBase {

  /**
   * The 'purge.purgers' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

  /**
   * The 'purge.processors' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface
   */
  protected $purgeProcessors;

  /**
   * Construct the PurgerFormController.
   *
   * @param \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface $purge_purgers
   *   The purge executive service, which wipes content from external caches.
   * @param \Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface $purge_processors
   *   The purge processors service.
   */
  final public function __construct(PurgersServiceInterface $purge_purgers, ProcessorsServiceInterface $purge_processors) {
    $this->purgePurgers = $purge_purgers;
    $this->purgeProcessors = $purge_processors;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('purge.purgers'),
      $container->get('purge.processors')
    );
  }

  /**
   * Render the purger add form.
   *
   * @return array
   *   The render array.
   */
  public function addForm() {
    if (count($this->purgePurgers->getPluginsAvailable())) {
      return $this->formBuilder()->getForm("Drupal\purge_ui\Form\PurgerAddForm");
    }
    throw new NotFoundHttpException();
  }

  /**
   * Retrieve the plugin definition for the given instance ID.
   *
   * @param string $id
   *   Unique instance ID for the purger instance requested.
   *
   * @return array|false
   *   The definition or FALSE when it doesn't exist.
   */
  protected function getPurgerPluginDefinition($id) {
    $enabled = $this->purgePurgers->getPluginsEnabled();
    if (!isset($enabled[$id])) {
      return FALSE;
    }
    return $this->purgePurgers->getPlugins()[$enabled[$id]];
  }

  /**
   * Render the purger configuration form.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   * @param bool $dialog
   *   Determines if the modal dialog variant of the form should be rendered.
   *
   * @return array
   *   The render array.
   */
  public function configForm($id, $dialog) {
    if ($definition = $this->getPurgerPluginDefinition($id)) {
      if (isset($definition['configform']) && !empty($definition['configform'])) {
        return $this->formBuilder()->getForm(
          $definition['configform'],
          [
            'id' => $id,
            'dialog' => $dialog,
          ]
        );
      }
    }
    throw new NotFoundHttpException();
  }

  /**
   * Route title callback.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function configFormTitle($id) {
    if ($definition = $this->getPurgerPluginDefinition($id)) {
      if (isset($definition['configform']) && !empty($definition['configform'])) {
        $label = $this->purgePurgers->getLabels()[$id];
        return $this->t('Configure @label', ['@label' => $label]);
      }
    }
    return $this->t('Configure');
  }

  /**
   * Render the purger delete form.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   *
   * @return array
   *   The render array.
   */
  public function deleteForm($id) {
    // Although it might look like a logic bug that we aren't checking whether
    // the ID exists and always return the form, this is a must. Else submitting
    // the form never works as the purger has been deleted before.
    if (!($definition = $this->getPurgerPluginDefinition($id))) {
      $definition = ['label' => ''];
    }
    return $this->formBuilder()->getForm(
      "\Drupal\purge_ui\Form\PurgerDeleteForm",
      ['id' => $id, 'definition' => $definition]
    );
  }

  /**
   * Render the purger detail form.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   *
   * @return array
   *   The render array.
   */
  public function detailForm($id) {
    if ($definition = $this->getPurgerPluginDefinition($id)) {
      return $this->formBuilder()->getForm(
        "\Drupal\purge_ui\Form\PluginDetailsForm",
        ['details' => $definition['description']]
      );
    }
    throw new NotFoundHttpException();
  }

  /**
   * Route title callback.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function detailFormTitle($id) {
    if (!($this->getPurgerPluginDefinition($id))) {
      return $this->t('Purger');
    }
    return $this->purgePurgers->getLabels()[$id];
  }

  /**
   * Render the purger move form.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   * @param string $direction
   *   Either 'up' or 'down' are valid directions to move execution order in.
   *
   * @return array
   *   The render array.
   */
  public function moveForm($id, $direction) {
    if ($definition = $this->getPurgerPluginDefinition($id)) {
      if (in_array($direction, ['up', 'down'])) {
        return $this->formBuilder()->getForm(
          "\Drupal\purge_ui\Form\PurgerMoveForm",
          ['id' => $id, 'direction' => $direction, 'definition' => $definition]
        );
      }
    }
    throw new NotFoundHttpException();
  }

  /**
   * Render the purger invalidate form.
   *
   * @param string $id
   *   Unique instance ID for the purger instance.
   *
   * @return array
   *   The form render array.
   */
  public function invalidateForm($id) {
    if (!$this->purgeProcessors->get('purge_ui_invalidate')) {
      throw new NotFoundHttpException();
    }
    if ($definition = $this->getPurgerPluginDefinition($id)) {
      return $this->formBuilder()->getForm(
        '\Drupal\purge_ui\Form\PurgerInvalidateForm',
        ['id' => $id, 'definition' => $definition]
      );
    }
    throw new NotFoundHttpException();
  }

}
