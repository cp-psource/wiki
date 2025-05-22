<?php
// filepath: /home/dern3rd/Local Sites/ps-dev/app/public/wp-content/plugins/wiki/default-templates/wiki-comments.php
if ( post_password_required() ) {
    return;
}
?>

<div class="wiki-comments-wrapper">
    <h2 class="wiki-comments-title">
        Diskutiere über &bdquo;<?php echo esc_html(get_the_title()); ?>&ldquo;
    </h2>

    <div class="wiki-comments-count">
        <?php
        $count = get_comments_number();
        echo $count . ' ' . _n('Bemerkung', 'Bemerkungen', $count, 'textdomain');
        ?>
    </div>

    <div class="wiki-comment-form">
        <?php comment_form([
            'title_reply' => 'Neue Bemerkung verfassen',
            'label_submit' => 'Absenden',
            'comment_notes_before' => '',
            'comment_notes_after' => '',
        ]); ?>
    </div>

    <?php if ( have_comments() ) : ?>
        <ol class="wiki-comment-list">
            <?php
            wp_list_comments([
                'style'      => 'ol',
                'short_ping' => true,
                'avatar_size'=> 32,
                'reply_text' => 'Antworten',
            ]);
            ?>
        </ol>
    <?php else : ?>
        <p class="wiki-no-comments">Noch keine Bemerkungen vorhanden.</p>
    <?php endif; ?>
</div>
<style>
    .wiki-comments-wrapper {
    background: #f8f9fa;
    border: 1px solid #d6d8db;
    border-radius: 6px;
    padding: 24px 24px 12px 24px;
    margin: 32px 0;
    font-family: "Segoe UI", "Liberation Sans", Arial, sans-serif;
}

.wiki-comments-title {
    font-size: 1.4em;
    margin-bottom: 8px;
    color: #2d3a4a;
    font-weight: bold;
}

.wiki-comments-count {
    font-size: 1em;
    color: #4a5568;
    margin-bottom: 18px;
}

.wiki-comment-form {
    margin-bottom: 24px;
}

.wiki-comment-list {
    list-style: none;
    padding-left: 0;
    margin-bottom: 0;
}

.wiki-comment-list li {
    border-top: 1px solid #e2e8f0;
    padding: 12px 0;
}

.wiki-comment-list li:first-child {
    border-top: none;
}

.wiki-no-comments {
    color: #888;
    font-style: italic;
    margin-top: 12px;
}
</style>