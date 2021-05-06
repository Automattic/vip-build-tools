<?php

require __DIR__ . '/../vendor/erusev/parsedown/Parsedown.php';

function is_env_set() {
    return isset(
        $_SERVER[ 'CIRCLE_PROJECT_USERNAME' ],
        $_SERVER[ 'CIRCLE_PROJECT_REPONAME' ],
        $_SERVER[ 'WP_CHANGELOG_AUTH_TOKEN'],
    );
}

if ( ! is_env_set() ) {
    echo "The following environment variables need to be set:
    \tCIRCLE_PROJECT_USERNAME
    \tCIRCLE_PROJECT_REPONAME
    \tWP_CHANGELOG_AUTH_TOKEN\n";
    exit( 1 );   
}

$options = getopt( null, [
    "start-marker:",
    "end-marker:",
    "wp-endpoint:",
    "wp-tag-ids:",
] );

if ( ! isset( $options[ "wp-endpoint" ] ) ) {
    echo "Argument --wp-endpoint is mandatory.\n";
    exit( 1 );   
}

define( 'PR_USERNAME', $_SERVER[ 'CIRCLE_PROJECT_USERNAME' ] );
define( 'PR_REPONAME', $_SERVER[ 'CIRCLE_PROJECT_REPONAME' ] );
define( 'WP_CHANGELOG_AUTH_TOKEN', $_SERVER[ 'WP_CHANGELOG_AUTH_TOKEN' ] );

define( 'GITHUB_ENDPOINT', 'https://api.github.com/repos/' . PR_USERNAME . '/' . PR_REPONAME . '/pulls?per_page=10&sort=updated&direction=desc&state=closed');
define( 'PR_CHANGELOG_START_MARKER', $options[ 'start-marker' ] ?? '<h2>Changelog Description' );
define( 'PR_CHANGELOG_END_MARKER', $options[ 'end-marker' ] ?? '<h2>' );
define( 'WP_CHANGELOG_ENDPOINT', $options[ 'wp-endpoint' ] );
define( 'WP_CHANGELOG_TAG_IDS', $options[ 'wp-tag-ids' ] );

function fetch_last_PR() {
    $ch = curl_init( GITHUB_ENDPOINT );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'User-Agent: script' ] );
    $data = curl_exec($ch);
    curl_close($ch);

    $last_prs = json_decode($data, true);
    $merged_prs = array_filter( $last_prs, function ( $pr ) { return $pr['merged_at'] ?? ''; } );

    return $merged_prs[0];
}

function get_changelog_section_in_description_html( $description ) {
    $found_changelog_header = false;
    $result = '';
    foreach(preg_split("/\n/", $description) as $line){
        if ( strpos($line, PR_CHANGELOG_START_MARKER) === 0 ) {
            $found_changelog_header = true;
        } else if ( $found_changelog_header ) {

            if ( strpos($line, PR_CHANGELOG_END_MARKER) === 0 ) {
                // We have hit next section
                break;
            }
            $result = $result . "\n" . $line;
        }
    }
    return $result;
}

function get_changelog_html( $pr ) {
    $Parsedown = new Parsedown();
    $body = preg_replace( '/<!--(.|\s)*?-->/', '', $pr['body'] );
    $description_html =  $Parsedown->text( $body );

    $changelog_html = get_changelog_section_in_description_html( $description_html );

    if ( strpos($changelog_html, $pr['html_url']) === false ) {
        $changelog_html = $changelog_html . "\n\n" . $Parsedown->text( $pr['html_url'] );
    }
    return $changelog_html;
}

function parse_changelog_html( $changelog_html ) {
    preg_match('/<h3>(.*)<\/h3>/', $changelog_html, $matches);

    // Remove the header from html. WP will add the title there.
    $content_changelog_html = str_replace( $matches[0], '', $changelog_html );

    return [
        'title' => $matches[1],
        'content' => $content_changelog_html,
    ];
}

function create_draft_changelog( $title, $content, $tags ) {
    $fields = [
        'title' => $title,
        'content' => $content,
        'excerpt' => $title,
        'status' => 'draft',
        'tags' => implode( ',', $tags ),
    ];
    $ch = curl_init( WP_CHANGELOG_ENDPOINT );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization:Bearer ' . WP_CHANGELOG_AUTH_TOKEN ] );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    echo "Response:\n";
    echo $response;
    echo "\nHttpCode: $http_code";

    if ( $http_code >= 400 ) {
        echo "\n\nFailed to create changelog draft post\n";
        exit( 1 );
    }
}

function get_changelog_tags( $github_labels ) {
    $tags = explode( ",", WP_CHANGELOG_TAG_IDS );

    foreach ( $github_labels as $label ) {
        preg_match('/ChangelogTagID:\s*(\d+)/', $label['description'], $matches);
        if ( $matches ) {
            array_push( $tags, $matches[1] );
        }
    }

    return $tags;
}

function create_changelog_for_last_PR() {
    $pr = fetch_last_PR();

    $changelog_tags = get_changelog_tags( $pr['labels'] );
    $changelog_html = get_changelog_html( $pr );
    $changelog_record = parse_changelog_html( $changelog_html );

    create_draft_changelog( $changelog_record['title'], $changelog_record['content'], $changelog_tags );
    echo "\n\nAll done!";
}

create_changelog_for_last_PR();