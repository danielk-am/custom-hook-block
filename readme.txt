=== Registered Render Blocks ===
Contributors: danielkam1
Tags: blocks, block editor, dynamic blocks, developers
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use trusted PHP renderers as editable blocks with typed settings, native styles and authorized server previews.

== Description ==

Registered Render Blocks connects developer-registered PHP renderers to the block editor. Editors choose a renderer, adjust its declared settings, and preview the same PHP output used on the site.

The plugin includes a simple Notice renderer with a heading and message. Developers can register their own renderers through a PHP API. Renderer code belongs in a plugin or theme; the block does not accept PHP or JavaScript source from post content.

* Typed settings: text, choices, booleans and numbers.
* Native block color, typography, spacing and border controls.
* Server previews through WordPress's authenticated block-renderer API.
* A temporary preview post selection that is never saved into content.
* Registered style and script handles for integration assets.
* A hidden compatibility block for explicitly mapped legacy hook names.

Legacy hook names do not dispatch arbitrary WordPress actions. Unmapped legacy blocks render no public content and show an explanatory editor message.

Previewing a selected post requires permission to edit it. Previewing without a post requires permission to edit theme options. PHP callbacks are trusted application code: integrations must escape their output, read only appropriate public data on the frontend, and perform no side effects during rendering.

== Installation ==

1. Upload the registered-render-blocks folder to wp-content/plugins, or install its ZIP from Plugins.
2. Activate Registered Render Blocks.
3. Insert Registered Renderer in the block editor and select Notice.
4. Developers can register additional renderers using the API documented in README.md.

== Frequently Asked Questions ==

= Can editors enter PHP or JavaScript? =
No. Only trusted PHP registered by a developer executes. Settings contain typed values, not executable source.

= Does this need Advanced Custom Fields or WooCommerce? =
No. Site integrations may read those plugins' data, but this plugin has no dependency on either.

= Can I preview a product in a template? =
Yes. Choose its content type and search for its title under Preview context. You must be allowed to edit that product. The selection is local to the current editor session and never changes frontend context.

= Does it replace an existing Custom Hook Block plugin? =
It registers a hidden compatibility alias only if that block name is free. Deactivate the older plugin after reviewing its usages. Legacy hooks must be explicitly mapped to a trusted renderer; the plugin does not execute arbitrary old actions.

== Screenshots ==

1. The Notice renderer in the block editor, with editable heading and message settings and the server-rendered preview.

== Changelog ==

= 0.1.0 =
* Initial package with typed renderer registration, core server previews and block styles.

== Build source ==

Readable JavaScript and SCSS source is included under src. package.json and package-lock.json document the build. Run npm ci followed by npm run build to reproduce build assets. No build tools are required to use the installed plugin.
