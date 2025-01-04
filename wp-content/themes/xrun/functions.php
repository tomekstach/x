<?php
/**
 * XRUN Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package XRUN
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define('CHILD_THEME_XRUN_VERSION', '1.0.0');

/**
 * Enqueue styles
 */
function child_enqueue_styles()
{
    wp_enqueue_style('xrun-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_XRUN_VERSION, 'all');
}

add_action('wp_enqueue_scripts', 'child_enqueue_styles', 15);

add_action('acf/init', 'set_acf_settings');
function set_acf_settings()
{
    acf_update_setting('enable_shortcode', true);
}

add_filter('the_excerpt', 'shortcode_unautop');
add_filter('the_excerpt', 'do_shortcode');

function post_title_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts, 'post_title');

    $post_title = get_the_title($atts['id']);
    return $post_title;
}
add_shortcode('post_title', 'post_title_shortcode');

function custom_remove_all_quantity_fields($return, $product)
{
    return true;
}

add_filter('woocommerce_is_sold_individually', 'custom_remove_all_quantity_fields', 10, 2);

add_action('woocommerce_checkout_before_order_review', 'custom_before_order_review_heading', 10, 1);

function custom_before_order_review_heading()
{
    global $woocommerce;
    $items = $woocommerce->cart->get_cart();

    if (wp_doing_ajax()) {
        add_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);
    } else {
        echo '<div class="woocommerce_checkout-your-runs"><h4>Wybrałeś biegi:</h4><div>';
        $i = 0;
        foreach ($items as $item => $values) {
            $getProductDetail = wc_get_product($values['product_id']);
            echo $getProductDetail->get_image('thumbnail');
            $i++;
        }
        if ($i == 0) {
            echo '<p>Brak biegów w koszyku</p>';
        }

        if ($i <= 5) {
            echo '<a href="/zapisy" id="your-runs-add"><img src="/wp-content/uploads/2024/12/dodaj-bieg.png" alt="Dodaj kolejny bieg" /></a>';
        }
        echo '</div></div>';
    }
}

add_filter('woocommerce_add_to_cart_validation', 'allowed_products_variation_in_the_cart', 10, 5);
function allowed_products_variation_in_the_cart($passed, $product_id, $quantity, $variation_id, $variations = [])
{
    $kids = false;
    $adults = false;

    $variation_obj = new WC_Product_Variation($variation_id);
    $attributes = $variation_obj->get_attributes();

    if (array_key_exists('dystans', $attributes) and array_key_exists('typ', $attributes)) {
        if ($attributes['dystans'] === 'Kids' and $attributes['typ'] === 'Bieg') {
            $kids = true;
        } elseif ($attributes['typ'] === 'Bieg') {
            $adults = true;
        }
    }

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $cart_product_id = $cart_item['product_id'];

        if (array_key_exists('variation', $cart_item)) {
            if (array_key_exists('attribute_typ', $cart_item['variation'])) {
                if ($cart_item['variation']['attribute_typ'] === 'Bieg') {
                    if ($cart_product_id == $product_id) {
                        wc_add_notice(__('Ten bieg został już dodany do koszyka!', 'domain'), 'error');
                        $passed = false; // don't add the new product to the cart
                        break;
                    }

                    if ($cart_item['variation']['attribute_dystans'] === 'Kids') {
                        $kids = true;
                    } else {
                        $adults = true;
                    }
                }
            }
        }
    }

    if ($kids and $adults) {
        wc_add_notice(__('Nie można dodać biegu Kids i dorosłego do koszyka jednocześnie!', 'domain'), 'error');
        $passed = false; // don't add the new product to the cart
    }

    return $passed;
}

add_action('woocommerce_update_order', 'custom_update_order');
function custom_update_order($order_id)
{
    global $wpdb;
    // Get runs from the database
    $runs = $wpdb->get_results("SELECT * FROM rnx_starting_runs WHERE year = YEAR(CURDATE())+1");
    if (empty($runs)) {
        $runs = $wpdb->get_results("SELECT * FROM rnx_starting_runs WHERE year = YEAR(CURDATE())");
    }

    // Get distances from the database
    $distances = $wpdb->get_results("SELECT * FROM rnx_starting_distances");

    $order = wc_get_order($order_id);
    $status = $order->get_status();

    // Remove starting list for given orderNumber
    $wpdb->delete('rnx_starting_list', ['orderNumber' => $order_id]);

    if ($status === 'cancelled' or $status === 'failed' or $status === 'refunded' or $status === 'checkout-draft') {
        return;
    }

    if ($status === 'completed') {
        $status = 'tak';
    } else {
        $status = 'nie';
    }

    $startingList = [];
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $productID = $product->get_parent_id();
        $attributes = $product->get_attributes();

        if ($attributes['typ'] === 'Bieg') {
            $distance = searchForValue($attributes['dystans'], 'name', $distances);
            $run = searchForValue($productID, 'productID', $runs);

            foreach ($order->meta_data as $metaItem) {
                $data = $metaItem->get_data();

                switch ($data['key']) {
                    case '_billing_birth_date':
                        $birthDate = $metaItem->value;
                        break;
                    case '_billing_sex':
                        $sex = $metaItem->value;
                        if ($sex === 'mezczyzna') {
                            $sex = 'mezczyzna';
                        } else {
                            $sex = 'kobieta';
                        }
                        break;
                    case '_billing_alarm_phone':
                        $alarmPhone = $metaItem->value;
                        break;
                    case '_billing_meal':
                        $meal = $metaItem->value;
                        if ($meal === 'miesny') {
                            $meal = 'miesny';
                        } else {
                            $meal = 'vege';
                        }
                        break;
                    case '_billing_club':
                        $club = $metaItem->value;
                        break;
                }
            }

            $startingList = [
                'orderNumber' => $order_id,
                'firstName' => $order->data['billing']['first_name'],
                'surname' => $order->data['billing']['last_name'],
                'address' => $order->data['billing']['address_1'],
                'city' => $order->data['billing']['city'],
                'postcode' => $order->data['billing']['postcode'],
                'country' => $order->data['billing']['country'],
                'email' => $order->data['billing']['email'],
                'phone' => $order->data['billing']['phone'],
                'birthDate' => $birthDate,
                'sex' => $sex,
                'club' => $club,
                'alarmPhone' => $alarmPhone,
                'meal' => $meal,
                'paymentStatus' => $status,
                'distanceID' => $distance->distanceID,
                'runID' => $run->runID,
            ];

            // Store data in the database
            $return = $wpdb->insert('rnx_starting_list', $startingList);

            if ($return === false) {
                print_r($wpdb->last_error);
                echo 'Error while inserting data to the database';
                exit();
            }
        }
    }
}

add_filter('woocommerce_return_to_shop_redirect', 'custom_woocommerce_return_to_shop_redirect');

function custom_woocommerce_return_to_shop_redirect()
{
    return site_url() . '/zapisy/';
}

/**
 * Function for `woocommerce_return_to_shop_text` filter-hook.
 *
 * @param string $default_text Default text.
 *
 * @return string
 */
function custom_woocommerce_return_to_shop_text_filter($default_text)
{
    $default_text = 'Wróć do zapisów';
    return $default_text;
}
add_filter('woocommerce_return_to_shop_text', 'custom_woocommerce_return_to_shop_text_filter');

// Function to search for a specific object variable value in an array of objects
function searchForValue($value, $key, $array)
{
    foreach ($array as $k => $val) {
        if ($val->$key == $value) {
            return $val;
        }
    }
    return null;
}

/**
 * Register REST API endpoint for getting starting list for the given run
 */
add_action('rest_api_init', function () {
    register_rest_route('xrun/v1', '/run/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_xrun_run',
    ));
});

function get_xrun_run($data)
{
    global $wpdb;
    $data['id'] = (int) $data['id'];

    // Get starting list for the given run from the database
    $startingList = $wpdb->get_results($wpdb->prepare("SELECT list.*, runs.name AS run, dist.name AS distance FROM rnx_starting_list AS list LEFT JOIN rnx_starting_runs AS runs ON runs.runID = list.runID LEFT JOIN rnx_starting_distances AS dist ON dist.distanceID = list.distanceID WHERE list.runID = '" . $data['id'] . "' ORDER BY list.orderNumber ASC"));

    if (empty($startingList)) {
        $startingList = [];
    }

    return $startingList;
}

// Remove the existing action
function remove_astra_woocommerce_before_main_content()
{
    // Ensure the class is loaded before trying to remove the action
    if (class_exists('Astra_WooCommerce')) {
        remove_action('woocommerce_before_main_content', array(Astra_WooCommerce::get_instance(), 'before_main_content_start'));
    }
}
add_action('init', 'remove_astra_woocommerce_before_main_content');

// Add your custom action
function custom_woocommerce_before_main_content()
{
    // Your custom code here
    ?>
    <div id="primary" class="content-area primary">
        <section class="ast-single-entry-banner" data-post-type="page" data-banner-layout="layout-2">
            <div class="ast-container">
                <h1 class="entry-title" itemprop="headline">ZAPISY</h1>
                <div class="ast-breadcrumbs-wrapper">
                    <div class="ast-breadcrumbs-inner">
                        <nav role="navigation" aria-label="Breadcrumbs" class="breadcrumb-trail breadcrumbs"><div class="ast-breadcrumbs"><ul class="trail-items"><li class="trail-item trail-begin"><a href="https://x.astosoft.pl/" rel="home"><span>Home</span></a></li><li class="trail-item trail-end"><span><span>ZAPISY</span></span></li></ul></div></nav>
                    </div>
                </div>
            </div>
        </section>

        <?php astra_primary_content_top();?>

        <main id="main" class="site-main">
            <div class="ast-woocommerce-container">
                <div class="checkout-steps">
                    <a class="checkout-step" href="/zapisy/">
                        <button>1</button>
                        <span>wybierz bieg</span>
                    </a>
                    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
                    <div class="checkout-step active">
                        <button>2</button>
                        <span>wybierz dystans</span>
                    </div>
                    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
                    <a class="checkout-step" href="/zamowienie/">
                        <button>3</button>
                        <span>dane zawodnika</span>
                    </a>
                    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
                    <div class="checkout-step">
                        <button>4</button>
                        <span>podsumowanie</span>
                    </div>
                </div>

                <div class="wp-block-uagb-advanced-heading uagb-block-0bedd072"><h1 class="uagb-heading-text">wybierz dystans</h1></div>
    <?php
}
add_action('woocommerce_before_main_content', 'custom_woocommerce_before_main_content');

remove_filter('woocommerce_get_cart_url', 'astra_woocommerce_get_cart_url');

// Override the cart URL
function custom_wc_get_cart_url()
{
    return site_url('/zamowienie/'); // Replace with your custom cart URL
}
add_filter('woocommerce_add_to_cart_redirect', 'custom_wc_get_cart_url', 100);

// Optionally, you can also override the cart URL in other places
add_filter('woocommerce_get_cart_url', 'custom_wc_get_cart_url', 100);

// Schedule the cron event
function custom_schedule_cron_event()
{
    if (!wp_next_scheduled('custom_cron_event')) {
        wp_schedule_event(time(), 'hourly', 'custom_cron_event');
    }
}
add_action('wp', 'custom_schedule_cron_event');

// Callback function for the cron event
function custom_cron_event_callback()
{
    // Update prices for the products
    $args = array(
        'category_name' => 'imprezy', // Category slug
        'posts_per_page' => -1, // Number of posts to retrieve (-1 for all posts)
    );

    // Create a new WP_Query instance
    $query = new WP_Query($args);

    $currentTimestamp = time();

    // Check if there are any posts to display
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            // Get the varation product ID
            $variations = get_field('boks');
            $variantionsNumber = count($variations);

            for ($i = 0; $i < $variantionsNumber; $i++) {
                $variationID = (int) get_field('boks_' . $i . '_identyfikator_wariantu');
                $currentPrice = 0;
                // Get price for the product
                if ($variationID > 0) {
                    $prices = get_field('boks_' . $i . '_cennik');
                    $pricesNumber = count($prices);

                    for ($j = 0; $j < $pricesNumber; $j++) {
                        $price = get_field('boks_' . $i . '_cennik_' . $j . '_cena');
                        $dateTo = get_field('boks_' . $i . '_cennik_' . $j . '_do_kiedy');
                        // Create a DateTime object from the date string
                        $dateTime = DateTime::createFromFormat('d/m/Y H:i:s', $dateTo . ' 23:59:59');
                        // Get the timestamp from the DateTime object
                        $timestamp = $dateTime->getTimestamp();

                        if ($currentTimestamp < $timestamp and $currentPrice === 0) {
                            $currentPrice = $price;
                            // Update the price for the product
                            update_post_meta($variationID, '_sale_price', $currentPrice);
                            update_post_meta($variationID, '_sale_price_dates_to', $timestamp);
                            echo 'Price updated for product ID: ' . $variationID . ' to: ' . $currentPrice . '<br>';
                        }
                    }

                    // Disable the product if the current price is 0
                    if ($currentPrice === 0) {
                        // Disable the product
                    }
                }
            }
        }
    }

    // Restore original post data
    wp_reset_postdata();
}
add_action('custom_cron_event', 'custom_cron_event_callback');
