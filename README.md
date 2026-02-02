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
| wp-terms            | Taxonomies and terms to add to the post. E.g. `custom_taxonomy_slug:1,2`                                     | Optional            |                             |
| changelog-title     | Custom title format. Supports placeholders: `{date}`, `{datetime}`, `{pr}`, `{version}`, `{repo}`            | Optional            | `{repo} {datetime}`         |

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

```
GITHUB_TOKEN="" CHANGELOG_POST_TOKEN="" CIRCLE_PROJECT_USERNAME="" CIRCLE_PROJECT_REPONAME="" php scripts/github-changelog.php \
    --wp-endpoint=https://example.com/wp-json/wp/v2/posts \
    --wp-status=draft \
    --wp-categories=3 \
    --link-to-pr=true \
    --changelog-source=last-release \
    --wp-terms=custom_taxonomy_slug:1 \
    --wp-terms=tags:4 \
    --changelog-title="VIP Dashboard {date}"
```

### Custom Title Examples

The `--changelog-title` option supports the following placeholders:

- `{date}` - Current date in YYYY-MM-DD format
- `{datetime}` - Current date and time in YYYY-MM-DD HH:MM format
- `{pr}` - Pull request number (when using default or last-pr source)
- `{version}` - Release version tag (when using last-release source)
- `{repo}` - Repository name

**Examples:**
```bash
# For date-based titles
--changelog-title="VIP Dashboard {date}"
# Result: "VIP Dashboard 2025-01-19"

# For PR-based titles
--changelog-title="VIP MU plugins PR {pr}"
# Result: "VIP MU plugins PR 1234"

# For version-based titles (use with --changelog-source=last-release)
--changelog-title="VIP-CLI v{version}"
# Result: "VIP-CLI v2.3.0"
```
