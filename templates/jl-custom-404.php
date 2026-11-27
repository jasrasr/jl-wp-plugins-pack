<?php
if (!defined('ABSPATH')) {
    exit;
}
$jl_custom_404_page = get_post(absint(get_query_var('jl_custom_404_page_id')));
get_header();
?>
<main id="primary" class="site-main jl-custom-404">
    <?php if ($jl_custom_404_page) : ?>
        <article id="post-<?php echo esc_attr((string) $jl_custom_404_page->ID); ?>" class="page">
            <header class="entry-header"><h1 class="entry-title"><?php echo esc_html(get_the_title($jl_custom_404_page)); ?></h1></header>
            <div class="entry-content"><?php echo apply_filters('the_content', $jl_custom_404_page->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </article>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
