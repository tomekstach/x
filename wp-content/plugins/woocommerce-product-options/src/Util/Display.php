<?php

namespace Barn2\Plugin\WC_Product_Options\Util;

use Barn2\Plugin\WC_Product_Options\Model\Option as Option_Model;
use Barn2\Plugin\WC_Product_Options\Util\Price as Price_Util;

/**
 * Display utilities.
 *
 * @package   Barn2\woocommerce-product-options
 * @author    Barn2 Plugins <support@barn2.com>
 * @license   GPL-3.0
 * @copyright Barn2 Media Ltd
 */
final class Display
{

    /**
     * Retrieves the HTML for the supplied groups.
     *
     * @param array $groups
     * @param WC_Product $product
     */
    public static function get_groups_html($groups, $product)
    {
        ob_start();

        print('<h2>Dodaj do pakietu</h2>');

        print('<div class="wpo-options-container">');

        foreach ($groups as $group) {
            // AstoSoft
            print('<div class="wpo-options-group">');
            $options = Option_Model::where('group_id', $group->id)->orderBy('menu_order', 'asc')->get();

            if ($options->isEmpty()) {
                continue;
            }

            if ($group->display_name) {
                $context = 'group_name';
                $group_name = apply_filters('wc_product_options_get_output_string', $group->name, $group, $context);
                printf('<h2 class="wpo-group-title">%s</h2>', esc_html($group_name));
            }

            foreach ($options as $option) {
                $class = Util::get_field_class($option->type);

                if (!class_exists($class)) {
                    continue;
                }

                $field = new $class($option, $product);

                $field->render();
            }
            // AstoSoft
            print('</div>');
        }

        print('<input type="hidden" name="wpo-hidden-fields" value="1" />');

        print('</div>');

        return ob_get_clean();
    }

    /**
     * Retrives the totals HTML for the supplied product
     *
     * @param WC_Product $product
     * @return string
     */
    public static function get_totals_container_html($product)
    {
        /**
         * Filters the product price to display in the totals container.
         *
         * @param float $price The product price.
         * @param WC_Product $product The product object.
         * @return string
         */
        $price = apply_filters('wc_product_options_product_price_container', wc_get_price_to_display($product), $product);

        $price_string = str_replace('%1$s', '<span class="wpo-currency">%1$s</span>', get_woocommerce_price_format());
        $price_string = "<span class=\"wpo-price\">$price_string</span>";

        $formatted_price = sprintf(
            $price_string,
            get_woocommerce_currency_symbol(),
            Price_Util::get_formatted_price($price)
        );

        $exclude_price = Option_Model::get_product_price_exclusion_status($product);

        $html = sprintf(
            '<div class="wpo-totals-container"
				data-product-price="%1$s"
				data-exclude-product-price="%4$s"
				data-weight="%5$s"
				data-width="%6$s"
				data-length="%7$s"
				data-height="%8$s"
			>
				<span class="wpo-totals-label">%3$s<span>
				%2$s
			</div>',
            esc_attr($price),
            wp_kses($formatted_price, ['span' => ['class' => []]]),
            esc_html__('Total: ', 'woocommerce-product-options'),
            $exclude_price ? 'true' : 'false',
            esc_attr($product->get_weight()),
            esc_attr($product->get_width()),
            esc_attr($product->get_length()),
            esc_attr($product->get_height())
        );

        return $html;
    }

    /**
     * Get the output string for the field.
     *
     * This method runs a filter to allow for further string customization,
     * including internationalization.
     *
     * @param string $value        The string to output.
     * @param string $id           A unique id assigned to the string.
     * @param string $context        A title describing the string.
     * @param string $content_type The type of content to output: either 'text', 'textarea' or 'editor'.
     * @return string
     */
    public static function get_output_string($string_value, $args)
    {
        return apply_filters('wc_product_options_get_frontend_string', $string_value, $args);
    }

}
