=== Custom Hook Block ===
Contributors: danielkam1
Tags: blocks, block editor, dynamic blocks, developers
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display approved PHP hooks and renderers with editable settings, native block styles, and server previews.

== Description ==

Custom Hook Block lets developers place PHP output in the block editor. Editors choose an approved hook or renderer, change its declared settings, and preview the same PHP output used on the site.

Keep an existing add_action callback and explicitly register its display hook with chb_register_hook. For callbacks that return HTML, use chb_register_renderer. The included Notice renderer provides editable heading and message fields.

* Optional Interact mode for developer-enabled editor previews.
* Typed settings: text, choices, booleans, and numbers.
* Native block colour, typography, spacing, and border controls.
* Server previews through WordPress's authenticated block-renderer API.
* Searchable preview content that is never saved as a frontend post selection.
* Registered style and script handles for integration assets.
* Saved-block compatibility with Custom Hook Block 1.0 and Registered Render Blocks.

New blocks start with an empty choice. PHP code belongs in a plugin, theme, or trusted snippet. Editors cannot enter executable PHP or JavaScript, and unregistered action names never run.

Previewing a selected post requires permission to edit it. Previewing without a post requires permission to edit theme options. Developers must escape output, respect field visibility, and keep rendering read-only. Register dedicated display actions, never lifecycle or transactional hooks.



== Installation ==

1. Upload the reviewed custom-hook-block folder to wp-content/plugins, or install its ZIP through Plugins.
2. Deactivate Registered Render Blocks if active, then activate Custom Hook Block.
3. Insert Custom Hook Block and choose Notice to try the built-in renderer.
4. Register additional display hooks or renderers using the examples in README.md.

== Frequently Asked Questions ==

= Can I keep an existing add_action callback? =
Yes. Register its dedicated display hook with chb_register_hook. The block captures its echoed output. Existing callbacks may keep their original signature, or accept validated settings and context as two arguments.

= What changes for Custom Hook Block 1.0? =
The original mytheme/custom-hook-block identity remains. Version 2.0 requires explicit hook registration before output runs. Old blocks without hookName represent my_custom_hook, which must also be registered. Activation does not rewrite posts.

= What happens to Registered Render Blocks content? =
Saved registered-render-blocks/renderer blocks remain supported as a hidden compatibility block. The rrb_register_renderer API and rrb_register_renderers action remain available. Deactivate the separate Registered Render Blocks plugin before using this provider.

= What if both plugins are active? =
Custom Hook Block pauses its provider and shows an administrator notice, preventing duplicate-function errors. Its chb_ APIs become available after Registered Render Blocks is deactivated and WordPress loads again.

= Can editors enter PHP or JavaScript? =
No. Only developer-registered PHP executes. Settings contain typed values, not executable source.

= Does this need Advanced Custom Fields or WooCommerce? =
No. Site integrations may read those plugins' data, but this plugin has no dependency on either.

= Can I preview a product in a template? =
Yes. Choose its content type and search for its title under Preview context. You must be allowed to edit that product. The selection is temporary and never changes frontend context.

= Can I use buttons and Ajax inside the editor? =
Yes, for renderers whose developer enables interactive previews and supplies an editor initializer. Select the block and turn on Interact. Escape returns to editing. Existing renderers remain non-interactive until configured. Interaction mode is temporary and is not saved into content.

== Screenshots ==

1. A registered PHP action with editable heading and message settings and its PHP preview.
2. Interact enabled in Custom Hook Block 2.1.0, with an Ajax response inside the editor.

== Changelog ==

= 2.1.0 =
Released 2026-10-07.
* Adds opt-in interactive editor previews and a temporary Interact control.
* Mounts approved editor scripts per preview, with abort signals and cleanup on refresh or removal.
* Adds an interactive Ajax snippet example and updated listing artwork and screenshots.


= 2.0.0 =
Released 2026-10-07.
* Retains the original Custom Hook Block identity and requires explicit registration of display hooks.
* Adds editable settings, native styles, and shared server previews.
* Preserves Registered Render Blocks saved content and registration APIs.
* Stops recursive hook rendering and cleans output buffers after callback errors.
* Pauses safely when the separate Registered Render Blocks plugin is active.

== Upgrade Notice ==

= 2.0.0 =
Register each existing display hook before upgrading. Unregistered hooks produce no frontend output. Deactivate Registered Render Blocks if it is installed.

== Build source ==

Readable JavaScript and SCSS source is included under src. package.json and package-lock.json document the build. Run npm ci followed by npm run build to reproduce assets. Build tools are not needed to use the installed plugin. README.md includes disposable-site test commands and migration examples.
