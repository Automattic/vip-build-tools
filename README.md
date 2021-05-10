# VIP Build Tools

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

| Option       | Description                                                                                    | Required / Optional | Default Value               |
| -------------|:----------------------------------------------------------------------------------------------:|---------------------| --------------------------- |
| wp-endpoint  | The WordPress posts endpoint the changelog will be posted at.                                  | Required            |                             |
| start-marker | The text marker used to find the start of the changelog description inside the PR description. | Optional            | `<h2>Changelog Description` |
| end-marker   | The text marker used to find the end of the changelog description inside the PR description.   | Optional            | `<h2>`                      |
| wp-status    | The WordPress post status.                                                                     | Optional            | `draft`                     |
| wp-tag-ids   | A comma separated list of WordPress tag ids to add to the post.                                | Optional            |                             |
| link-to-pr   | Wether or not to include the link to the PR in the post.                                       | Optional            | `true`                      |

### Environment Variables

Most of these variables are already [built-in](https://circleci.com/docs/2.0/env-vars/#built-in-environment-variables) by CircleCI.

| Option                  | Description                                                          | Required / Optional |
| ----------------------- |:--------------------------------------------------------------------:|---------------------| 
| CIRCLE_PROJECT_USERNAME | The GitHub username of the current project.                          | Required            |
| CIRCLE_PROJECT_REPONAME | The name of the repository of the current project.                   | Required            |
| CHANGELOG_POST_TOKEN    | WordPress auth token required to post to the endpoint.               | Required            |
| GITHUB_TOKEN            | The GitHub personal acess token needed to read private repositories. | Optional            |

### Usage Example

```bash
php scripts/github-changelog.php \
    --wp-endpoint=https://public-api.wordpress.com/wp/v2/sites/wpvipchangelog.wordpress.com/posts \
    --wp-tag-ids=1784989
```

In the example above, the following is to be expected:
1. A post will be created on the `wpvipchangelog.wordpress.com` site.
2. The post will be tagged with the tag of id `1784989`.
3. The contents of the post should be all text in the PR description that is between the `<h2>Changelog Description` and `<h2>` markers.

An example [CircleCI Workflow](https://circleci.com/docs/2.0/workflows/) is available [here](/examples/changelog-circleci-config.yml).