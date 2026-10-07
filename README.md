<p align="center"><img src="https://raw.githubusercontent.com/danielk-am/registered-render-blocks/main/.wordpress-org/icon-128x128.png" width="64" height="64" alt="Registered Render Blocks icon"></p>

![Registered Render Blocks — PHP output. Native block controls.](https://raw.githubusercontent.com/danielk-am/registered-render-blocks/main/.wordpress-org/banner-1544x500.png)

# Registered Render Blocks

Turn trusted PHP output into editable WordPress blocks. Choose a renderer, adjust its settings, and style the result using native block controls.

[Download the installable ZIP](https://github.com/danielk-am/registered-render-blocks/releases/latest) · [Report an issue](https://github.com/danielk-am/registered-render-blocks/issues)

## What it does

- Includes a working Notice renderer with editable heading and message.
- Generates settings controls from typed PHP definitions.
- Shows the same PHP-rendered content in the editor and on the site.
- Supports native colors, typography, spacing and borders.
- Lets you search for a record to preview without saving that preview choice into the page.
- Loads registered CSS and JavaScript assets for developer integrations.

The renderer adds no default background box or padding. Its callback supplies the HTML; the theme and optional block style choices determine its appearance.

PHP code lives in your plugin or theme. Editors change typed settings rather than executable source. ACF and WooCommerce are optional integrations.

## Editor screenshot

The Notice renderer running in WordPress, with editable content and its server preview. The banner above is an illustration; this is a real editor capture.

![Notice renderer with heading and message settings and live PHP preview](https://raw.githubusercontent.com/danielk-am/registered-render-blocks/main/.wordpress-org/screenshot-1.jpg)

## Code Snippets and Ajax examples

[See the working PHP and Ajax examples](docs/examples/README.md), including source in Code Snippets, block-editor previews and the frontend result.

## Install

Download the ZIP from [Releases](https://github.com/danielk-am/registered-render-blocks/releases), then use **Plugins → Add Plugin → Upload Plugin** in WordPress. Activate it and insert **Registered Renderer**, choosing **Notice** to start.

The release ZIP is the installable package. GitHub's automatically generated source archives also include development and artwork files.

## Build and check

```sh
npm ci
npm run build
npm run lint:js
npm run lint:css
```

Source is in `src/renderer/`; generated assets are in `build/renderer/`. The normal `@wordpress/scripts` build is used without custom webpack configuration. Build tools are development dependencies, not executed by WordPress.

Run `tests/run.php` with `studio wp eval-file` on a disposable WordPress site after activating this plugin. It creates and removes fixture posts/users. Never run it on production. Runtime checks cover registration, malicious IDs, strict types and bounds, default settings, HTML escaping, legacy alias safety, frontend context isolation and core SSR authorization.

## Register a renderer

Register on `rrb_register_renderers` (runs on `init` priority9). All registration arguments come from trusted PHP, never REST or stored block content.

```php
add_action( 'rrb_register_renderers', function () {
    wp_register_style( 'my-product-facts', plugins_url( 'facts.css', __FILE__ ), array(), '1.0' );
    $registered = rrb_register_renderer( 'my/product-facts', array(
        'title'       => __( 'Product facts', 'my-plugin' ),
        'description' => __( 'Verified details for the current product.', 'my-plugin' ),
        'settings'    => array(
            'heading' => array( 'type' => 'string', 'title' => __( 'Heading', 'my-plugin' ), 'default' => 'Product facts', 'maxLength' => 120 ),
            'layout'  => array( 'type' => 'string', 'title' => __( 'Layout', 'my-plugin' ), 'enum' => array( 'table', 'list' ), 'default' => 'table' ),
            'showHeading' => array( 'type' => 'boolean', 'title' => __( 'Show heading', 'my-plugin' ), 'default' => true ),
        ),
        'callback' => function ( $settings, $context ) {
            $post_id = $context['post_id'];
            if ( ! $post_id ) {
                return $context['preview'] ? '<p>' . esc_html__( 'Choose a preview product.', 'my-plugin' ) . '</p>' : '';
            }
            return '<h3>' . esc_html( $settings['heading'] ) . '</h3>';
        },
        'style_handles' => array( 'my-product-facts' ),
        'view_script_handles' => array(),
        'editor_script_handles' => array(),
        'legacy_hooks' => array( 'my_product_facts_hook' ),
    ) );
    // $registered is true or WP_Error. Treat a WP_Error as a registration defect.
} );
```

The ID is a lowercase slug, optionally `namespace/slug`, maximum100 characters. It must be unique. `title` and callable `callback` are required. `settings` is a property map, not a full object-schema wrapper. Each property requires `type` and `default`; permitted schema keys are `type`, `title`, `description`, `default`, `enum`, `minimum`, `maximum`, `maxLength`. Supported types are `string`, `boolean`, `number`, `integer`. Defaults are applied before the callback. Unknown settings and invalid types fail closed; no string-to-number/boolean coercion. String settings are bounded at10000 characters, default2000.

Callback signature: `callback(array $settings, array $context): string`. Context contains `post_id` (integer) and `preview` (boolean). Post context comes from the block's `postId` first, then the singular queried post. A frontend render never reads a preview-post attribute. In core SSR, the context must match the authorized request post ID. Preview selection is React component state, never a serialized block attribute.

Frontend rendering also refuses password-protected contextual posts and nonpublic posts the current visitor cannot read. Integrations remain responsible for field-level visibility and avoiding private metadata on otherwise public posts. Callbacks must be read-only and return escaped markup. The framework trusts registered PHP callback output because integrations may need semantic HTML and scripts registered separately. Escape dynamic values for the exact output context (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`). Do not execute settings as code. Do not print, update data, send messages, invoke transactions or use arbitrary action dispatch during rendering. Return an empty string when frontend content is unavailable. On a record-less preview, return an explicit sample/selection message rather than reading unrelated global data.

`style_handles` load on rendered frontend blocks and in the editor canvas. `view_script_handles` load only on a nonempty frontend render. `editor_script_handles` opt in trusted editor-only scripts. Register handles before the corresponding enqueue hook; source URLs and executable strings are not accepted as renderer settings. Scope CSS to `.rrb-renderer--my-product-facts` or renderer-owned classes. Core block support styles are applied by the block wrapper in both editor and frontend.

## Preview security

The plugin uses the built-in `/wp/v2/block-renderer/registered-render-blocks/renderer` route (and compatibility block route), authenticated by WordPress. Core validates registered block attributes. An additional permission guard requires `edit_post` for a selected record, or `edit_theme_options` for no-post template preview. Core retains its own permission check as well. Anonymous callers and users without access to another author's private post cannot invoke a preview. No custom REST route or nonce scheme is introduced.

The `previewPostId` attribute is intentionally absent. Sending it to REST fails schema validation; embedding it in frontend content cannot change renderer context. Preview state is cleared after the core REST callback.

## Compatibility

Main block: `registered-render-blocks/renderer`. Hidden legacy block: `mytheme/custom-hook-block`, registered only when no other plugin owns it. Old hook names work only when a trusted renderer explicitly lists them in `legacy_hooks`. The old plugin should be deactivated once its uses are reviewed; activation alone cannot safely override another plugin's block implementation. Unknown legacy hooks show an editor notice and no frontend output.

## Release status and validation

Version 0.1.0 is the initial GitHub release. It has not been submitted to or approved by WordPress.org.

Tested on WordPress 7.1.2 and PHP 8.4: 37 runtime checks passed, and Plugin Check 2.1.0 completed 29 static checks with no errors or warnings. Five Plugin Check runtime asset checks were unavailable in the Studio test environment; direct served-page checks confirmed conditional frontend styles and no bundled frontend JavaScript. Minimum declared WordPress/PHP versions have not been exercised as a separate matrix.

## Artwork and licence

Directory-ready banner, icon and screenshot files are in `.wordpress-org/`; editable SVG artwork is in `design/`. The banner is illustrative, and the screenshot uses synthetic demonstration content. Code and original artwork are GPL-2.0-or-later. Author: Daniel Kam.
