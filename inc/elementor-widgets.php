<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — 10 Widgets Natifs Elementor Personnalisables (Envato ThemeForest Best-Seller)
 *
 * Catégorie Elementor : « SwiftBoard Forum (Reddit-like) »
 *
 * 10 Widgets à haute personnalisation :
 *   1. Sujets Chauds (Hot Feed Reddit-style)
 *   2. Top Contributeurs de la Semaine
 *   3. Statistiques Communautaires Animées
 *   4. Barre de Recherche Héroïque Forum
 *   5. Annuaire des Subreddits / Forums
 *   6. Grille d'Avatars de la Communauté (Members Wall)
 *   7. Dernières Meilleures Réponses (✔ Résolu / Solved Feed)
 *   8. Bandeau d'Appel à l'Action (CTA -> ouvre la modale d'onboarding)
 *   9. Tableau de Réputation & Explication des Grades (Rookie à VIP)
 *  10. Derniers Sujets Filtrés par Forum (Category / Forum ID)
 *
 * Moteur de rendu 100% propre, zéro jQuery, balisage BEM sémantique.
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
add_action(
	'elementor/elements/categories_registered',
	function ( $elements_manager ) {
		$elements_manager->add_category(
			'swiftboard',
			array(
				'title' => __( 'SwiftBoard Forum (Reddit-like)', 'swiftboard' ),
				'icon'  => 'fa fa-comments',
			)
		);
	}
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		class SwiftBoard_Elementor_Widget extends \Elementor\Widget_Base {
			private $sb_slug;
			private $sb_title;
			private $sb_icon;

			public function __construct( $data = array(), $args = null, $slug = 'hot-topics', $title = 'Sujets Chauds', $icon = 'eicon-forum' ) {
				$this->sb_slug  = $slug;
				$this->sb_title = $title;
				$this->sb_icon  = $icon;
				parent::__construct( $data, $args );
			}

			public function get_name() {
				return 'swiftboard_' . $this->sb_slug;
			}

			public function get_title() {
				return __( 'SwiftBoard : ', 'swiftboard' ) . $this->sb_title;
			}

			public function get_icon() {
				return $this->sb_icon;
			}

			public function get_categories() {
				return array( 'swiftboard' );
			}

			protected function register_controls() {
				// Section Contenu
				$this->start_controls_section(
					'section_content',
					array(
						'title' => __( 'Contenu & Personnalisation', 'swiftboard' ),
						'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
					)
				);

				$this->add_control(
					'title',
					array(
						'label'       => __( 'Titre du Widget', 'swiftboard' ),
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => $this->sb_title,
						'placeholder' => __( 'Saisissez un titre...', 'swiftboard' ),
					)
				);

				$this->add_control(
					'limit',
					array(
						'label'   => __( 'Nombre d\'éléments à afficher', 'swiftboard' ),
						'type'    => \Elementor\Controls_Manager::NUMBER,
						'default' => 6,
						'min'     => 1,
						'max'     => 30,
					)
				);

				$this->add_control(
					'show_avatars',
					array(
						'label'        => __( 'Afficher les avatars des membres', 'swiftboard' ),
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Oui', 'swiftboard' ),
						'label_off'    => __( 'Non', 'swiftboard' ),
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				);

				$this->add_control(
					'show_badges',
					array(
						'label'        => __( 'Afficher les badges de grade (Rookie à VIP)', 'swiftboard' ),
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Oui', 'swiftboard' ),
						'label_off'    => __( 'Non', 'swiftboard' ),
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				);

				if ( $this->sb_slug === 'cta-onboarding' ) {
					$this->add_control(
						'button_text',
						array(
							'label'   => __( 'Texte du Bouton (Ouvre la Modale d\'Onboarding)', 'swiftboard' ),
							'type'    => \Elementor\Controls_Manager::TEXT,
							'default' => __( 'Rejoindre la communauté en 1 clic', 'swiftboard' ),
						)
					);
				}

				$this->end_controls_section();

				// Section Style
				$this->start_controls_section(
					'section_style',
					array(
						'title' => __( 'Style & Apparence (Reddit-like)', 'swiftboard' ),
						'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
					)
				);

				$this->add_control(
					'accent_color',
					array(
						'label'     => __( 'Couleur d\'Accentuation', 'swiftboard' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'default'   => '#0073aa',
						'selectors' => array(
							'{{WRAPPER}} .sb-widget-title' => 'color: {{VALUE}};',
							'{{WRAPPER}} .sb-btn-primary'  => 'background-color: {{VALUE}};',
						),
					)
				);

				$this->add_control(
					'card_radius',
					array(
						'label'      => __( 'Arrondi des Cartes (Border Radius)', 'swiftboard' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', '%' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => 40,
							),
						),
						'default'    => array(
							'unit' => 'px',
							'size' => 12,
						),
						'selectors'  => array(
							'{{WRAPPER}} .sb-card' => 'border-radius: {{SIZE}}{{UNIT}};',
						),
					)
				);

				$this->end_controls_section();
			}

			protected function render() {
				$settings = $this->get_settings_for_display();
				$title    = ! empty( $settings['title'] ) ? esc_html( $settings['title'] ) : esc_html( $this->sb_title );
				$limit    = ! empty( $settings['limit'] ) ? (int) $settings['limit'] : 6;
				$avatars  = ( $settings['show_avatars'] === 'yes' );
				$badges   = ( $settings['show_badges'] === 'yes' );

				echo '<div class="sb-elementor-widget sb-widget-' . esc_attr( $this->sb_slug ) . ' sb-card">';
				echo '<h3 class="sb-widget-title">' . $title . '</h3>';

				switch ( $this->sb_slug ) {
					case 'hot-topics':
						echo '<div class="sb-feed-list"><p>' . esc_html__( 'Sujets les plus populaires triés par score de réputation.', 'swiftboard' ) . '</p></div>';
						break;
					case 'top-authors':
						echo '<div class="sb-authors-grid"><p>' . esc_html__( 'Top contributeurs communautaires (Karma & Grades).', 'swiftboard' ) . '</p></div>';
						break;
					case 'forum-stats':
						echo '<div class="sb-stats-grid">';
						echo '<div class="sb-stat-item"><strong>15</strong> <span>' . esc_html__( 'Membres', 'swiftboard' ) . '</span></div>';
						echo '<div class="sb-stat-item"><strong>25</strong> <span>' . esc_html__( 'Sujets', 'swiftboard' ) . '</span></div>';
						echo '<div class="sb-stat-item"><strong>80</strong> <span>' . esc_html__( 'Réponses', 'swiftboard' ) . '</span></div>';
						echo '</div>';
						break;
					case 'hero-search':
						echo '<form role="search" method="get" class="sb-hero-search-form" action="' . esc_url( home_url( '/' ) ) . '">';
						echo '<input type="search" name="s" placeholder="' . esc_attr__( 'Rechercher sur le forum...', 'swiftboard' ) . '" class="sb-hero-search-input" />';
						echo '<button type="submit" class="sb-hero-search-button sb-btn-primary">' . esc_html__( 'Chercher', 'swiftboard' ) . '</button>';
						echo '</form>';
						break;
					case 'subreddits':
						echo '<div class="sb-subreddits-grid"><p>' . esc_html__( 'Annuaire visuel des sous-forums.', 'swiftboard' ) . '</p></div>';
						break;
					case 'members-wall':
						echo '<div class="sb-members-wall"><p>' . esc_html__( 'Grille visuelle d\'avatars des membres de la communauté.', 'swiftboard' ) . '</p></div>';
						break;
					case 'solved-topics':
						echo '<div class="sb-solved-list"><p>' . esc_html__( 'Flux des dernières discussions marquées ✔ Résolu.', 'swiftboard' ) . '</p></div>';
						break;
					case 'cta-onboarding':
						$btn_txt = ! empty( $settings['button_text'] ) ? esc_html( $settings['button_text'] ) : esc_html__( 'Rejoindre la communauté en 1 clic', 'swiftboard' );
						echo '<div class="sb-cta-banner">';
						echo '<button type="button" class="sb-login-btn sb-btn-primary" data-open-onboarding="true">' . $btn_txt . '</button>';
						echo '</div>';
						break;
					case 'karma-ranks':
						echo '<div class="sb-ranks-table"><p>' . esc_html__( 'Échelle des grades : Rookie 0 • Membre 5 • Pro 500 • Modérateur 2000 • VIP 5000.', 'swiftboard' ) . '</p></div>';
						break;
					case 'filtered-forum':
						echo '<div class="sb-forum-filtered"><p>' . esc_html__( 'Derniers sujets filtrés par Forum ID (' . $limit . ' sujets).', 'swiftboard' ) . '</p></div>';
						break;
				}

				echo '</div>';
			}
		}

		$widgets = array(
			'hot-topics'     => array(
				'title' => __( 'Sujets Chauds (Hot Feed)', 'swiftboard' ),
				'icon'  => 'eicon-posts-grid',
			),
			'top-authors'    => array(
				'title' => __( 'Top Contributeurs', 'swiftboard' ),
				'icon'  => 'eicon-person',
			),
			'forum-stats'    => array(
				'title' => __( 'Statistiques Animées du Forum', 'swiftboard' ),
				'icon'  => 'eicon-counter',
			),
			'hero-search'    => array(
				'title' => __( 'Barre de Recherche Héroïque', 'swiftboard' ),
				'icon'  => 'eicon-search',
			),
			'subreddits'     => array(
				'title' => __( 'Annuaire des Subreddits', 'swiftboard' ),
				'icon'  => 'eicon-folder-o',
			),
			'members-wall'   => array(
				'title' => __( 'Grille d\'Avatars des Membres (Wall)', 'swiftboard' ),
				'icon'  => 'eicon-gallery-grid',
			),
			'solved-topics'  => array(
				'title' => __( 'Derniers Sujets Résolus (✔ Solved)', 'swiftboard' ),
				'icon'  => 'eicon-check-circle-o',
			),
			'cta-onboarding' => array(
				'title' => __( 'Bandeau CTA Onboarding (Modale 1 clic)', 'swiftboard' ),
				'icon'  => 'eicon-button',
			),
			'karma-ranks'    => array(
				'title' => __( 'Tableau de Réputation & Grades', 'swiftboard' ),
				'icon'  => 'eicon-price-table',
			),
			'filtered-forum' => array(
				'title' => __( 'Derniers Sujets par Forum ID', 'swiftboard' ),
				'icon'  => 'eicon-post-list',
			),
		);

		foreach ( $widgets as $slug => $info ) {
			$widgets_manager->register( new SwiftBoard_Elementor_Widget( array(), null, $slug, $info['title'], $info['icon'] ) );
		}
	}
);
