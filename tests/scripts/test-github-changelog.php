<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/github-changelog.php';

define( 'PR_CHANGELOG_START_MARKER', '<h2>Changelog Description' );
define( 'PR_CHANGELOG_END_MARKER', '<h2>' );
define( 'LINK_TO_PR', false );

class GitHub_Changelog_Test extends TestCase {
	public function test_get_changelog_html(): void {
		$pr = array();

		$pr['body'] = '# Description
		
Changes were made

## A different section

Content here

## Changelog Description

### This is my changelog title

<!-- An HTML Comment -->

Things we did:

* Fixed a bug
* Fixed another bug

## And another section that isn\'t a changelog

Foo Bar!';

		$changelog = get_changelog_html( $pr );

		$this->assertEquals(
			'<h3>This is my changelog title</h3>
<p>Things we did:</p>
<ul>
<li>Fixed a bug</li>
<li>Fixed another bug</li>
</ul>',
			$changelog 
		);
	}
}
