<?php
/**
 * Plugin Name: WPC Variations Radio Buttons for WooCommerce
 * Plugin URI: https://wpclever.net/
 * Description: WPC Variations Radio Buttons will replace dropdown select with radio buttons for the buyer easier in selecting the variations.
 * Version: 3.8.1
 * Author: WPClever
 * Author URI: https://wpclever.net
 * Text Domain: wpc-variations-radio-buttons
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * Requires at least: 4.0
 * Tested up to: 7.0
 * WC requires at least: 3.0
 * WC tested up to: 10.8
 * License: GPLv2 or later
 * * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

! defined( 'WOOVR_VERSION' ) && define( 'WOOVR_VERSION', '3.8.1' );
! defined( 'WOOVR_LITE' ) && define( 'WOOVR_LITE', __FILE__ );
! defined( 'WOOVR_FILE' ) && define( 'WOOVR_FILE', __FILE__ );
! defined( 'WOOVR_URI' ) && define( 'WOOVR_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOVR_DIR' ) && define( 'WOOVR_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOVR_SUPPORT' ) && define( 'WOOVR_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woovr&utm_campaign=wporg' );
! defined( 'WOOVR_REVIEWS' ) && define( 'WOOVR_REVIEWS', 'https://wordpress.org/support/plugin/wpc-variations-radio-buttons/reviews/' );
! defined( 'WOOVR_CHANGELOG' ) && define( 'WOOVR_CHANGELOG', 'https://wordpress.org/plugins/wpc-variations-radio-buttons/#developers' );
! defined( 'WOOVR_DISCUSSION' ) && define( 'WOOVR_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-variations-radio-buttons' );

// WPC Core
require_once __DIR__ . '/includes/wpc-core/wpc-core.php';
wpc_core_register( [
	'file'    => __FILE__,
	'version' => WOOVR_VERSION,
	'prefix'  => 'woovr',
] );

include 'includes/class-backend.php';

if ( ! class_exists( 'WPClever_Woovr' ) ) {
	class WPClever_Woovr {
		protected static $instance = null;
		protected static $settings = [];
		protected static $image_size = 'woocommerce_thumbnail';

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			self::$settings = (array) get_option( 'woovr_settings', [] );

			// init
			add_action( 'init', [ $this, 'init' ] );

			// enqueue frontend scripts
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );

			// functions
			add_filter( 'woocommerce_post_class', [ $this, 'post_class' ], 99, 2 );
			add_action( 'woocommerce_before_variations_form', [ $this, 'before_variations_form' ] );

			// custom variation name & image
			add_filter( 'woocommerce_product_variation_get_name', [ $this, 'variation_get_name' ], 99, 2 );

			// WPC Smart Messages
			add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );
		}

		function init() {
			// load text-domain
			load_plugin_textdomain( 'wpc-variations-radio-buttons', false, basename( WOOVR_DIR ) . '/languages/' );

			// image size
			self::$image_size = apply_filters( 'woovr_image_size', self::$image_size );
		}

		public static function get_settings() {
			return apply_filters( 'woovr_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'woovr_' . $name, $default );
			}

			return apply_filters( 'woovr_get_setting', $setting, $name, $default );
		}

		function enqueue_scripts() {
			// ddslick
			wp_enqueue_script( 'ddslick', WOOVR_URI . 'assets/libs/ddslick/jquery.ddslick.min.js', [ 'jquery' ], WOOVR_VERSION, true );

			// select2
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', [ 'jquery' ], WOOVR_VERSION, true );

			// woovr
			wp_enqueue_style( 'woovr-frontend', WOOVR_URI . 'assets/css/frontend.css', [], WOOVR_VERSION );
			wp_enqueue_script( 'woovr-frontend', WOOVR_URI . 'assets/js/frontend.js', [ 'jquery' ], WOOVR_VERSION, true );
		}

		function before_variations_form() {
			global $product;

			if ( $product && ( $product_id = $product->get_id() ) ) {
				$active  = self::get_setting( 'active', 'yes' );
				$_active = get_post_meta( $product_id, '_woovr_active', true ) ?: 'default';

				if ( $_active === 'yes' || ( $_active === 'default' && $active === 'yes' ) ) {
					self::variations_form( $product );
				}
			}
		}

		function variation_get_name( $name, $product ) {
			if ( apply_filters( 'woovr_variation_get_name', true ) && ( $custom_name = get_post_meta( $product->get_id(), 'woovr_name', true ) ) && ! empty( $custom_name ) ) {
				return $custom_name;
			}

			return $name;
		}

		function post_class( $classes, $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$product_id        = $product->get_id();
				$active            = self::get_setting( 'active', 'yes' );
				$show_price        = self::get_setting( 'show_price', 'yes' );
				$show_availability = self::get_setting( 'show_availability', 'yes' );
				$show_description  = self::get_setting( 'show_description', 'yes' );
				$_active           = get_post_meta( $product_id, '_woovr_active', true ) ?: 'default';

				if ( $_active === 'yes' ) {
					// overwrite settings
					$show_price        = get_post_meta( $product_id, '_woovr_show_price', true ) ?: $show_price;
					$show_availability = get_post_meta( $product_id, '_woovr_show_availability', true ) ?: $show_availability;
					$show_description  = get_post_meta( $product_id, '_woovr_show_description', true ) ?: $show_description;
				}

				if ( ( $_active === 'yes' ) || ( ( $_active === 'default' ) && ( $active === 'yes' ) ) ) {
					$classes[] = 'woovr-active';

					if ( $show_price === 'yes' ) {
						$classes[] = 'woovr-show-price';
					}

					if ( $show_availability === 'yes' ) {
						$classes[] = 'woovr-show-availability';
					}

					if ( $show_description === 'yes' ) {
						$classes[] = 'woovr-show-description';
					}
				}
			}

			return $classes;
		}

		static function data_attributes( $attrs ) {
			$attrs_arr = [];

			foreach ( $attrs as $key => $attr ) {
				$attrs_arr[] = 'data-' . sanitize_title( $key ) . '="' . esc_attr( $attr ) . '"';
			}

			return implode( ' ', $attrs_arr );
		}

		static function is_purchasable( $product ) {
			return $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( 1 );
		}

		public static function variations_form( $product, $variation = false, $context = 'default' ) {
			self::woovr_variations_form( $product, $variation, $context );
		}

		public static function enable_cache( $context = 'default' ) {
			return apply_filters( 'woovr_enable_cache', false, $context );
		}

		public static function delete_cache( $product_id ) {
			delete_transient( 'woovr_variations_form_' . $product_id );
			do_action( 'woovr_delete_cache', $product_id );
		}

		public static function woovr_variations_form( $product, $variation = false, $context = 'default', $allowed_terms = [] ) {
			$product_id = $product->get_id();
			$cache_id   = 'woovr_variations_form_' . $product_id;

            // Get the product categories
            $product_categories = get_the_terms($product_id, 'product_cat');
            // If product categories has 'dodatki' category
            $has_dodatki_category = false;
            if ($product_categories && ! is_wp_error($product_categories)) {
                foreach ($product_categories as $category) {
                    if ($category->slug === 'dodatki' or $category->slug === 'koszulki' or $category->slug === 'bluzy' or $category->slug === 'akcesoria') {
                        $has_dodatki_category = true;
                        return; // Exit the function if 'dodatki' category is found
                    }
                }
            }

			if ( ! self::enable_cache( $context ) || ( false === ( $variations_form = get_transient( $cache_id ) ) ) ) {
				ob_start();

				$unique_id          = uniqid( 'woovr_' . $product_id . '_' ); // compatible with WPC Product Bundles
				$active             = apply_filters( 'woovr_active', get_post_meta( $product_id, '_woovr_active', true ) ?: 'default', $product, $variation, $context );
				$show_clear         = apply_filters( 'woovr_show_clear', self::get_setting( 'show_clear', 'yes' ), $product, $variation, $context );
				$hide_unpurchasable = apply_filters( 'woovr_hide_unpurchasable', self::get_setting( 'hide_unpurchasable', 'no' ), $product, $variation, $context );

				// settings
				$selector          = apply_filters( 'woovr_default_selector', self::get_setting( 'selector', 'default' ), $product, $variation, $context );
				$orderby           = apply_filters( 'woovr_default_orderby', self::get_setting( 'orderby', 'default' ), $product, $variation, $context );
				$order             = apply_filters( 'woovr_default_order', self::get_setting( 'order', 'default' ), $product, $variation, $context );
				$show_name         = apply_filters( 'woovr_default_variation_name', self::get_setting( 'variation_name', 'formatted' ), $product, $variation, $context );
				$product_name      = apply_filters( 'woovr_default_product_name', self::get_setting( 'product_name', 'yes' ), $product, $variation, $context );
				$show_image        = apply_filters( 'woovr_default_show_image', self::get_setting( 'show_image', 'yes' ), $product, $variation, $context );
				$show_price        = apply_filters( 'woovr_default_show_price', self::get_setting( 'show_price', 'yes' ), $product, $variation, $context );
				$show_availability = apply_filters( 'woovr_default_show_availability', self::get_setting( 'show_availability', 'yes' ), $product, $variation, $context );
				$show_description  = apply_filters( 'woovr_default_show_description', self::get_setting( 'show_description', 'yes' ), $product, $variation, $context );
				$clear_label       = apply_filters( 'woovr_default_clear_label', self::get_setting( 'clear_label', esc_html__( 'Choose an option', 'wpc-variations-radio-buttons' ) ), $product, $variation, $context );
				$clear_image       = apply_filters( 'woovr_default_clear_image', self::get_setting( 'clear_image', 'placeholder' ), $product, $variation, $context );
				$clear_image_id    = apply_filters( 'woovr_default_clear_image_id', self::get_setting( 'clear_image_id', 0 ), $product, $variation, $context );

				if ( $active === 'yes' ) {
					// overwrite settings
					$selector          = get_post_meta( $product_id, '_woovr_selector', true ) ?: $selector;
					$orderby           = get_post_meta( $product_id, '_woovr_orderby', true ) ?: $orderby;
					$order             = get_post_meta( $product_id, '_woovr_order', true ) ?: $order;
					$show_name         = get_post_meta( $product_id, '_woovr_variation_name', true ) ?: $show_name;
					$show_image        = get_post_meta( $product_id, '_woovr_show_image', true ) ?: $show_image;
					$show_price        = get_post_meta( $product_id, '_woovr_show_price', true ) ?: $show_price;
					$show_availability = get_post_meta( $product_id, '_woovr_show_availability', true ) ?: $show_availability;
					$show_description  = get_post_meta( $product_id, '_woovr_show_description', true ) ?: $show_description;
					$clear_label       = ! empty( get_post_meta( $product_id, '_woovr_clear_label', true ) ) ? esc_html( get_post_meta( $product_id, '_woovr_clear_label', true ) ) : $clear_label;
					$clear_image       = get_post_meta( $product_id, '_woovr_clear_image', true ) ?: $clear_image;
					$clear_image_id    = get_post_meta( $product_id, '_woovr_clear_image_id', true ) ?: $clear_image_id;
				}

				if ( empty( $clear_label ) ) {
					$clear_label = esc_html__( 'Choose an option', 'wpc-variations-radio-buttons' );
				}

				// apply filters
				$clear_label       = apply_filters( 'woovr_clear_label', $clear_label, $product, $variation, $context );
				$clear_image       = apply_filters( 'woovr_clear_image', $clear_image, $product, $variation, $context );
				$clear_image_id    = apply_filters( 'woovr_clear_image_id', $clear_image_id, $product, $variation, $context );
				$selector          = apply_filters( 'woovr_selector', $selector, $product, $variation, $context );
				$orderby           = apply_filters( 'woovr_orderby', $orderby, $product, $variation, $context );
				$order             = apply_filters( 'woovr_order', $order, $product, $variation, $context );
				$show_name         = apply_filters( 'woovr_show_name', $show_name, $product, $variation, $context );
				$show_image        = apply_filters( 'woovr_show_image', $show_image, $product, $variation, $context );
				$show_price        = apply_filters( 'woovr_show_price', $show_price, $product, $variation, $context );
				$show_availability = apply_filters( 'woovr_show_availability', $show_availability, $product, $variation, $context );
				$show_description  = apply_filters( 'woovr_show_description', $show_description, $product, $variation, $context );

				// clear image src
				$clear_image_src = '';

				if ( $clear_image !== 'none' ) {
					$clear_image_src = wc_placeholder_img_src();

					if ( ( $clear_image === 'product' ) && ( $product_image_id = $product->get_image_id() ) ) {
						$product_image   = wp_get_attachment_image_src( $product_image_id, self::$image_size );
						$clear_image_src = $product_image[0];
					}

					if ( ( $clear_image === 'custom' ) && $clear_image_id ) {
						$custom_image    = wp_get_attachment_image_src( $clear_image_id, self::$image_size );
						$clear_image_src = $custom_image[0];
					}
				}

				$clear_image_src = apply_filters( 'woovr_clear_image_src', $clear_image_src, $product );

				// default attributes
				$df_attrs = [];

				if ( $variation ) {
					$df_attrs_o = $variation->get_attributes();
				} else {
					$df_attrs_o = $product->get_default_attributes();
				}

				foreach ( $df_attrs_o as $k => $v ) {
					$k_a              = 'attribute_' . str_replace( 'attribute_', '', $k );
					$df_attrs[ $k_a ] = $v;
				}

				// get default from URL
				$df_request = [];

				if ( isset( $_REQUEST ) ) {
					foreach ( wp_unslash( $_REQUEST ) as $rk => $rv ) {
						if ( strpos( $rk, 'attribute_' ) === 0 ) {
							$k_a                = 'attribute_' . str_replace( 'attribute_', '', $rk );
							$df_request[ $k_a ] = wc_clean( stripslashes( urldecode( $rv ) ) );
						}
					}
				}

				$df_attrs = array_merge( $df_attrs, $df_request );

				$children = apply_filters( 'woovr_get_children', $product->get_children(), $product );

				if ( ! empty( $children ) ) {
					// build children data
					$children_data = [];

					foreach ( $children as $child ) {
						$child_product = wc_get_product( $child );

						if ( ! $child_product || ! $child_product->variation_is_visible() ) {
							continue;
						}

						if ( ( $hide_unpurchasable === 'yes' ) && ! self::is_purchasable( $child_product ) ) {
							continue;
						}

						$attrs         = [];
						$product_attrs = $product->get_attributes();
						$child_attrs   = $child_product->get_attributes();

						foreach ( $child_attrs as $k => $a ) {
							if ( $a === '' ) {
								if ( $product_attrs[ $k ]->get_id() ) {
									foreach ( $product_attrs[ $k ]->get_terms() as $term ) {
										if ( ! empty( $allowed_terms ) && ! empty( $allowed_terms[ $k ] ) ) {
											if ( ! in_array( $term->slug, $allowed_terms[ $k ] ) ) {
												continue;
											}
										}

										$attrs[ 'attribute_' . $k ][] = $term->slug;
									}
								} else {
									// custom attribute
									foreach ( $product_attrs[ $k ]->get_options() as $option ) {
										if ( ! empty( $allowed_terms ) && ! empty( $allowed_terms[ $k ] ) ) {
											if ( ! in_array( $option, $allowed_terms[ $k ] ) ) {
												continue;
											}
										}

										$attrs[ 'attribute_' . $k ][] = $option;
									}
								}
							} else {
								if ( ! empty( $allowed_terms ) && ! empty( $allowed_terms[ $k ] ) ) {
									if ( ! in_array( $a, $allowed_terms[ $k ] ) ) {
										continue 2;
									}
								}

								$attrs[ 'attribute_' . $k ][] = $a;
							}
						}

						$attrs = woovr_combinations( $attrs );

						foreach ( $attrs as $attr ) {
							$children_data[] = [
								'id'      => $child,
								'product' => $child_product,
								'name'    => get_post_meta( $child, 'woovr_name', true ) ?: $child_product->get_formatted_name(),
								'price'   => $child_product->get_price(),
								'attrs'   => $attr
							];
						}
					}

					$children_data = apply_filters( 'woovr_get_children_data', $children_data, $product );

					// order
					if ( is_string( $orderby ) && ! empty( $orderby ) && ( $orderby !== 'default' ) ) {
						array_multisort( array_column( $children_data, $orderby ), SORT_ASC, $children_data );
					}

					if ( ! empty( $order ) && ( $order === 'desc' ) ) {
						$children_data = array_reverse( $children_data );
					}

					$children_data = apply_filters( 'woovr_get_children_ordered_data', $children_data, $product );

					if ( ! empty( $children_data ) ) {
						do_action( 'woovr_variations_above', $product );

						echo '<div class="woovr-variations ' . esc_attr( 'woovr-variations-' . $selector ) . '" data-click="0" data-description="' . esc_attr( $show_description ) . '">';

						do_action( 'woovr_variations_before', $product );
						// should add a fieldset and legend

						if ( in_array( $selector, [ 'default', 'grid', 'grid-2', 'grid-3', 'grid-4' ] ) ) {
							// show choose an option
							if ( $show_clear === 'yes' ) {
								$data_attrs = apply_filters( 'woovr_data_attributes_option_none', [
									'id'            => 0,
									'pid'           => $product_id,
									'sku'           => '',
									'purchasable'   => 'no',
									'attrs'         => '',
									'price'         => 0,
									'regular-price' => 0,
									'pricehtml'     => '',
									'availability'  => '',
									'weight'        => '',
									'dimensions'    => ''
								] );

								$df_checked = empty( $df_attrs ) ? 'checked' : '';

								echo '<div class="woovr-variation woovr-variation-radio ' . ( empty( $df_attrs ) ? 'woovr-variation-active' : '' ) . '" ' . self::data_attributes( $data_attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								do_action( 'woovr_variation_before' );

								$radio_id = 'woovr_' . $product_id . '_0';
								echo apply_filters( 'woovr_variation_radio_selector', '<div class="woovr-variation-selector"><input type="radio" id="' . esc_attr( $radio_id ) . '" name="' . esc_attr( $unique_id ) . '" ' . $df_checked . '/></div>', $product_id, $df_checked, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								if ( ( $show_image === 'yes' ) && ( $clear_image !== 'none' ) ) {
									echo '<div class="woovr-variation-image">' . apply_filters( 'woovr_clear_image', '<img src="' . esc_url( $clear_image_src ) . '"/>', $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								echo '<div class="woovr-variation-info">';
								echo '<div class="woovr-variation-name"><label for="' . esc_attr( $radio_id ) . '">' . apply_filters( 'woovr_clear_name', $clear_label, $product ) . '</label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo '<div class="woovr-variation-description">' . apply_filters( 'woovr_clear_description', '', $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo '</div><!-- /woovr-variation-info -->';

								do_action( 'woovr_variation_after' );

								echo '</div><!-- /woovr-variation -->';
							}

							// radio buttons
							foreach ( $children_data as $child_data ) {
								$child_id      = $child_data['id'];
								$child_product = $child_data['product'];
								$child_attrs   = htmlspecialchars( json_encode( $child_data['attrs'] ), ENT_QUOTES, 'UTF-8' );
								$diff_attrs    = array_diff( $child_data['attrs'], $df_attrs ); // find selected option
								$child_checked = empty( $diff_attrs ) ? 'checked' : '';

								// get name
								if ( ( $custom_name = get_post_meta( $child_id, 'woovr_name', true ) ) && ! empty( $custom_name ) ) {
									$child_name = $custom_name;
								} else {
									$child_name_arr = [];

									foreach ( $child_data['attrs'] as $k => $a ) {
										if ( $t = get_term_by( 'slug', $a, str_replace( 'attribute_', '', $k ) ) ) {
											$n = $t->name;
										} elseif ( $t = get_term_by( 'name', $a, str_replace( 'attribute_', '', $k ) ) ) {
											$n = $t->name;
										} else {
											$n = $a;
										}

										if ( $show_name === 'formatted_label' ) {
											$child_name_arr[] = wc_attribute_label( str_replace( 'attribute_', '', $k ), $product ) . ': ' . $n;
										} else {
											$child_name_arr[] = $n;
										}
									}

									$child_name = implode( ', ', $child_name_arr );

									if ( $product_name === 'yes' ) {
										$child_name = $product->get_name() . ' – ' . $child_name;
									}
								}

								// get image
								if ( $child_product->get_image_id() && ( $child_image = wp_get_attachment_image_src( $child_product->get_image_id(), self::$image_size ) ) ) {
									$child_image_src = $child_image[0];
								} else {
									$child_image_src = wc_placeholder_img_src();
								}

								// custom image
								if ( ( $child_image_id = get_post_meta( $child_id, 'woovr_image_id', true ) ) && ( $child_image = wp_get_attachment_image_src( absint( $child_image_id ), self::$image_size ) ) ) {
									$child_image_src = $child_image[0];
								} elseif ( get_post_meta( $child_id, 'woovr_image', true ) ) {
									$child_image_src = get_post_meta( $child_id, 'woovr_image', true );
								}

								$child_image_src = apply_filters( 'woovr_variation_image_src', $child_image_src, $child_product );
								$child_images    = array_filter( explode( ',', get_post_meta( $child_id, 'wpcvi_images', true ) ) );
								$data_attrs      = apply_filters( 'woovr_data_attributes', [
									'id'            => $child_id,
									'pid'           => $product_id,
									'sku'           => $child_product->get_sku(),
									'purchasable'   => self::is_purchasable( $child_product ) ? 'yes' : 'no',
									'attrs'         => $child_attrs,
									'price'         => wc_get_price_to_display( $child_product ),
									'regular-price' => wc_get_price_to_display( $child_product, [ 'price' => $child_product->get_regular_price() ] ),
									'pricehtml'     => htmlentities( $child_product->get_price_html() ),
									'imagesrc'      => esc_url( $child_image_src ),
									'availability'  => htmlentities( wc_get_stock_html( $child_product ) ),
									'weight'        => htmlentities( wc_format_weight( $child_product->get_weight() ) ),
									'dimensions'    => htmlentities( wc_format_dimensions( $child_product->get_dimensions( false ) ) ),
									'images'        => ! empty( $child_images ) ? 'yes' : 'no'
								], $child_product );

								$child_class = 'woovr-variation woovr-variation-radio';

								if ( $child_checked === 'checked' ) {
									$child_class .= ' woovr-variation-active';
								}

								echo '<div class="' . esc_attr( apply_filters( 'woovr_variation_class', $child_class, $child_product ) ) . '" ' . self::data_attributes( $data_attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								do_action( 'woovr_variation_before', $child_product );

								$radio_id = 'woovr_' . $product_id . '_' . $child_id;
								echo apply_filters( 'woovr_variation_radio_selector', '<div class="woovr-variation-selector"><input type="radio" id="' . esc_attr( $radio_id ) . '" name="' . esc_attr( $unique_id ) . '" ' . $child_checked . '/></div>', $product_id, $child_checked, $child_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								if ( $show_image === 'yes' ) {
									echo '<div class="woovr-variation-image"><img src="' . esc_url( $child_image_src ) . '" alt=""/></div>';
								}

								echo '<div class="woovr-variation-info">';
								$child_info = '<div class="woovr-variation-name"><label for="' . esc_attr( $radio_id ) . '">' . apply_filters( 'woovr_variation_name', $child_name, $child_product ) . '</label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								if ( $show_price === 'yes' ) {
									$child_info .= '<div class="woovr-variation-price">' . apply_filters( 'woovr_variation_price', $child_product->get_price_html(), $child_product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								if ( $show_availability === 'yes' ) {
									$child_info .= '<div class="woovr-variation-availability">' . apply_filters( 'woovr_variation_availability', wc_get_stock_html( $child_product ), $child_product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								if ( $show_description === 'yes' ) {
									$child_info .= '<div class="woovr-variation-description">' . apply_filters( 'woovr_variation_description', $child_product->get_description(), $child_product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								echo apply_filters( 'woovr_variation_info', $child_info, $child_product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo '</div><!-- /woovr-variation-info -->';

								do_action( 'woovr_variation_after', $child_product );

								echo '</div><!-- /woovr-variation -->';
							}
						} else {
							// dropdown
							echo '<div class="woovr-variation woovr-variation-dropdown">';

							if ( ( $selector === 'select' ) && ( $show_image === 'yes' ) ) {
								echo '<div class="woovr-variation-image">' . apply_filters( 'woovr_clear_image', '<img src="' . esc_url( $clear_image_src ) . '"/>', $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}

							echo '<div class="woovr-variation-selector"><select class="woovr-variation-select" id="' . esc_attr( $unique_id ) . '">';

							// show choose an option
							if ( $show_clear === 'yes' ) {
								$data_attrs = apply_filters( 'woovr_data_attributes_option_none', [
									'id'            => 0,
									'pid'           => $product_id,
									'sku'           => '',
									'purchasable'   => 'no',
									'attrs'         => '',
									'price'         => 0,
									'regular-price' => 0,
									'pricehtml'     => '',
									'imagesrc'      => $show_image === 'yes' ? $clear_image_src : '',
									'description'   => htmlentities( apply_filters( 'woovr_clear_description', '', $product ) ),
									'availability'  => ''
								] );
								echo '<option value="0" ' . self::data_attributes( $data_attrs ) . '>' . apply_filters( 'woovr_clear_name', $clear_label, $product ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}

							foreach ( $children_data as $child_data ) {
								$child_id      = $child_data['id'];
								$child_product = $child_data['product'];
								$child_attrs   = htmlspecialchars( json_encode( $child_data['attrs'] ), ENT_QUOTES, 'UTF-8' );

								// get name
								if ( ( $custom_name = get_post_meta( $child_id, 'woovr_name', true ) ) && ! empty( $custom_name ) ) {
									$child_name = $custom_name;
								} else {
									$child_name_arr = [];

									foreach ( $child_data['attrs'] as $k => $a ) {
										if ( $t = get_term_by( 'slug', $a, str_replace( 'attribute_', '', $k ) ) ) {
											$n = $t->name;
										} elseif ( $t = get_term_by( 'name', $a, str_replace( 'attribute_', '', $k ) ) ) {
											$n = $t->name;
										} else {
											$n = $a;
										}

										if ( $show_name === 'formatted_label' ) {
											$child_name_arr[] = wc_attribute_label( str_replace( 'attribute_', '', $k ), $product ) . ': ' . $n;
										} else {
											$child_name_arr[] = $n;
										}
									}

									$child_name = implode( ', ', $child_name_arr );

									if ( $product_name === 'yes' ) {
										$child_name = $product->get_name() . ' – ' . $child_name;
									}
								}

								// get image
								if ( $child_product->get_image_id() && ( $child_image = wp_get_attachment_image_src( $child_product->get_image_id(), self::$image_size ) ) ) {
									$child_image_src = $child_image[0];
								} else {
									$child_image_src = wc_placeholder_img_src();
								}

								// custom image
								if ( ( $child_image_id = get_post_meta( $child_id, 'woovr_image_id', true ) ) && ( $child_image = wp_get_attachment_image_src( absint( $child_image_id ), self::$image_size ) ) ) {
									$child_image_src = $child_image[0];
								} elseif ( get_post_meta( $child_id, 'woovr_image', true ) ) {
									$child_image_src = esc_url( get_post_meta( $child_id, 'woovr_image', true ) );
								}

								$child_image_src = esc_url( apply_filters( 'woovr_variation_image_src', $child_image_src, $child_product ) );

								// get info
								$child_info = '';

								if ( $show_price === 'yes' ) {
									$child_info .= '<span class="woovr-variation-price">' . apply_filters( 'woovr_variation_price', $child_product->get_price_html(), $child_product ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								if ( $show_availability === 'yes' ) {
									$child_info .= '<span class="woovr-variation-availability">' . apply_filters( 'woovr_variation_availability', wc_get_stock_html( $child_product ), $child_product ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								if ( $show_description === 'yes' ) {
									$child_info .= '<span class="woovr-variation-description">' . apply_filters( 'woovr_variation_description', $child_product->get_description(), $child_product ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								$data_attrs = apply_filters( 'woovr_data_attributes', [
									'id'            => $child_id,
									'pid'           => $product_id,
									'sku'           => $child_product->get_sku(),
									'purchasable'   => self::is_purchasable( $child_product ) ? 'yes' : 'no',
									'attrs'         => $child_attrs,
									'price'         => wc_get_price_to_display( $child_product ),
									'regular-price' => wc_get_price_to_display( $child_product, [ 'price' => $child_product->get_regular_price() ] ),
									'pricehtml'     => htmlentities( $child_product->get_price_html() ),
									'imagesrc'      => $show_image === 'yes' ? $child_image_src : '',
									'description'   => htmlentities( apply_filters( 'woovr_variation_info', $child_info, $child_product ) ),
									'availability'  => htmlentities( wc_get_stock_html( $child_product ) )
								], $child_product );
								$diff_attrs = array_diff( $child_data['attrs'], $df_attrs ); // find selected option

								echo '<option value="' . esc_attr( $child_id ) . '" ' . self::data_attributes( $data_attrs ) . ' ' . esc_attr( empty( $diff_attrs ) ? 'selected' : '' ) . '>' . apply_filters( 'woovr_variation_name', $child_name, $child_product ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}

							echo '</select></div><!-- /woovr-variation-selector -->';

							if ( ( $selector === 'select' ) && ( $show_price === 'yes' ) ) {
								echo '<div class="woovr-variation-price"></div>';
							}

							echo '</div><!-- /woovr-variation -->';
						}

						do_action( 'woovr_variations_after', $product );

						echo '</div><!-- /woovr-variations -->';

						do_action( 'woovr_variations_below', $product );
					}
				}

				$variations_form = ob_get_clean();

				if ( self::enable_cache( $context ) ) {
					set_transient( $cache_id, $variations_form, 24 * HOUR_IN_SECONDS );
				}
			}

			echo apply_filters( 'woovr_variations_form', $variations_form, $product, $variation, $context, $allowed_terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		function wpcsm_locations( $locations ) {
			$locations['WPC Variations Radio Buttons'] = [
				'woovr_variations_above'  => esc_html__( 'Before variations wrap', 'wpc-variations-radio-buttons' ),
				'woovr_variations_below'  => esc_html__( 'After variations wrap', 'wpc-variations-radio-buttons' ),
				'woovr_variations_before' => esc_html__( 'Before variations', 'wpc-variations-radio-buttons' ),
				'woovr_variations_after'  => esc_html__( 'After variations', 'wpc-variations-radio-buttons' ),
				'woovr_variation_before'  => esc_html__( 'Before variation', 'wpc-variations-radio-buttons' ),
				'woovr_variation_after'   => esc_html__( 'After variation', 'wpc-variations-radio-buttons' ),
			];

			return $locations;
		}
	}
}

if ( ! function_exists( 'woovr_init' ) ) {
	add_action( 'plugins_loaded', 'woovr_init', 11 );

	function woovr_init() {
		if ( class_exists( 'WC_Product' ) ) {
			WPClever_Woovr::instance();

			if ( is_admin() ) {
				WPClever_Woovr_Backend::instance();
			}
		}
	}
}

if ( ! function_exists( 'woovr_combinations' ) ) {
	function woovr_combinations( $arrays ) {
		$result = [ [] ];

		foreach ( $arrays as $property => $property_values ) {
			$tmp = [];

			foreach ( $result as $result_item ) {
				foreach ( $property_values as $property_value ) {
					$tmp[] = array_merge( $result_item, [ $property => $property_value ] );
				}
			}

			$result = $tmp;
		}

		return apply_filters( 'woovr_combinations', $result );
	}
}
