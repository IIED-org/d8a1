<?php

namespace Drupal\content_moderation_notifications\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentModerationNotificationFormBase.
 *
 * Typically, we need to build the same form for both adding a new entity,
 * and editing an existing entity. Instead of duplicating our form code,
 * we create a base class. Drupal never routes to this class directly,
 * but instead through the child classes of ContentModerationNotificationAddForm
 * and ContentModerationNotificationEditForm.
 *
 * @package Drupal\content_moderation_notifications\Form
 *
 * @ingroup content_moderation_notifications
 */
class ContentModerationNotificationsFormBase extends EntityForm {

  /**
   * Entity Query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct the ContentModerationNotificationFormBase.
   *
   * For simple entity forms, there's no need for a constructor. Our form
   * base, however, requires an entity query factory to be injected into it
   * from the container. We later use this query factory to build an entity
   * query for the exists() method.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   An entity query factory for the entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity type manager for the entity type.
   */
  public function __construct(QueryFactory $query_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityQueryFactory = $query_factory;
  }

  /**
   * Factory method for ContentModerationNotificationFormBase.
   *
   * When Drupal builds this class it does not call the constructor directly.
   * Instead, it relies on this method to build the new object. Why? The class
   * constructor may take multiple arguments that are unknown to Drupal. The
   * create() method always takes one parameter -- the container. The purpose
   * of the create() method is twofold: It provides a standard way for Drupal
   * to construct the object, meanwhile it provides you a place to get needed
   * constructor parameters from the container.
   *
   * In this case, we ask the container for an entity query factory. We then
   * pass the factory to our class as a constructor parameter.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.query'), $container->get('entity_type.manager'));
  }

  /**
   * Update options.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Formstate.
   *
   * @return mixed
   *   Returns the updated options.
   */
  public static function updateWorkflowTransitions(array $form, FormStateInterface &$form_state) {
    return $form['transitions_wrapper'];
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   *
   * Builds the entity add/edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the content_moderation_notification
   *   add/edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve a list of all possible workflows.
    /** @var \Drupal\workflows\WorkflowInterface[] $workflows */
    $workflows = $this->entityTypeManager->getStorage('workflow')->loadMultiple();

    // Return early if there are no available workflows.
    if (empty($workflows)) {
      $form['no_workflows'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No workflows available. <a href=":url">Manage workflows</a>.', [':url' => Url::fromRoute('entity.workflow.collection')->toString()]),
      ];
      return $form;
    }

    // Get anything we need from the base class.
    $form = parent::buildForm($form, $form_state);

    // Drupal provides the entity to us as a class variable. If this is an
    // existing entity, it will be populated with existing values as class
    // variables. If this is a new entity, it will be a new object with the
    // class of our entity. Drupal knows which class to call from the
    // annotation on our ContentModerationNotification class.
    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $content_moderation_notification */
    $content_moderation_notification = $this->entity;

    // Build the options array of workflows.
    $workflow_options = [];
    foreach ($workflows as $workflow_id => $workflow) {
      $workflow_options[$workflow_id] = $workflow->label();
    }

    // Default to the first workflow in the list.
    $workflow_keys = array_keys($workflow_options);

    if ($form_state->getValue('workflow')) {
      $selected_workflow = $form_state->getValue('workflow');
    }
    elseif (isset($content_moderation_notification->workflow)) {
      $selected_workflow = $content_moderation_notification->workflow;
    }
    else {
      $selected_workflow = array_shift($workflow_keys);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $content_moderation_notification->label(),
      '#description' => $this->t('The label for this notification.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $content_moderation_notification->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$content_moderation_notification->isNew(),
    ];

    // Allow the workflow to be selected, this will dynamically update the
    // available transition lists.
    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#options' => $workflow_options,
      '#default_value' => $selected_workflow,
      '#required' => TRUE,
      '#description' => $this->t('Select a workflow'),
      '#ajax' => [
        'wrapper' => 'workflow_transitions_wrapper',
        'callback' => static::class . '::updateWorkflowTransitions',
      ],
    ];

    // Ajax replaceable fieldset.
    $form['transitions_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="workflow_transitions_wrapper">',
      '#suffix' => '</div>',
    ];

    // Transitions.
    $state_transitions_options = [];
    $state_transitions = $workflows[$selected_workflow]->getTypePlugin()->getTransitions();
    foreach ($state_transitions as $key => $transition) {
      $state_transitions_options[$key] = $transition->label();
    }

    $form['transitions_wrapper']['transitions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Transitions'),
      '#options' => $state_transitions_options,
      '#default_value' => isset($content_moderation_notification->transitions) ? $content_moderation_notification->transitions : [],
      '#required' => TRUE,
      '#description' => $this->t('Select which transitions triggers this notification.'),
    ];

    // Role selection.
    $roles_options = user_role_names(TRUE);

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $roles_options,
      '#default_value' => $content_moderation_notification->getRoleIds(),
      '#description' => $this->t('Send notifications to all users with these roles.  Addresses will be added to the BCC field.'),
    ];

    // Send email to author?
    $form['author'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email the author?'),
      '#default_value' => $content_moderation_notification->sendToAuthor(),
      '#description' => $this->t('Send notifications to the current author of the content by adding Author\'s email address to the TO address.'),
    ];

    $form['to'] = [
      '#type' => 'textarea',
      '#rows' => $this->textareaCountLines($content_moderation_notification->getTo()),
      '#title' => $this->t('TO email addresses'),
      '#default_value' => $content_moderation_notification->getTo(),
      '#description' => $this->t('Send notifications to these email addresses in the TO: field. Separate emails with commas or newlines. You may use Twig templating code in this field.'),
    ];

    $form['cc'] = [
      '#type' => 'textarea',
      '#rows' => $this->textareaCountLines($content_moderation_notification->getCc()),
      '#title' => $this->t('CC email addresses'),
      '#default_value' => $content_moderation_notification->getCc(),
      '#description' => $this->t('Send notifications to these email addresses in the Cc: field. Separate emails with commas or newlines. You may use Twig templating code in this field.'),
    ];

    $form['bcc'] = [
      '#type' => 'textarea',
      '#rows' => $this->textareaCountLines($content_moderation_notification->getBcc()),
      '#title' => $this->t('BCC email addresses'),
      '#default_value' => $content_moderation_notification->getBcc(),
      '#description' => $this->t('Send notifications to these email addresses in the Bcc: field. Put email addresses here if you do not want recipients to see each other\'s email addresses. Separate emails with commas or newlines. You may use Twig templating code in this field. <em>(You should have an address listed in the To: field as well, as some email systems will promote Bcc to To: if To: is empty.)</em>'),
    ];

    $form['replyto'] = [
      '#type' => 'textarea',
      '#rows' => $this->textareaCountLines($content_moderation_notification->getReplyTo()),
      '#title' => $this->t('REPLY-TO email addresses'),
      '#default_value' => $content_moderation_notification->getReplyTo(),
      '#description' => $this->t('Specify a Reply-To address for the email.  You may use Twig templating code in this field.'),
    ];

    $form['from'] = [
      '#type' => 'textarea',
      '#rows' => $this->textareaCountLines($content_moderation_notification->getFrom()),
      '#title' => $this->t('FROM email address'),
      '#default_value' => $content_moderation_notification->getFrom(),
      '#description' => $this->t('Send notifications From this email address. You may use Twig templating code in this field.  If this field is empty, the site email address <em>@email</em> will be used. You should usually use the REPLY-TO header instead of FROM to comply with <a href="@link">DMARC</a> policies.', ['@email' => \Drupal::config('system.site')->get('mail'), '@link' => 'https://dmarc.org/wiki/FAQ']),
    ];

    // Email subject line.
    $form['subject'] = [
      '#type' => 'textarea',
      '#rows' => $this->textareaCountLines($content_moderation_notification->getSubject()),
      '#title' => $this->t('Email Subject'),
      '#default_value' => $content_moderation_notification->getSubject(),
      '#description' => $this->t('The subject of the message.  Multiple lines will joined into a single line.  You may use Twig templating code in this field.'),
    ];

    // Email body content.
    $form['body'] = [
      '#type' => 'text_format',
      '#format' => $content_moderation_notification->getMessageFormat() ?: filter_default_format(),
      '#title' => $this->t('Email Body'),
      '#default_value' => $content_moderation_notification->getMessage(),
      '#description' => $this->t('The body of the email message.  You may use Twig templating code in this field, but make sure there is no accidental HTML inside the Twig tags if editing with the WYSIWYG editor (it may be easier to edit using the Source option, if available.)'),
    ];

    $form['abort'] = [
      '#type' => 'textfield',
      '#rows' => 1,
      '#title' => $this->t('"Abort Sending" Email Address'),
      '#default_value' => $content_moderation_notification->getAbort(),
      '#description' => $this->t('Prevent all notifications from being sent if this email address is present in the TO, CC or BCC fields.  If this field is empty will not affect any notifications.  (Use something that would not normally be delivered, such as <em>abort@example.com</em>.'),
    ];

    // Enable Debug Mode?
    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Notification'),
      '#default_value' => $content_moderation_notification->getDebug(),
      '#description' => $this->t('Display notification message details and prevent email from being sent.'),
    ];

    $tips = <<<HTML
<table>
  <tr>
    <th>Placeholder</th>
    <th>Example</th>
    <th>Description</th>
  </tr>
  <tr>
    <td>{{ workflow }}</td>
    <td>Editorial</td>
    <td>The Workflow being executed (user-friendly label)</td>
  </tr>
  <tr>
    <td>{{ workflow_id }}</td>
    <td>editorial</td>
    <td>The machine name of the Workflow being executed</td>
  </tr>
  <tr>
    <td>{{ transition }}</td>
    <td>Create New Draft</td>
    <td>The Workflow Transition being executed (user-friendly label)</td>
  </tr>
  <tr>
    <td>{{ transition_id }}</td>
    <td>create_new_draft</td>
    <td>The machine name of the Workflow Transition being executed</td>
  </tr>
  <tr>
    <td>{{ prev_state }}</td>
    <td>Draft</td>
    <td>The previous Workflow State, before the content was edited (user-friendly label)</td>
  </tr>
  <tr>
    <td>{{ prev_state_id }}</td>
    <td>draft</td>
    <td>The machine name of the previous Workflow State, before the content was edited</td>
  </tr>
  <tr>
    <td>{{ new_state }} or {{ state }}</td>
    <td>Published</td>
    <td>The new Workflow State, after the content was edited (user-friendly label) <br />[Not working yet - only shows machine name]</td>
  </tr>
  <tr>
    <td>{{ new_state_id }} or {{ state_id }}</td>
    <td>published</td>
    <td>The machine name of the new Workflow State, after the content was edited</td>
  </tr>
  <tr>
    <td>{{ user.email }}</td>
    <td>user@example.com</td>
    <td>The current logged-in user's email address</td>
  </tr>
  <tr>
    <td>{{ user.username }}</td>
    <td>druplicon</td>
    <td>The current logged-in user's username / login name</td>
  </tr>
  <tr>
    <td>{{ user.displayname }}</td>
    <td>Druplicon Logoman</td>
    <td>The current logged-in user's display name</td>
  </tr>
  <tr>
    <td>{{ entity.Owner.mail.0.value }}</td>
    <td>author@example.com</td>
    <td>The author's email address</td>
  </tr>
  <tr>
    <td>{{ entity.Owner.username }}</td>
    <td>aardvark</td>
    <td>The author's username / login name</td>
  </tr>
  <tr>
    <td>{{ entity.Owner.displayname }}</td>
    <td>Aardvark Jones</td>
    <td>The author's display name</td>
  </tr>
  <tr>
    <td>{{ entity.Owner.id }}</td>
    <td>43</td>
    <td>The author's User ID (UID)</td>
  </tr>
  <tr>
    <td>{{ entity.title.value }}</td>
    <td>The Prince Groom</td>
    <td>The entity's Title</td>
  </tr>
  <tr>
    <td>{{ entity.bundle }}</td>
    <td>basic_page</td>
    <td>Entity Bundle (Content type system name)</td>
  </tr>
  <tr>
    <td>{{ entity.type.entity.label }}</td>
    <td>Basic Page</td>
    <td>Entity Bundle Label (Content type name)</td>
  </tr>
  <tr>
    <td>{{ entity.nid.value }}</td>
    <td>1235</td>
    <td>The entity ID / Node ID</td>
  </tr>
  <tr>
    <td>{{ entity.vid.value }}</td>
    <td>6890</td>
    <td>The entity / node Revision ID (VID)</td>
  </tr>
  <tr>
    <td>{{ entity.uuid.value }}</td>
    <td>68d0a104-a5bf-466c-a429-f871d91f9581</td>
    <td>The entity's Universally Unique ID (UUID)</td>
  </tr>
  <tr>
    <td>{# comment goes here #}</td>
    <td>{# check the editor exists #}</td>
    <td>Put comments in the code that will not affect output</td>
  </tr>
  <tr>
    <td>{% if entity.bundle == 'article' %}<br />&nbsp;&nbsp;{{ field_editor_user_ref.entity.mail.0.value }}<br />{% endif %}</td>
    <td>editor_bob@example.com</td>
    <td>Twig statements for conditional logic</td>
  </tr>
  <tr>
    <td>{% include('my_template.twig.html') %}</td>
    <td>{{ 'my_template output here'|title }}</td>
    <td>Include external template file from filesystem (if you don't want (much) Twig code in your database)</td>
  </tr>
  <tr>
    <td>{{ site_email }}</td>
    <td>noreply@example.com</td>
    <td>The default site email address</td>
  </tr>
  <tr>
    <td>{{ site_name }}</td>
    <td>Example Warehouse</td>
    <td>The site's name</td>
  </tr>
  <tr>
    <td>{{ site_slogan }}</td>
    <td>All The Best Examples In One Place</td>
    <td>The site's slogan</td>
  </tr>
  <tr>
    <td>{{ site_front }}</td>
    <td>/node/1</td>
    <td>The site's front page path</td>
  </tr>
  <tr>
    <td>{{ base_url }}</td>
    <td>https://example.com/</td>
    <td>The site's base URL</td>
  </tr>

</table>
HTML;

    $form['tips'] = array(
      '#type' => 'details',
      '#title' => t('Common Replacement Patterns'),
      '#open' => FALSE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
      '#description' => t($tips),

    );

    // Return the form.
    return $form;
  }

  /**
   * Checks for an existing content_moderation_notification.
   *
   * @param string|int $entity_id
   *   The entity ID.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this format already exists, FALSE otherwise.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    // Use the query factory to build a new entity query.
    $query = $this->entityQueryFactory->get('content_moderation_notification');

    // Query the entity ID to see if its in use.
    $result = $query->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute();

    // We don't need to return the ID, only if it exists or not.
    return (bool) $result;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * To set the submit button text, we need to override actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actins from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Return the result.
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   *
   * Saves the entity. This is called after submit() has built the entity from
   * the form values. Do not override submit() as save() is the preferred
   * method for entity form controllers.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function save(array $form, FormStateInterface $form_state) {
    // EntityForm provides us with the entity we're working on.
    $content_moderation_notification = $this->getEntity();

    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class. PHP
    // allows Drupal to do this even if the method is not defined ahead of
    // time.
    $status = $content_moderation_notification->save();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      drupal_set_message($this->t('Notification <a href=":url">%label</a> has been updated.', ['%label' => $content_moderation_notification->label(), ':url' => $content_moderation_notification->toUrl('edit-form')->toString()]));
      $this->logger('content_moderation_notifications')->notice('Notification has been updated.', ['%label' => $content_moderation_notification->label()]);
    }
    else {
      // If we created a new entity...
      drupal_set_message($this->t('Notification <a href=":url">%label</a> has been added.', ['%label' => $content_moderation_notification->label(), ':url' => $content_moderation_notification->toUrl('edit-form')->toString()]));
      $this->logger('content_moderation_notifications')->notice('Notification has been added.', ['%label' => $content_moderation_notification->label()]);
    }

    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.content_moderation_notification.collection');
  }

  /**
   * Returns the number of rows to show in a text area based on the number of lines in a string.
   *
   * @param string $str
   *   The string to count newlines in.
   *
   * @return int
   *   The number of rows to display for the textarea
   */
  private function textareaCountLines($str) {
    $num_lines = substr_count($str, "\n");
    return $num_lines + 1;

  }

}
