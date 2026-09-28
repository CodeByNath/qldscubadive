<?php
/**
 * Every non-platform request lands here. The public website is served by a
 * separate front end, so WordPress renders only a minimal placeholder
 * document. The Admin Station lives at /station/ (QSD Platform plugin).
 */
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="content">
  <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
</main>
<?php wp_footer(); ?>
</body>
</html>
