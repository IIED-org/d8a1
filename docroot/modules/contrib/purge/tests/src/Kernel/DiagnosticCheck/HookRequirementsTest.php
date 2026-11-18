<?php

namespace Drupal\Tests\purge\Kernel\DiagnosticCheck;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Tests\purge\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that purge_requirements() passes on our diagnostic checks.
 *
 * @group purge
 */
#[Group('purge')]
#[RunTestsInSeparateProcesses]
class HookRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'purge_check_test',
    'purge_check_error_test',
    'purge_check_warning_test',
  ];

  /**
   * Tests that purge_requirements() passes on our diagnostic checks.
   */
  public function testHookRequirements(): void {
    $module_handler = \Drupal::service('module_handler');
    $module_handler->loadInclude('purge', 'install');
    $req = $module_handler->invoke('purge', 'requirements', ['runtime']);
    // Assert presence of all DiagnosticCheck plugins we know off.
    $this->assertTrue(isset($req["capacity"]));
    $this->assertTrue(isset($req["maxage"]));
    $this->assertTrue(isset($req["memoryqueuewarning"]));
    $this->assertTrue(isset($req["processorsavailable"]));
    $this->assertTrue(isset($req["purgersavailable"]));
    $this->assertTrue(isset($req["queuersavailable"]));
    $this->assertTrue(isset($req["alwayserror"]));
    $this->assertTrue(isset($req["alwayswarning"]));
    $this->assertFalse(isset($req["alwaysinfo"]));
    $this->assertFalse(isset($req["alwaysok"]));
    $this->assertFalse(isset($req["purgerwarning"]));
    $this->assertFalse(isset($req["queuewarning"]));
    // Assert check titles.
    $this->assertSame('Purge: Always a warning', $req['alwayswarning']['title']);
    $this->assertSame('Purge: Always an error', $req['alwayserror']['title']);
    // Assert that the descriptions come through.
    $this->assertSame('This is a warning for testing.', $req['alwayswarning']['description']);
    $this->assertSame('This is an error for testing.', $req['alwayserror']['description']);
    // Assert that the severities come through properly. Drupal 11.2+ expects
    // RequirementSeverity enum cases, older cores the legacy integer constants.
    if (class_exists(RequirementSeverity::class)) {
      $this->assertSame(RequirementSeverity::Warning, $req['alwayswarning']['severity']);
      $this->assertSame(RequirementSeverity::Error, $req['alwayserror']['severity']);
    }
    else {
      $this->assertSame(1, $req['alwayswarning']['severity']);
      $this->assertSame(2, $req['alwayserror']['severity']);
    }
    // Assert that the severity statuses come through properly.
    $this->assertSame('warning', $req['alwayswarning']['severity_status']);
    $this->assertSame('error', $req['alwayserror']['severity_status']);
    // Assert that the values come through properly.
    $this->assertTrue(is_string($req['capacity']['value']));
    $this->assertSame("0", $req['capacity']['value']);
  }

  /**
   * Tests that the status report receives our checks on every core version.
   *
   * Mirrors SystemManager::listRequirements(): Drupal 11.3+ skips
   * purge_requirements() because of #[LegacyRequirementsHook], so the rows
   * must come from the hook_runtime_requirements() implementation there.
   */
  public function testStatusReportRequirements(): void {
    $module_handler = \Drupal::service('module_handler');
    $module_handler->loadInclude('purge', 'install');
    $req = array_merge(
      $module_handler->invokeAll('requirements', ['runtime']),
      $module_handler->invokeAll('runtime_requirements'),
    );
    $this->assertArrayHasKey('alwayserror', $req);
    $this->assertArrayHasKey('alwayswarning', $req);
    $this->assertArrayNotHasKey('alwaysinfo', $req);
    $this->assertSame('Purge: Always an error', $req['alwayserror']['title']);
    $this->assertSame('error', $req['alwayserror']['severity_status']);
  }

}
