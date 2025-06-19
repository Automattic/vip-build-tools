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

| Option              | Description                                                                                                  | Required / Optional | Default Value               |
|---------------------|:------------------------------------------------------------------------------------------------------------:|---------------------|-----------------------------|
| wp-endpoint         | The WordPress posts endpoint the changelog will be posted at.                                                | Required            |                             |
| start-marker        | The text marker used to find the start of the changelog description inside the PR description.               | Optional            | `<h2>Changelog Description` |
| end-marker          | The text marker used to find the end of the changelog description inside the PR description.                 | Optional            | `<h2>`                      |
| wp-status           | The WordPress post status.                                                                                   | Optional            | `draft`                     |
| wp-tag-ids          | A comma separated list of WordPress tag ids to add to the post.                                              | Optional            |                             |
| link-to-pr          | Whether or not to include the link to the PR in the post.                                                    | Optional            | `true`                      |
| changelog-source    | Source to create the changelog for. Use `last-release` to process release notes, otherwise processes last PR | Optional            |                             |

### Environment Variables

Most of these variables are already [built-in](https://circleci.com/docs/2.0/env-vars/#built-in-environment-variables) by CircleCI.

| Option                  | Description                                                          | Required / Optional |
| ----------------------- |:--------------------------------------------------------------------:|---------------------|
| CIRCLE_PROJECT_USERNAME | The GitHub username of the current project.                          | Required            |
| CIRCLE_PROJECT_REPONAME | The name of the repository of the current project.                   | Required            |
| CHANGELOG_POST_TOKEN    | WordPress.com auth token required to post to the endpoint.           | Required            |
| GITHUB_TOKEN            | The GitHub personal acess token needed to read private repositories. | Optional            |


- `CHANGELOG_POST_TOKEN` can be generated using a helper app like https://github.com/Automattic/node-wpcom-oauth ([example instructions](https://wp.me/p6jPRI-4xy#comment-26288))

### Usage Example

An example [CircleCI Workflow](https://circleci.com/docs/2.0/workflows/) is available [here](/examples/changelog-circleci-config.yml).

The example does NOT have a valid WP TOKEN so no entry will be published.

To run the example you can use circleci-cli: `circleci local execute --job create-changelog-draft --config examples/changelog-circleci-config.yml`
