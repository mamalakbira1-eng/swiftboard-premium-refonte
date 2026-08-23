<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Contenu d'un sujet (topic + replies)
 *
 * Rendu Reddit-style : header du topic + liste des réponses
 * avec avatars, rôles, dates ISO 8601, colonne de vote.
 *
 * @package SwiftBoard
 */
?>

<?php
$topic_id    = bbp_get_topic_id();
$topic_title = bbp_get_topic_title($topic_id);
$author_id   = bbp_get_topic_author_id($topic_id);
$author_name = bbp_get_topic_author_display_name($topic_id);
$reply_count = bbp_get_topic_reply_count($topic_id);
$post_date   = get_the_date('c', $topic_id);
$last_active = bbp_get_topic_last_active_time($topic_id);
$vote_count  = swiftboard_get_vote_count($topic_id);
$content     = bbp_get_topic_content($topic_id);

// Rôle de l'auteur
$author_role = '';
if (user_can((int) $author_id, 'administrator')) {
    $author_role = '<span class="bbp-author-role admin">' . esc_html__('Admin', 'swiftboard') . '</span>';
} elseif (user_can((int) $author_id, 'bbp_moderator') || user_can((int) $author_id, 'edit_posts')) {
    $author_role = '<span class="bbp-author-role moderator">' . esc_html__('Modo', 'swiftboard') . '</span>';
}
?>

<article id="topic-<?php echo esc_attr((string) $topic_id); ?>" class="forum-content" itemscope itemtype="https://schema.org/DiscussionForumPosting">

    <header class="bbp-topic-header">
        <h1 class="bbp-topic-title" itemprop="headline"><?php echo esc_html($topic_title); ?></h1>
        <div class="bbp-topic-meta">
            <span>
                <?php echo swiftboard_get_avatar($author_id, 24); ?>
                <?php esc_html_e('Démarré par', 'swiftboard'); ?>
                <strong itemprop="author" itemscope itemtype="https://schema.org/Person">
                    <span itemprop="name"><?php echo esc_html($author_name); ?></span>
                </strong>
                <?php echo $author_role; // phpcs:ignore ?>
            </span>
            <span class="topic-meta-sep">•</span>
            <time datetime="<?php echo esc_attr($post_date); ?>" itemprop="datePublished">
                <?php echo esc_html(swiftboard_time_ago($post_date)); ?>
            </time>
            <span class="topic-meta-sep">•</span>
            <span><?php echo swiftboard_icon('discuss',14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php echo esc_html(swiftboard_format_count($reply_count)); ?> <?php esc_html_e('réponses', 'swiftboard'); ?></span>
        </div>
    <?php if (function_exists("swiftboard_display_grade_badge")) swiftboard_display_grade_badge((int) bbp_get_topic_author_id()); ?>
                </header>

    <!-- Topic original (premier message) — SANS doublon (auteur/date/grade déjà dans le header) -->
    <div class="bbp-reply">
        <div class="bbp-reply-content" itemprop="text">
            <?php echo $content; // phpcs:ignore ?>
        </div>
        <?php
        // v5.3.1 — barre d'actions du sujet IDENTIQUE a celle des commentaires
        // (pilule de vote horizontale + icones SVG seules, une seule ligne).
        $sb_my_vote_topic = (function_exists('swiftboard_get_my_vote') && is_user_logged_in())
            ? swiftboard_get_my_vote($topic_id)
            : null;
        $sb_cnt_cls = 'sb-comment-vote-count';
        if ($sb_my_vote_topic === 'up')   { $sb_cnt_cls .= ' up'; }
        if ($sb_my_vote_topic === 'down') { $sb_cnt_cls .= ' down'; }
        ?>
        <div class="bbp-reply-actions sb-topic-actions">
            <?php if (swiftboard_get_option('show_vote_count', 1)) : ?>
                <span class="sb-comment-votes" data-post-id="<?php echo esc_attr((string) $topic_id); ?>">
                    <button class="sb-comment-vote-btn up<?php echo $sb_my_vote_topic === 'up' ? ' active' : ''; ?>" data-post-id="<?php echo esc_attr((string) $topic_id); ?>" data-vote="up"
                        aria-label="<?php esc_attr_e('Upvoter', 'swiftboard'); ?>"
                        aria-pressed="<?php echo esc_attr(swiftboard_aria_pressed($topic_id, 'up')); ?>">
                        <svg class="sb-icon" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
                    </button>
                    <span class="<?php echo esc_attr($sb_cnt_cls); ?>" aria-live="polite" aria-atomic="true"><?php echo esc_html(swiftboard_format_count($vote_count)); ?></span>
                    <button class="sb-comment-vote-btn down<?php echo $sb_my_vote_topic === 'down' ? ' active' : ''; ?>" data-post-id="<?php echo esc_attr((string) $topic_id); ?>" data-vote="down"
                        aria-label="<?php esc_attr_e('Downvoter', 'swiftboard'); ?>"
                        aria-pressed="<?php echo esc_attr(swiftboard_aria_pressed($topic_id, 'down')); ?>">
                        <svg class="sb-icon" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
                    </button>
                </span>
            <?php endif; ?>
            <?php if (is_user_logged_in()) : ?>
                <a class="sb-comment-action" href="#bbp-reply-form" aria-label="<?php esc_attr_e('Répondre', 'swiftboard'); ?>" title="<?php esc_attr_e('Répondre', 'swiftboard'); ?>">
                    <svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                </a>
            <?php endif; ?>
            <button type="button" class="sb-comment-action sb-action-share" data-url="<?php echo esc_attr(bbp_get_topic_permalink($topic_id)); ?>"
                aria-label="<?php esc_attr_e('Partager', 'swiftboard'); ?>" title="<?php esc_attr_e('Partager', 'swiftboard'); ?>">
                <svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>
            <?php // Sauvegarder + Suivre directement dans la barre (pas de menu déroulant) ?>
            <?php if ( is_user_logged_in() ) : ?>
                <button type="button" class="sb-comment-action sb-action-save" data-post-id="<?php echo esc_attr((string) $topic_id); ?>" aria-pressed="false"
                    aria-label="<?php esc_attr_e('Sauvegarder', 'swiftboard'); ?>" title="<?php esc_attr_e('Sauvegarder', 'swiftboard'); ?>">
                    <svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                </button>
                <button type="button" class="sb-comment-action sb-action-follow" data-post-id="<?php echo esc_attr((string) $topic_id); ?>" aria-pressed="false"
                    aria-label="<?php esc_attr_e('Suivre', 'swiftboard'); ?>" title="<?php esc_attr_e('Suivre', 'swiftboard'); ?>">
                    <svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // V2 restauration - Meilleure réponse épinglée
    if (function_exists('swiftboard_render_pinned_best_answer')) {
        swiftboard_render_pinned_best_answer($topic_id);
    }
    ?>

    <!-- Réponses -->
    <?php
    // V2 restauration - Famille A1: barre tri commentaires + compose rapide
    // Ce hook était branché dans nested-comments.php mais jamais déclenché car ce gabarit rend les réponses inline
    do_action('bbp_template_before_replies_loop');
    ?>
    <?php if (bbp_has_replies()) : ?>
        <?php while (bbp_replies()) : bbp_the_reply(); ?>
            <?php bbp_get_template_part('loop', 'single-reply'); ?>
        <?php endwhile; ?>

        <?php
        // PATCH v2.1 : bbp_get_reply_pagination_links() n'existe pas en 2.6.14.
        // On utilise bbp_get_topic_pagination_links() à la place.
        $pagination_links = function_exists('bbp_get_topic_pagination_links')
            ? bbp_get_topic_pagination_links()
            : (function_exists('bbp_get_replies_pagination_links') ? bbp_get_replies_pagination_links() : '');
        ?>
        <?php if ($pagination_links) : ?>
            <nav class="bbp-pagination">
                <span class="bbp-pagination-count"><?php bbp_topic_pagination_count(); ?></span>
                <div class="bbp-pagination-links"><?php echo $pagination_links; // phpcs:ignore ?></div>
            </nav>
        <?php endif; ?>

    <?php else : ?>
        <div class="bbp-no-reply">
            <p><?php esc_html_e('Aucune réponse pour le moment. Soyez le premier à répondre !', 'swiftboard'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Formulaire de réponse -->
    <?php if (is_user_logged_in() && bbp_current_user_can_access_create_reply_form()) : ?>
        <?php bbp_get_template_part('form', 'reply'); ?>
    <?php elseif (!is_user_logged_in()) : ?>
        <div class="bbp-template-notice info" style="margin-top: var(--space-md);">
            <?php printf(
                esc_html__('Vous devez %s pour répondre à ce sujet.', 'swiftboard'),
                '<a href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('vous connecter', 'swiftboard') . '</a>'
            ); ?>
        </div>
    <?php endif; ?>

</article>

