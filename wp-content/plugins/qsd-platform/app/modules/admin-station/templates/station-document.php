<?php
/**
 * The Admin Station document. Served for the /station/ route by
 * AdminStationModule::templateInclude(), independent of the active theme.
 */
if (!defined('QSD_PLUGIN_PATH')) { return; }

use QSD\Platform\Modules\AdminStation\AdminStationModule;

$body = AdminStationModule::renderBody();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Station · <?php echo esc_html(get_bloginfo('name')); ?></title>
  <?php wp_head(); ?>
</head>
<body class="qsd-station-document">
<?php echo $body; // Each gate template escapes its own output. ?>
<?php wp_footer(); ?>
</body>
</html>
