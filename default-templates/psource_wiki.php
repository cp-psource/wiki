<?php
global $blog_id, $wp_query, $wiki, $post, $current_user;
get_header( 'wiki' );
?>

<div id="primary" class="wiki-primary-event">
    <div id="content">
        <div class="padder">
            <div id="wiki-page-wrapper">
                <h1 class="entry-title"><?php the_title(); ?></h1>

                <?php if ( !post_password_required() ) { 

                // Variablen VOR der Tabs-Ausgabe setzen!
                $revision_id = isset($_REQUEST['revision']) ? absint($_REQUEST['revision']) : 0;
                $left        = isset($_REQUEST['left']) ? absint($_REQUEST['left']) : 0;
                $right       = isset($_REQUEST['right']) ? absint($_REQUEST['right']) : 0;
                $action      = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';
                ?>

                <!-- Tabs-Leiste nur wenn NICHT im "Wiki-Seite"-Tab -->
                <?php if ($action !== 'view') : ?>
                    <div class="psource_wiki psource_wiki_single">
                        <div class="psource_wiki_tabs psource_wiki_tabs_top">
                            <?php echo $wiki->tabs(); ?>
                            <div class="psource_wiki_clear"></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                if ($action == 'discussion') {
                    include dirname(__FILE__) . '/wiki_comment.php'; // <-- Dein eigenes Template!
                } elseif ($action !== 'view') {
                    echo $wiki->decider(apply_filters('the_content', $post->post_content), $action, $revision_id, $left, $right, false);
                } else {
                    echo apply_filters('the_content', $post->post_content);
                }
                ?>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php get_sidebar('wiki'); ?>
<?php get_footer('wiki'); ?>

<style type="text/css">
.single #primary {
	float: left;
	margin: 0 -26.4% 0 0;
}

.singular #content, .left-sidebar.singular #content {
	margin: 0 34% 0 7.6%;
    width: 58.4%;
}
</style>