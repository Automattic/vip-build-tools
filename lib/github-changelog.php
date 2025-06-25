<?php

function debug( $arg ) {
	if ( ! DEBUG ) {
		return;
	}

	echo 'DEBUG: ' . print_r( $arg, true );
}

/**
 * Make a GitHub request.
 *
 * @param string $url The URL to make the request to
 * @param array $headers The headers to send with the request
 * @return array The response from the GitHub API
 */
function make_github_request( $url, $headers = array() ) {
	$ch      = curl_init( $url );
	$headers = array_merge( $headers, array( 'User-Agent: script' ) );

	if ( isset( $_SERVER['GITHUB_TOKEN'] ) ) {
		$headers = array_merge( $headers, array( 'Authorization:token ' . GITHUB_TOKEN ) );
	}

	curl_setopt( $ch, CURLOPT_HEADER, 0 );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
	$data      = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	if ( false === $data || $http_code >= 400 ) {
		echo "Failed to fetch data from GitHub API. HTTP code: $http_code\n";
		exit( 1 );
	}

	return json_decode( $data, true );
}

/**
 * Wrapper which paginates the GitHub request to fetch everything. Useful when
 * the endpoint doesn't include pagination information.
 *
 * @param string $url URL to get
 * @return array $data The array with all the data
 */
function make_github_request_paginated( $url ) {
	$all_data       = array();
	$pagination_url = $url . '?per_page=100&page=';

	for ( $page = 1; ; $page++ ) {
		$data = make_github_request( $pagination_url . $page );
		if ( empty( $data ) ) {
			break;
		}
		$all_data = array_merge( $all_data, $data );
	}

	return $all_data;
}

/**
 * Get the PRs referenced in the commits of a PR.
 *
 * @param array $pr The PR object from GitHub API
 * @return array The PR objects from PR IDs referenced in the commits
 */
function get_referenced_prs( $pr ) {
	if ( ! isset( $pr['_links']['commits']['href'] ) ) {
		return array();
	}

	$commits = make_github_request_paginated( $pr['_links']['commits']['href'] );
	$pr_ids  = array();

	foreach ( $commits as $commit ) {
		$msg = $commit['commit']['message'];

		debug( "Checking commit: {$commit['sha']}" );
		$pr_ids_from_commit = get_pr_ids_from_message( $msg );
		if ( ! empty( $pr_ids_from_commit ) ) {
			$pr_ids = array_merge( $pr_ids, $pr_ids_from_commit );
		}
	}

	$prs = fetch_prs_by_ids( array_unique( $pr_ids ) );

	return $prs;
}

/**
 * Fetch the last closed PR.
 *
 * @return array The last closed PR
 */
function fetch_last_pr() {
	$url = GITHUB_PR_ENDPOINT . '?per_page=10&sort=updated&direction=desc&state=closed';

	if ( '' !== GITHUB_BASE_BRANCH ) {
		$url .= '&base=' . GITHUB_BASE_BRANCH;
	}

	$last_prs = make_github_request( $url );

	$merged_prs = array_filter(
		$last_prs,
		function ( $pr ) {
			return $pr['merged_at'] ?? '';
		}
	);

	$merged_prs_array = array_values( $merged_prs );

	if ( empty( $merged_prs_array ) ) {
		return null;
	}

	return $merged_prs_array[0];
}

/**
 * Fetch a PR by its ID.
 *
 * @param int $pr_id The ID of the PR to fetch
 * @return array The PR object from GitHub API
 */
function fetch_pr( $pr_id ) {
	$url = GITHUB_PR_ENDPOINT . '/' . $pr_id;

	return make_github_request( $url );
}

/**
 * Gets the changelog section in the PR description.
 *
 * @param string $description The PR description
 * @return string The changelog section in the PR description
 */
function get_changelog_section_in_description_html( $description ) {
	$found_changelog_header = false;
	$result                 = '';
	foreach ( preg_split( "/\n/", $description ) as $line ) {
		if ( strpos( $line, PR_CHANGELOG_START_MARKER ) === 0 ) {
			$found_changelog_header = true;
		} elseif ( $found_changelog_header ) {
			if ( strpos( $line, PR_CHANGELOG_END_MARKER ) === 0 ) {
				// We have hit next section
				break;
			}
			$result = $result . "\n" . $line;
		}
	}
	return $result;
}

/**
 * Finds the changelog section in the PR description and returns the HTML.
 *
 * @param array $pr The PR object from GitHub API
 * @param bool $link_to_pr Whether to include a link to the PR in the changelog HTML
 * @return string The changelog HTML
 */
function get_changelog_html( $pr, $link_to_pr = LINK_TO_PR ) {
	if ( empty( $pr['body'] ) ) {
		return null;
	}

	$parsedown        = new Parsedown();
	$body             = preg_replace( '/<!--(.|\s)*?-->/', '', $pr['body'] );
	$description_html = $parsedown->text( $body );

	$changelog_html = get_changelog_section_in_description_html( $description_html );

	if ( empty( $changelog_html ) ) {
		return null;
	}

	if ( $link_to_pr && strpos( $changelog_html, $pr['html_url'] ) === false ) {
		$changelog_html = $changelog_html . "\n\n" . $parsedown->text( $pr['html_url'] );
	}
	return trim( $changelog_html );
}

/**
 * Parses the changelog HTML to get the title and content for the changelog post.
 *
 * @param string $changelog_html The changelog HTML
 * @return array The title and content for the changelog post
 */
function parse_changelog_html( $changelog_html ) {
	$known_sections = array(
		'Fixed',
		'Added',
		'Changed',
		'Removed',
	);

	// Check if we have multiple instances of known sections
	$has_duplicate_sections = false;
	foreach ( $known_sections as $section ) {
		$count = substr_count( $changelog_html, '<h3>' . $section . '</h3>' );
		if ( $count > 1 ) {
			$has_duplicate_sections = true;
			break;
		}
	}

	// Aggregate sections if we have duplicates
	if ( $has_duplicate_sections ) {
		$changelog_html = aggregate_changelog_headings( $changelog_html );
	}

	$title = PROJECT_REPONAME . ' ' . gmdate( 'o-m-d H:i' );

	return array(
		'title'   => $title,
		'content' => $changelog_html,
	);
}

/**
 * Builds the changelog request body.
 *
 * @param string $title The title of the changelog post
 * @param string $content The content of the changelog post
 * @param array $tags The tags of the changelog post
 * @param array $channels The channels of the changelog post
 * @param array $categories The categories of the changelog post
 * @return array The changelog request body
 */
function build_changelog_request_body( $title, $content, $tags, $channels, $categories ) {
	$fields = array(
		'title'      => $title,
		'content'    => $content,
		'excerpt'    => $title,
		'status'     => WP_CHANGELOG_STATUS,
		'tags'       => implode( ',', $tags ),
		'categories' => implode( ',', $categories ),
	);

	if ( $channels ) {
		$fields['release-channel'] = implode( ',', $channels );
	}

	return $fields;
}

/**
 * Makes the request to create a changelog post.
 *
 * @param string $title The title of the changelog post
 * @param string $content The content of the changelog post
 * @param array $tags The tags of the changelog post
 * @param array $channels The channels of the changelog post
 * @param array $categories The categories of the changelog post
 * @return void
 */
function create_changelog_post( $title, $content, $tags, $channels, $categories ) {
	$fields = build_changelog_request_body( $title, $content, $tags, $channels, $categories );

	$ch = curl_init( WP_CHANGELOG_ENDPOINT );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization:Bearer ' . CHANGELOG_POST_TOKEN ) );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
	$response  = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );

	echo "Response:\n";
	echo $response;
	echo "\nHttpCode: $http_code";

	if ( $http_code >= 400 ) {
		echo "\n\nFailed to create changelog draft post\n";
		exit( 1 );
	}
}

/**
 * Gets the changelog tags.
 *
 * @param array $github_labels The GitHub labels
 * @return array The changelog tags
 */
function get_changelog_tags( $github_labels ) {
	$tags = explode( ',', WP_CHANGELOG_TAG_IDS );

	if ( isset( $github_labels ) && count( $github_labels ) > 0 ) {
		foreach ( $github_labels as $label ) {
			preg_match( '/ChangelogTagID:\s*(\d+)/', $label['description'] ?? '', $matches );
			if ( $matches ) {
				array_push( $tags, $matches[1] );
			}
		}
	}

	return $tags;
}

/**
 * Gets the changelog categories.
 *
 * @return array The changelog categories
 */
function get_changelog_categories() {
	$categories = explode( ',', WP_CHANGELOG_CATEGORIES );

	$filtered = array_filter(
		$categories,
		function ( $category ) {
			return (bool) $category;
		}
	);

	// Pass through array_values() to reset the array indexes, which are left intact after array_filter()
	return array_values( $filtered );
}

/**
 * Gets the changelog channels.
 *
 * @return array The changelog channels
 */
function get_changelog_channels() {
	return array_filter(
		explode( ',', WP_CHANGELOG_CHANNEL_IDS ),
		function ( $channel ) {
			return (bool) $channel;
		}
	);
}

/**
 * Generates the changelog HTML from a list of PRs.
 *
 * @param array $prs The PRs to generate the changelog from
 * @return array The changelog HTML and tags
 */
function generate_changelog_from_prs( $prs ) {
	$all_changelog_html = '';
	$all_tags           = array();

	foreach ( $prs as $pr ) {
		$changelog_html = get_changelog_html( $pr, false );

		if ( ! empty( $changelog_html ) ) {
			$all_changelog_html .= "\n\n" . $changelog_html;
			$pr_tags             = get_changelog_tags( $pr['labels'] );
			$all_tags            = array_merge( $all_tags, $pr_tags );
		}
	}

	return array( $all_changelog_html, $all_tags );
}

/**
 * Creates a changelog post from the latest PR.
 *
 * @return void
 */
function create_changelog_for_last_pr() {
	$pr = fetch_last_pr();

	if ( ! isset( $pr['id'] ) ) {
		echo "Failed to retrieve last closed pull request.\n";
		exit( 1 );
	}

	if ( VERIFY_COMMIT_HASH && SHA1 !== $pr['merge_commit_sha'] ) {
		echo "Skipping post. Build not triggered from a merged pull request.\n";
		exit( 0 );
	}

	// The last merged PR and any PRs found in its commits.
	$prs = array_merge( array( $pr ), get_referenced_prs( $pr ) );

	if ( ! empty( $prs ) ) {
		list( $changelog_html, $changelog_tags ) = generate_changelog_from_prs( $prs );
	}

	$changelog_categories = get_changelog_categories();
	$changelog_channels   = get_changelog_channels();

	// Add a link to the PR if requested.
	if ( LINK_TO_PR ) {
		$changelog_html = $changelog_html . "\n\n" . $pr['html_url'];
	}

	if ( empty( $changelog_html ) ) {
		echo "Skipping post. No changelog text found.\n";
		exit( 0 );
	}

	$changelog_record = parse_changelog_html( $changelog_html );

	debug( $changelog_record );

	create_changelog_post( $changelog_record['title'], $changelog_record['content'], $changelog_tags, $changelog_channels, $changelog_categories );
	echo "\n\nAll done!";
}

/**
 * Fetches releases from GitHub.
 *
 * @param int $count The number of releases to fetch
 * @return array The fetched releases
 */
function fetch_releases( $count = 1 ) {
	$url = GITHUB_RELEASE_ENDPOINT . '?per_page=' . $count . '&sort=updated&direction=desc';

	$releases = make_github_request( $url );

	return $releases;
}

/**
 * Fetches PRs by their IDs.
 *
 * @param array $pr_ids The array of PR IDs to fetch
 * @return array The fetched PRs
 */
function fetch_prs_by_ids( $pr_ids ) {
	$prs         = array();
	$fetched_ids = array();

	foreach ( $pr_ids as $pr_id ) {
		// Skip if we've already fetched this PR
		if ( in_array( $pr_id, $fetched_ids ) ) {
			continue;
		}
		$fetched_ids[] = $pr_id;

		// Get the PR details
		$pr = fetch_pr( $pr_id );
		if ( ! $pr ) {
			continue;
		}

		$prs[] = $pr;
	}

	return $prs;
}

/**
 * Extracts PR IDs from a message like a commit message or release notes.
 *
 * @param string $message The message to extract PR IDs from
 * @return array The extracted PR IDs
 */
function get_pr_ids_from_message( $message ) {
	$pr_ids = array();

	// Match PR references in commit messages
	if ( 1 === preg_match( '/\(\#[0-9]+\)/', $message, $matches ) ||
		1 === preg_match( '/^Merge pull request #[0-9]+/', $message, $matches ) ) {
		$pr_ids[] = preg_replace( '/[^0-9]/', '', $matches[0] );
	}

	// Match PR references in release notes format
	// Example: "* chore: Update dependency by @user in https://github.com/Automattic/vip-cli/pull/1234"
	if ( preg_match_all( '/\/pull\/(\d+)/', $message, $matches ) ) {
		$pr_ids = array_merge( $pr_ids, $matches[1] );
	}

	return array_unique( $pr_ids );
}

/**
 * Aggregates changelog content by grouping items under their respective headings.
 *
 * @param string $html The HTML content to aggregate
 * @return string The aggregated HTML or original content if aggregation fails
 */
function aggregate_changelog_headings( string $html ): string {
	if ( empty( trim( $html ) ) ) {
		return '';
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );

	if ( ! $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html ) ) {
		return $html;
	}
	libxml_clear_errors();

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$root = $dom->getElementsByTagName( 'body' )->item( 0 ) ?? $dom->documentElement;
	if ( ! $root ) {
		return $html;
	}

	$aggregated      = array();
	$current_heading = null;

	foreach ( $root->childNodes as $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			continue;
		}

		if ( 'h3' === $node->nodeName ) {
			$current_heading                  = $node->textContent;
			$aggregated[ $current_heading ] ??= array();
			continue;
		}

		if ( ! $current_heading ) {
			continue;
		}

		// Handle ul and p elements
		if ( 'ul' === $node->nodeName ) {
			foreach ( $node->getElementsByTagName( 'li' ) as $li ) {
				$aggregated[ $current_heading ][] = trim( $dom->saveHTML( $li ) );
			}
		} elseif ( 'p' === $node->nodeName ) {
			$aggregated[ $current_heading ][] = trim( $dom->saveHTML( $node ) );
		}
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

	$output = '';
	foreach ( $aggregated as $heading => $items ) {
		// Skip if there are no nodes under this heading
		if ( empty( $items ) ) {
			continue;
		}
		$output .= '<h3>' . $heading . "</h3>\n";
		$output .= "<ul>\n";
		foreach ( $items as $item ) {
			$output .= '<li>' . trim( strip_tags( $item, '<code>' ) ) . "</li>\n";
		}
		$output .= "</ul>\n";
	}

	return trim( $output );
}

/**
 * Creates a changelog post from the latest release.
 *
 * @return void
 */
function create_changelog_for_last_release() {
	$releases = fetch_releases( 1 );

	if ( empty( $releases ) ) {
		echo "No releases found.\n";
		exit( 0 );
	}

	$release = $releases[0];

	if ( ! isset( $release['body'] ) ) {
		echo "No body found for the latest release.\n";
		exit( 0 );
	}

	$pr_ids = get_pr_ids_from_message( $release['body'] );

	if ( empty( $pr_ids ) ) {
		echo "No PRs found for the latest release.\n";
		exit( 0 );
	}

	$prs = fetch_prs_by_ids( $pr_ids );

	if ( empty( $prs ) ) {
		echo "No PRs found for the latest release.\n";
		exit( 0 );
	}

	list( $changelog_html, $changelog_tags ) = generate_changelog_from_prs( $prs );

	if ( empty( $changelog_html ) ) {
		echo "No changelog entries found in any PRs for this release.\n";
		exit( 0 );
	}

	if ( LINK_TO_PR ) {
		$changelog_html = $changelog_html . "\n\n" . $release['html_url'];
	}

	// Remove duplicate tags
	$changelog_tags = array_unique( $changelog_tags );

	$changelog_categories = get_changelog_categories();
	$changelog_channels   = get_changelog_channels();

	$changelog_record = parse_changelog_html( $changelog_html );

	debug( $changelog_record );

	create_changelog_post( $changelog_record['title'], $changelog_record['content'], $changelog_tags, $changelog_channels, $changelog_categories );

	echo "\n\nAll done!";
}
