# Retake example screenshots

Use a disposable WordPress site with Code Snippets and this plugin active. Never copy production data or a Pro licence to it.

1. Save docs/examples/echo-hook.php and ajax-clock.php as active PHP snippets running everywhere using Code Snippets' own API or UI.
2. Publish a page with two mytheme/custom-hook-block blocks: hookName=my_custom_hook, and renderer=examples/ajax-clock. Keep style attributes absent. Use synthetic heading/message content.
3. Open the real WordPress editor in a browser that supports its blob iframe. Select the hook block, edit its heading, save, reload and confirm the PHP preview and settings. Capture the whole editor as .wordpress-org/screenshot-1.jpg and docs/examples/echo-hook-editor.jpg.
4. Open the active echo snippet in Code Snippets, with add_action and chb_register_hook visible. Capture docs/examples/echo-hook-source.jpg.
5. Open the saved page and click Refresh server time. Confirm the timestamp changes and success message appears. Capture docs/examples/hook-ajax-result.jpg.

The 2.0.0 captures were made with Codex computer-use screenshots on WordPress7.1.2/PHP8.4, Code SnippetsPro3.10.2, on 7October2026. The Pro-specific JavaScript loader was not used. The editor's thin selection outline is WordPress UI; neither block has an imposed background or padding.
