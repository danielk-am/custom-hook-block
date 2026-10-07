# PHP hooks, renderers, and Ajax examples

Use these optional PHP snippets to add approved output to **Custom Hook Block 2.1.0**. They use demonstration content and add no background box or padding. Code Snippets is optional and is not bundled.

The current interactive example was tested on WordPress 7.1.2 and PHP 8.4 with Code Snippets Pro 3.10.2. Hook screenshots from 2.0.0 and older renderer screenshots remain labelled with their versions.

## 1. A WordPress action that echoes content

1. Create a PHP snippet from [echo-hook.php](echo-hook.php), omitting the opening `<?php` in Code Snippets.
2. Select **Run everywhere** and activate it with Custom Hook Block active.
3. Insert **Custom Hook Block** and choose **Greeting from a registered hook**.
4. Edit **Heading** and **Message** in the block sidebar.

![PHP snippet registering my_custom_hook with chb_register_hook](echo-hook-source.jpg)

![Custom Hook Block 2.0.0 with a registered PHP action and its edited heading after save and reload](echo-hook-editor.jpg)

This current capture shows the registered hook in 2.0.0. The heading was edited, saved, and reloaded.

![Custom Hook Block 2.0.0 frontend showing the registered hook and Ajax example](hook-ajax-result.jpg)

The frontend capture shows the action output alongside the Ajax demo. A real button click changed the clock from `02:39:50 UTC` to `02:39:55 UTC` and showed the success status. Both block wrappers had transparent backgrounds and `0px` padding.

The example keeps `add_action('my_custom_hook', ...)` and explicitly approves that action through `chb_register_hook`. Its callback echoes escaped HTML. The block captures the output for the editor and frontend.

Old Custom Hook Block 1.0 content without a saved hook name used `my_custom_hook`. This example demonstrates its explicit registration. Only use it on a test site unless you have reviewed every callback already attached to that action.

## 2. A PHP callback that returns content

1. Create a PHP snippet from [php-card.php](php-card.php), omitting the opening `<?php` in Code Snippets.
2. Select **Run everywhere** and activate it with Custom Hook Block active.
3. Insert **Custom Hook Block** and choose **PHP greeting from Code Snippets**.

Running everywhere allows registration on the frontend and editor preview requests. The snippet registers `examples/php-card` on `chb_register_renderers`; its callback returns escaped HTML. The heading and message become settings in the block sidebar. The examples use the snippet output with the theme’s normal styles: no background box or padding is added. Native block Styles controls remain optional. The thin editor selection outline is WordPress UI, not frontend markup.

![Active PHP registration snippet in Code Snippets](php-snippet-source.jpg)

![The PHP greeting selected and previewed in the block editor](php-block-editor.jpg)

This uses the new renderer registration hook. It does not execute arbitrary hook names entered by editors.

## 3. JavaScript and Ajax, managed by a PHP snippet

1. Create a PHP snippet from [ajax-clock.php](ajax-clock.php), omitting the opening `<?php` in Code Snippets.
2. Select **Run everywhere** and activate it.
3. Insert **Custom Hook Block** and choose **Ajax server clock from Code Snippets**.

This single snippet registers:

1. A PHP callback returning the clock card.
2. Separate trusted frontend and editor JavaScript handles using WordPress's script API.
3. A public, read-only Ajax endpoint that returns only the server UTC time.
4. An editor initializer with a separate root and cleanup for each preview.

The renderer's `view_script_handles` loads the JavaScript when this block renders on the frontend. Click **Refresh server time** on the saved page: JavaScript requests a new timestamp from PHP and updates this card without reloading the page. Loading, timeout and error states are included.

The following two captures show the earlier frontend-only 0.1.0 example. Use the current source above for editor interaction.

![Historical Ajax endpoint and script registration in Code Snippets](ajax-snippet-source.jpg)

![Actual frontend result after a successful Ajax request](ajax-result.jpg)

[ajax-clock.js](ajax-clock.js) is a readable companion copy of the script embedded in the PHP snippet. **Do not activate both copies**; that would duplicate event listeners.

## Interactive editor preview

The Ajax example now sets `interactive: true` and declares `editor_script_handles` as well as its frontend handle. In the editor, select the block and turn on **Interact** in its toolbar. Click **Refresh server time** to make a real Ajax request to PHP. Press Escape or choose **Return to editing** in the sidebar to stop interaction.

![Custom Hook Block 2.1.0 Ajax button working inside the editor](interactive-editor.jpg)

The initializer registers through `window.chbEditor.registerPreview('examples/ajax-clock', mountClock)`. It receives this preview's DOM root and an abort signal. It scopes clicks to that root, cancels pending requests and removes listeners when the preview is replaced or Interact stops. Multiple instances have independent state. Interact is temporary and is not saved as a block attribute.

Frontend scripts are not automatically executed inside the editor. The example registers the same lifecycle-aware JavaScript under two handles, one for each environment. Existing frontend-only scripts need an editor initializer before they can be used this way.

The initial editor click changed the timestamp from `03:01:00 UTC` to `03:01:05 UTC` and displayed the Ajax success status. Escape returned to editing and made the preview controls inactive again.

## What about a separate Code Snippets Pro JavaScript snippet?

The screenshots and tests above demonstrate **PHP-managed JavaScript through Code Snippets Pro's PHP snippet type**. They do not demonstrate the separate Pro JavaScript snippet loader.

Source inspection of Code Snippets Pro 3.10.2 shows a native frontend-footer JavaScript snippet path, subject to its licensing and location/condition settings. With that feature enabled, the JavaScript can live separately while PHP keeps the renderer and endpoint. Remove only the frontend PHP script registration, its inline JavaScript and the `view_script_handles` entry before activating that separate copy. Keep the editor handle and initializer to retain interactive editor previews. Account for its loading conditions; a component selector prevents activity on pages without the block, but does not itself prevent a global script download. This separate-loader arrangement has not been run in this disposable site's unlicensed copy, and no licence check was bypassed.

## Historical verification and scope

These observations apply to the earlier 0.1.0 screenshots, before the source examples switched to the canonical `chb_` APIs:

- Both PHP snippets saved active through Code Snippets' own API, with no reported code error.
- Both renderers appeared in the block editor and produced their PHP previews.
- Real frontend click changed the server time from `02:11:41 UTC` to `02:11:50 UTC`; the status confirmed the Ajax result without a page reload.
- Anonymous Ajax GET returned HTTP 200 and only the public timestamp. POST returned HTTP 405.
- A page without this block did not load the example JavaScript asset.
- Independent source review passed: contextual escaping, read-only endpoint, scoped DOM updates, text-only insertion, duplicate-click protection and timeout/error recovery.

This clock intentionally uses no nonce because it provides public read-only data and performs no mutation. Do not copy that assumption into account data, product management or transactional endpoints: add the appropriate authentication, object/capability checks and CSRF protection. A nonce alone is not authorization.

These examples are opt-in documentation and excluded from the installable plugin ZIP. The plugin does not activate snippets or create endpoints by itself.

The old `rrb_register_renderer` function and `rrb_register_renderers` action still work in 2.1.0. Register each integration on one action only. Deactivate the separate Registered Render Blocks plugin before testing these canonical `chb_` examples.
