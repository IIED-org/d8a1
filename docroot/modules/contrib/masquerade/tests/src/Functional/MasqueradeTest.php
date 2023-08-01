<?php

namespace Drupal\Tests\masquerade\Functional;

/**
 * Tests form permissions and user switching functionality.
 *
 * @group masquerade
 */
class MasqueradeTest extends MasqueradeWebTestBase {

  /**
   * Tests masquerade user links.
   */
  public function testMasquerade() {
    $this->drupalLogin($this->adminUser);

    // Verify that a token is required.
    $this->drupalGet('user/0/masquerade');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('user/' . $this->authUser->id() . '/masquerade');
    $this->assertSession()->statusCodeEquals(403);

    // Verify that the admin user is able to masquerade.
    $this->assertSessionByUid($this->adminUser->id());
    $this->masqueradeAs($this->authUser);
    $this->assertSessionByUid($this->authUser->id(), $this->adminUser->id());
    $this->assertNoSessionByUid($this->adminUser->id());

    // Verify that a token is required to unmasquerade.
    $this->drupalGet('unmasquerade');
    $this->assertSession()->statusCodeEquals(403);

    // Verify that the web user cannot masquerade.
    $this->drupalGet('user/' . $this->adminUser->id() . '/masquerade', [
      'query' => [
        'token' => $this->drupalGetToken('user/' . $this->adminUser->id() . '/masquerade'),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    // Verify that the user can unmasquerade.
    $this->unmasquerade($this->authUser);
    $this->assertNoSessionByUid($this->authUser->id());
    $this->assertSessionByUid($this->adminUser->id());
  }

}
