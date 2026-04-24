<?php

/**
 * The steps tabs
 *
 * @package WPMultiStepCheckout
 */

defined('ABSPATH') || exit;

$i                  = 0;
// AstoSoft
$number_of_steps = ($show_login_step) ? count($steps) + 1 + 2 : count($steps) + 2;
$current_step_title = ($show_login_step) ? 'login' : key(array_slice($steps, 0, 1, true));

do_action('wpmc_before_tabs');

// AstoSoft - start
// Check the all products in the cart and if there is a product with category "bieg" then show the steps tabs
$show_steps_tabs = false;
foreach (WC()->cart->get_cart() as $cart_item) {
    $product_id = $cart_item['product_id'];
    if (has_term('bieg', 'product_cat', $product_id)) {
        $show_steps_tabs = true;
        break;
    }
}

if ($show_steps_tabs):
?>
    <!-- The steps tabs -->
    <div class="checkout-steps">
        <a class="checkout-step" href="<?php echo wpml_url_by_slug('zapisy', 'page'); ?>">
            <button>1</button>
            <span><?php esc_html_e('wybierz bieg', 'woocommerce'); ?></span>
        </a>
        <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
        <div class="checkout-step">
            <button>2</button>
            <span><?php esc_html_e('wybierz dystans', 'woocommerce'); ?></span>
        </div>
        <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
        <a class="checkout-step active" href="<?php echo wpml_url_by_slug('zamowienie', 'page'); ?>" id="checkout-step-3">
            <button>3</button>
            <span><?php esc_html_e('dane zawodnika', 'woocommerce'); ?></span>
        </a>
        <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
        <div class="checkout-step" id="checkout-step-4">
            <button>4</button>
            <span><?php esc_html_e('podsumowanie', 'woocommerce'); ?></span>
        </div>
    </div>
    <div class="wp-block-uagb-advanced-heading uagb-block-0bedd072">
        <h1 class="uagb-heading-text" id="checkout-step-header"><?php esc_html_e('dane zawodnika', 'woocommerce'); ?></h1>
    </div>
<?php
endif;
// AstoSoft - end
?>