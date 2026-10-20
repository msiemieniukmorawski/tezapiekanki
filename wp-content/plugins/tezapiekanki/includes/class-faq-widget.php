<?php
/**
 * Widget Elementora "FAQ (zwiń / rozwiń)", kategoria "TeZapiekanki". Renderuje Faq::render() (class-faq.php).
 *
 * @package tezapiekanki
 */

namespace TZP;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

class Faq_Widget extends Widget_Base {

	public function get_name() {
		return 'tzp_faq';
	}

	public function get_title() {
		return 'FAQ (zwiń / rozwiń)';
	}

	public function get_icon() {
		return 'eicon-accordion';
	}

	public function get_categories() {
		return array( 'tezapiekanki' );
	}

	public function get_keywords() {
		return array( 'faq', 'pytania', 'odpowiedzi', 'akordeon', 'accordion', 'schema' );
	}

	public function get_style_depends(): array {
		return array( Faq::STYLE );
	}

	/**
	 * Zawsze renderowany na żywo (bez cache elementów Elementora): pytania muszą trafić do schemy FAQPage,
	 * którą wypisujemy na końcu strony.
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_items', array( 'label' => 'Pytania' ) );

		$repeater = new Repeater();

		$repeater->add_control(
			'question',
			array(
				'label'       => 'Pytanie',
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'default'     => 'Pytanie',
			)
		);

		$repeater->add_control(
			'answer',
			array(
				'label'   => 'Odpowiedź',
				'type'    => Controls_Manager::WYSIWYG,
				'default' => '<p>Odpowiedź.</p>',
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => 'Pytania i odpowiedzi',
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ question }}}',
				'default'     => array(
					array(
						'question' => 'Pierwsze pytanie?',
						'answer'   => '<p>Odpowiedź na pierwsze pytanie.</p>',
					),
					array(
						'question' => 'Drugie pytanie?',
						'answer'   => '<p>Odpowiedź na drugie pytanie.</p>',
					),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_settings', array( 'label' => 'Ustawienia' ) );

		$this->add_control(
			'open_first',
			array(
				'label'   => 'Pierwsze pytanie otwarte',
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'exclusive',
			array(
				'label'       => 'Tylko jedno otwarte naraz',
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => 'Otwarcie pytania zamyka poprzednie. Na telefonie przesuwa to treść, dlatego domyślnie wyłączone.',
			)
		);

		$this->add_control(
			'schema',
			array(
				'label'       => 'Dane strukturalne FAQPage',
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => 'Pytania trafiają do schemy FAQPage dla Google (jedna na stronę, wspólna dla wszystkich bloków FAQ). Te same pytania oznaczaj tylko w jednym miejscu serwisu, np. na stronie FAQ, a w innych miejscach wyłącz.',
			)
		);

		$this->add_control(
			'more_text',
			array(
				'label'       => 'Link pod pytaniami: tekst',
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'Zobacz wszystkie pytania',
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'more_url',
			array(
				'label'       => 'Link pod pytaniami: adres',
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => '/faq/',
				'description' => 'Puste pole przy wpisanym tekście = strona z wszystkimi pytaniami.',
			)
		);

		$this->add_control(
			'more_align',
			array(
				'label'   => 'Link pod pytaniami: wyrównanie',
				'type'    => Controls_Manager::SELECT,
				'default' => 'left',
				'options' => array(
					'left'   => 'Do lewej',
					'center' => 'Do środka',
					'right'  => 'Do prawej',
				),
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'   => 'Znacznik pytania',
				'type'    => Controls_Manager::SELECT,
				'default' => 'span',
				'options' => array(
					'span' => 'Zwykły tekst',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
				),
			)
		);

		$this->end_controls_section();

		$this->register_style_controls();
	}

	private function register_style_controls() {
		$this->start_controls_section(
			'section_style_item',
			array(
				'label' => 'Bloki',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => 'Odstęp między pytaniami',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'item_bg',
			array(
				'label'     => 'Tło',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'item_border',
			array(
				'label'     => 'Kolor obramowania',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-border: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'item_radius',
			array(
				'label'      => 'Zaokrąglenie',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => 'Odstęp wewnętrzny',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-pt: {{TOP}}{{UNIT}}; --tzp-faq-pr: {{RIGHT}}{{UNIT}}; --tzp-faq-pb: {{BOTTOM}}{{UNIT}}; --tzp-faq-pl: {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_question',
			array(
				'label' => 'Pytanie',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'question_typography',
				'selector' => '{{WRAPPER}} .tzp-faq .tzp-faq__title',
			)
		);

		$this->add_control(
			'question_color',
			array(
				'label'     => 'Kolor',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-q-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'question_color_open',
			array(
				'label'     => 'Kolor otwartego pytania',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-q-open: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => 'Kolor strzałki',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-icon: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_answer',
			array(
				'label' => 'Odpowiedź',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'answer_typography',
				'selector' => '{{WRAPPER}} .tzp-faq .tzp-faq__answer',
			)
		);

		$this->add_control(
			'answer_color',
			array(
				'label'     => 'Kolor',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .tzp-faq' => '--tzp-faq-a-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items    = array();

		foreach ( (array) $settings['items'] as $item ) {
			$items[] = array(
				'question' => $item['question'] ?? '',
				'answer'   => $this->parse_text_editor( $item['answer'] ?? '' ),
			);
		}

		echo Faq::render( // phpcs:ignore WordPress.Security.EscapeOutput -- Faq::render escapuje pytania, odpowiedzi przez wp_kses_post.
			$items,
			array(
				'open_first' => 'yes' === $settings['open_first'],
				'exclusive'  => 'yes' === $settings['exclusive'],
				'schema'     => 'yes' === $settings['schema'] && ! \Elementor\Plugin::$instance->editor->is_edit_mode(),
				'tag'        => $settings['title_tag'],
				'id'         => $this->get_id(),
				'more_text'  => $settings['more_text'] ?? '',
				'more_url'   => '' !== trim( (string) ( $settings['more_url'] ?? '' ) ) ? $settings['more_url'] : Faq::page_url(),
				'more_align' => $settings['more_align'] ?? 'left',
			)
		);
	}
}
