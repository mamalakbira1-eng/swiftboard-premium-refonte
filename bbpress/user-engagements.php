<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Participations (engagements) utilisateur
 *
 * Liste des sujets dans lesquels l'utilisateur a participé.
 *
 * @package SwiftBoard
 */
do_action('bbp_template_before_user_engagements'); ?>

<div id="bbp-user-engagements" class="bbp-user-engagements">

    <?php bbp_get_template_part('form', 'topic-search'); ?>

    <h2 class="entry-title"><?php esc_html_e('Sujets où l\'utilisateur a participé', 'swiftboard'); ?></h2>
    <div class="bbp-user-section">

        <?php if (bbp_get_user_engagements()) : ?>

            <?php bbp_get_template_part('pagination', 'topics'); ?>

            <?php bbp_get_template_part('loop', 'topics'); ?>

            <?php bbp_get_template_part('pagination', 'topics'); ?>

        <?php else : ?>

            <?php bbp_get_template_part('feedback', 'no-topics'); ?>

        <?php endif; ?>

    </div>
</div><!-- #bbp-user-engagements -->

<?php do_action('bbp_template_after_user_engagements');

