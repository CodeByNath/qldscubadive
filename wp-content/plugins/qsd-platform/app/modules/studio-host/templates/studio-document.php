<?php
/**
 * The Studio host document. Served for the /studio/ route by
 * StudioHostModule::templateInclude() — entirely separate from
 * /station/'s own document, templates, and bundle.
 */
if (!defined('QSD_PLUGIN_PATH')) { return; }

use QSD\Platform\Modules\StudioHost\StudioHostModule;

$body = StudioHostModule::renderBody();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Studio · <?php echo esc_html(get_bloginfo('name')); ?></title>
  <?php wp_head(); ?>
</head>
<body class="qsd-studio-document">
<?php echo $body; // Each gate template escapes its own output. ?>
<?php wp_footer(); ?>
</body>
</html>
