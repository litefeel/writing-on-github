# Writing On GitHub #
**Contributors:** litefeel  
**Tags:** github, git, version control, content, collaboration, publishing  
**Donate link:** https://www.paypal.me/litefeel  
**Requires at least:** 3.9  
**Tested up to:** 4.8  
**Stable tag:** 1.3  
**License:** GPLv2  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A WordPress plugin to allow you writing on GitHub (or Jekyll site).

## Description ##

*A WordPress plugin to allow you writing on GitHub (or Jekyll site).*

Some code for this plugin comes from [Wordpress GitHub Sync](https://github.com/mAAdhaTTah/wordpress-github-sync), thanks.

Ever wish you could collaboratively author content for your WordPress site (or expose change history publicly and accept pull requests from your readers)?

Well, now you can! Introducing [Writing On GitHub](https://github.com/litefeel/writing-on-wordpress)!

### Writing On GitHub does three things: ###

1.  Allows content publishers to version their content in GitHub
2.  Allows readers to submit proposed improvements to WordPress-served content via GitHub's Pull Request model

### Writing On GitHub might be able to do some other cool things: ###

* Allow teams to collaboratively write and edit posts using GitHub (e.g., pull requests, issues, comments)
* Allow you to sync the content of two different WordPress installations via GitHub
* Allow you to stage and preview content before "deploying" to your production server

### How it works ###

The sync action is based on two hooks:

1. A per-post sync fired in response to WordPress's `save_post` hook which pushes content to GitHub
2. A sync of all changed files triggered by GitHub's `push` webhook (outbound API call)

## Installation ##

### Using the WordPress Dashboard ###

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'Writing On GitHub'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

### Uploading in WordPress Dashboard ###

1. Download `writing-on-github.zip` from the WordPress plugins repository.
2. Navigate to the 'Add New' in the plugins dashboard
3. Navigate to the 'Upload' area
4. Select `writing-on-github.zip` from your computer
5. Click 'Install Now'
6. Activate the plugin in the Plugin dashboard

### Using FTP ###

1. Download `writing-on-github.zip`
2. Extract the `writing-on-github` directory to your computer
3. Upload the `writing-on-github` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard


### Configuring the plugin ###

1. [Create a personal oauth token](https://github.com/settings/tokens/new) with the `public_repo` scope. If you'd prefer not to use your account, you can create another GitHub account for this.
2. Configure your GitHub host, repository, secret (defined in the next step),  and OAuth Token on the Writing On GitHub settings page within WordPress's administrative interface. Make sure the repository has an initial commit or the export will fail.
3. Create a WebHook within your repository with the provided callback URL and callback secret, using `application/json` as the content type. To set up a webhook on GitHub, head over to the **Settings** page of your repository, and click on **Webhooks & services**. After that, click on **Add webhook**.
4. Click `Export to GitHub`

## Frequently Asked Questions ##

### Markdown Support ###

Writing On GitHub exports all posts as `.md` files for better display on GitHub, but all content is exported and imported as its original HTML. To enable writing, importing, and exporting in Markdown, please install and enable [WP-Markdown](https://wordpress.org/plugins/wp-markdown/), and Writing On GitHub will use it to convert your posts to and from Markdown.

You can also activate the Markdown module from [Jetpack](https://wordpress.org/plugins/jetpack/) or the standalone [JP Markdown](https://wordpress.org/plugins/jetpack-markdown/) to save in Markdown and export that version to GitHub.

### Importing from GitHub ###

Writing On GitHub is also capable of importing posts directly from GitHub, without creating them in WordPress before hand. In order to have your post imported into GitHub, add this YAML Frontmatter to the top of your .md document:

    ---
    post_title: 'Post Title'
    post_name: 'this is post name'
    layout: post_type_probably_post
    published: true_or_false
    author: author_name
    tags:
        - tag_a
        - tag_b
    categories:
        - category_a
        - category_b
    ---
    Post goes here.

and fill it out with the data related to the post you're writing. Save the post and commit it directly to the repository. After the post is added to WordPress, an additional commit will be added to the repository, updating the new post with the new information from the database.

Note that Writing On GitHub will import posts from the `master` branch by default. Once set, do not change it.

If Writing On GitHub cannot find the author for a given import, it will fallback to the default user as set on the settings page. **Make sure you set this user before you begin importing posts from GitHub.** Without it set, Writing On GitHub will default to no user being set for the author as well as unknown-author revisions.


### Contributing ###

Found a bug? Want to take a stab at [one of the open issues](https://github.com/litefeel/writing-on-github/issues)? We'd love your help!

See [the contributing documentation](CONTRIBUTING.md) for details.

### Prior Art ###

* [WordPress Post Forking](https://github.com/post-forking/post-forking)
* [WordPress to Jekyll exporter](https://github.com/benbalter/wordpress-to-jekyll-exporter)
* [Writing in public, syncing with GitHub](https://konklone.com/post/writing-in-public-syncing-with-github)
* [Wordpress GitHub Sync](https://github.com/mAAdhaTTah/wordpress-github-sync)
