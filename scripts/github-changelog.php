<?php

require 'vendor/autoload.php';

require_once __DIR__ . '/../lib/github-changelog.php';

function is_env_set() {
	return isset(
		$_SERVER['CIRCLE_PROJECT_USERNAME'],
		$_SERVER['CIRCLE_PROJECT_REPONAME'],
		$_SERVER['CHANGELOG_POST_TOKEN'],
	);
}

if ( ! is_env_set() ) {
	echo "The following environment variables need to be set:
    \tCIRCLE_PROJECT_USERNAME
    \tCIRCLE_PROJECT_REPONAME
    \tCHANGELOG_POST_TOKEN\n";
	exit( 1 );
}

$options = getopt(
	null,
	array(
		'link-to-pr', // Add link to the PR at the button of the changelog entry
		'start-marker:', // Text bellow line matching this param will be considered changelog entry
		'end-marker:', // Text untill this line will be considered changelog entry
		'wp-endpoint:', // Endpoint to wordpress site to create posts for
		'wp-status:', // Status to create changelog post with. Common scenarios are 'draft' or 'published'
		'wp-tag-ids:', // Default tag IDs to add to the changelog post
		'wp-channel-ids:', // Channel IDs to add to the changelog post
		'verify-commit-hash', // Use --verify-commit-hash=false in order to skip hash validation. This is usefull when testing the integration
		'debug', // Show debug information
	) 
);

if ( ! isset( $options['wp-endpoint'] ) ) {
	echo "Argument --wp-endpoint is mandatory.\n";
	exit( 1 );
}

define( 'SHA1', $_SERVER['CIRCLE_SHA1'] ?? '' );
define( 'PROJECT_USERNAME', $_SERVER['CIRCLE_PROJECT_USERNAME'] ?? '' );
define( 'PROJECT_REPONAME', $_SERVER['CIRCLE_PROJECT_REPONAME'] ?? '' );
define( 'CHANGELOG_POST_TOKEN', $_SERVER['CHANGELOG_POST_TOKEN'] ?? '' );
define( 'GITHUB_TOKEN', $_SERVER['GITHUB_TOKEN'] ?? '' );

define( 'GITHUB_ENDPOINT', 'https://api.github.com/repos/' . PROJECT_USERNAME . '/' . PROJECT_REPONAME . '/pulls?per_page=10&sort=updated&direction=desc&state=closed' );
define( 'PR_CHANGELOG_START_MARKER', $options['start-marker'] ?? '<h2>Changelog Description' );
define( 'PR_CHANGELOG_END_MARKER', $options['end-marker'] ?? '<h2>' );
define( 'WP_CHANGELOG_ENDPOINT', $options['wp-endpoint'] );
define( 'WP_CHANGELOG_STATUS', $options['wp-status'] ?? 'draft' );
define( 'WP_CHANGELOG_TAG_IDS', $options['wp-tag-ids'] );
define( 'WP_CHANGELOG_CHANNEL_IDS', $options['wp-channel-ids'] );
define( 'LINK_TO_PR', $options['link-to-pr'] ?? true );
define( 'VERIFY_COMMIT_HASH', $options['verify-commit-hash'] ?? true );
define( 'DEBUG', array_key_exists( 'debug', $options ) );

create_changelog_for_last_pr();
