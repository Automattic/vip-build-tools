# VIP Build Scripts

A collection of helpful scripts to be used in CI jobs.

## Prerequisites

Make sure you have [composer](https://getcomposer.org/) installed.

## Install

To get setup run the following command in the `vip-build-scripts` directory:

```bash
composer install 
```

## Script: Changelog

Extracts changelog information from the last closed Pull Request description and sends a request to a WordPress posts endpoint.

### Options

| Option        | Description                                              | Required / Optional |
| ------------- |:--------------------------------------------------------:|---------------------| 
| wp-endpoint   | The endpoint url where the changelog is gonna be posted. | Required            |
| wp-tag-ids    | Tag ids to add to the post                               | Optional            |
| gh-endpoint   | GitHub endpoint used to retrieve pull requests from.     | Required            |

### Environment Variables

| Option               | Description                                            | Required / Optional |
| -------------------- |:------------------------------------------------------:|---------------------| 
| CHANGELOG_POST_TOKEN | WordPress auth token required to post to the endpoint. | Required            |

### Usage Example

```bash
php scripts/github-changelog.php \
    --wp-endpoint=https://public-api.wordpress.com/wp/v2/sites/wpvipchangelog.wordpress.com/posts \
    --gh-endpoint=https://api.github.com/repos/Automattic/vip-go-mu-plugins/pulls?per_page=10&sort=updated&direction=desc&state=closed \
    --wp-tag-ids=1784989
```
