<?php

class MenuLocationTest extends \Codeception\TestCase\WPTestCase {

	public $group_key;
	public $post_id;
	public $test_image;
	public $tag_id;
	public $comment_id;
	public $menu_item_id;
	public $menu_id;

	public function setUp(): void {

		parent::setUp(); // TODO: Change the autogenerated stub
		$this->group_key = __CLASS__;
		WPGraphQL::clear_schema();
		$this->register_acf_field_group();

		$this->post_id = $this->factory()->post->create( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Test',
			'post_content' => 'test',
		] );

		$this->tag_id = $this->factory()->term->create( [
			'taxonomy' => 'post_tag',
		] );

		$this->comment_id = $this->factory()->comment->create([
			'comment_content' => 'test comment',
			'comment_author' => 'Test Author',
			'comment_approved' => true,
			'comment_post_ID' => $this->post_id,
		]);

		$location_name = 'test-location';
		add_theme_support( 'nav_menus' );
		register_nav_menu( $location_name, 'test menu...' );
		$menu_slug = 'my-test-menu';
		$this->menu_id = wp_create_nav_menu( $menu_slug );
		$post_id = $this->factory()->post->create();

		$this->menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			[
				'menu-item-title'     => 'Menu item',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			]
		);
		set_theme_mod( 'nav_menu_locations', [ $location_name => $this->menu_id ] );

		$this->test_image = dirname( __FILE__, 2 ) . '/_data/images/test.png';

	}

	public function tearDown(): void {
		acf_remove_local_field_group( $this->group_key );
		wp_delete_post( $this->post_id, true );
		WPGraphQL::clear_schema();
		parent::tearDown(); // TODO: Change the autogenerated stub
	}

	public function register_acf_field_group( $config = [] ) {

		$defaults = [
			'key'                   => $this->group_key,
			'title'                 => 'Menu Fields',
			'fields'                => [],
			'location'              => [
				[
					[
						'param'    => 'nav_menu',
						'operator' => '==',
						'value'    => 'all',
					],
				],
			],
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'menuFields',
			'graphql_types'         => [ 'Menu' ]
		];

		acf_add_local_field_group( array_merge( $defaults, $config ) );


	}

	public function register_acf_field( $config = [] ) {

		$defaults = [
			'parent'            => $this->group_key,
			'key'               => 'field_5d7812fd123',
			'label'             => 'Text',
			'name'              => 'text',
			'type'              => 'text',
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
			'show_in_graphql'   => 1,
			'default_value'     => '',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'maxlength'         => '',
		];

		acf_add_local_field( array_merge( $defaults, $config ) );
	}

	/**
	 * @throws Exception
	 */
	public function testBasicQuery() {
		$query  = '{ menuItems { nodes { id } } }';
		$actual = graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testAcfTextField() {

		$this->register_acf_field([
			'name' => 'menu_text_test',
			'type' => 'text',
		]);

		$expected_text = 'Some Text';

		update_field( 'menu_text_test', $expected_text, 'nav_menu_' . $this->menu_id );
		$field = get_field( 'menu_text_test', 'nav_menu_' . $this->menu_id );
		codecept_debug( [ 'field_value', $field, 'nav_menu_' . $this->menu_id ] );

		$query = '
		query getMenu( $id: ID! ) {
			menu( id: $id ) {
				__typename
				databaseId
				menuFields {
					fieldGroupName
					menuTextTest
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $this->menu_id ),
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->menu_id, $actual['data']['menu']['databaseId'] );
		$this->assertSame( $expected_text, $actual['data']['menu']['menuFields']['menuTextTest'] );

	}

}
