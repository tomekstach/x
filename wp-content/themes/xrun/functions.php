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

    // Check the all products in the cart and if there is a product with category "bieg" then show the runs
    $show_runs = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        if (has_term('bieg', 'product_cat', $product_id)) {
            $show_runs = true;
            break;
        }
    }

    if (wp_doing_ajax()) {
        add_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);
    } elseif ($show_runs) {
        echo '<div class="woocommerce_checkout-your-runs"><h4>' . esc_html__('Wybrałeś biegi:', 'woocommerce') . '</h4><div>';
        $i = 0;
        foreach ($items as $item => $values) {
            $getProductDetail = wc_get_product($values['product_id']);
            echo $getProductDetail->get_image('thumbnail');
            $i++;
        }
        if ($i == 0) {
            echo '<p>' . esc_html__('Brak biegów w koszyku', 'woocommerce') . '</p>';
        }

        if ($i <= 5) {
            echo '<a href="' . wpml_url_by_slug('zapisy', 'page') . '" id="your-runs-add"><img src="/wp-content/uploads/2024/12/dodaj-bieg.png" alt="' . esc_attr__('Dodaj kolejny bieg', 'woocommerce') . '" /></a>';
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
                        wc_add_notice(__('Ten bieg został już dodany do koszyka!', 'woocommerce'), 'error');
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
        wc_add_notice(__('Nie można dodać biegu Kids i dorosłego do koszyka jednocześnie!', 'woocommerce'), 'error');
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

    $runsIDs = [];
    foreach ($runs as $key => $run) {
        $runsIDs[] = $run->runID;
    }

    // Get distances from the database
    $distances = $wpdb->get_results("SELECT distance.*, run_distance.ordering FROM rnx_starting_distances AS distance LEFT JOIN rnx_starting_runs_distances AS run_distance ON run_distance.distanceID = distance.distanceID WHERE run_distance.runID IN (" . implode(',', $runsIDs) . ") ORDER BY run_distance.ordering ASC");

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

            $club = '-';
            $sex = 'kobieta';
            $meal = 'vege';
            $birthDate = '';
            $alarmPhone = '';

            foreach ($order->meta_data as $metaItem) {
                $data = $metaItem->get_data();

                switch ($data['key']) {
                    case 'billing_birth_date':
                        $birthDate = $metaItem->value;
                        break;
                    case 'billing_sex':
                        $sex = $metaItem->value;
                        if ($sex === 'mezczyzna') {
                            $sex = 'mezczyzna';
                        } else {
                            $sex = 'kobieta';
                        }
                        break;
                    case 'billing_alarm_phone':
                        $alarmPhone = $metaItem->value;
                        break;
                    case 'billing_meal':
                        $meal = $metaItem->value;
                        if ($meal === 'miesny') {
                            $meal = 'miesny';
                        } else {
                            $meal = 'vege';
                        }
                        break;
                    case 'billing_club':
                        $club = $metaItem->value;
                        if (strlen(trim($club)) > 0) {
                            $club = $club;
                        } else {
                            $club = '-';
                        }
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
    return wpml_url_by_slug('zapisy', 'page');
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
    $default_text = esc_html__('Wróć do zapisów', 'woocommerce');
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

    register_rest_route('xrun/v1', '/results/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_xrun_results',
    ));

    register_rest_route('xrun/v1', '/resultsKids/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_xrun_results_kids',
    ));

    register_rest_route('xrun/v1', '/currentRun/', array(
        'methods' => 'GET',
        'callback' => 'get_xrun_current_run',
    ));

    register_rest_route('xrun/v1', '/cupResults/(?P<year>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_xrun_cup_results',
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

function get_xrun_results($data)
{
    global $wpdb;
    $data['id'] = (int) $data['id'];

    // Get starting list for the given run from the database
    $results = $wpdb->get_results($wpdb->prepare("SELECT list.*, runs.name AS run, dist.name AS distance FROM rnx_starting_results AS list LEFT JOIN rnx_starting_runs AS runs ON runs.runID = list.runID LEFT JOIN rnx_starting_distances AS dist ON dist.distanceID = list.distanceID WHERE list.runID = '" . $data['id'] . "' AND list.distanceID != '4' ORDER BY list.position ASC"));

    if (empty($results)) {
        $results = [];
    }

    return $results;
}

function get_xrun_results_kids($data)
{
    global $wpdb;
    $data['id'] = (int) $data['id'];

    // Get starting list for the given run from the database
    $results = $wpdb->get_results($wpdb->prepare("SELECT list.*, runs.name AS run, dist.name AS distance FROM rnx_starting_results AS list LEFT JOIN rnx_starting_runs AS runs ON runs.runID = list.runID LEFT JOIN rnx_starting_distances AS dist ON dist.distanceID = list.distanceID WHERE list.runID = '" . $data['id'] . "' AND list.distanceID = '4' ORDER BY list.id ASC"));

    if (empty($results)) {
        $results = [];
    }

    return $results;
}

function get_xrun_cup_results($data)
{
    global $wpdb;
    // Get the list of runs for the current year
    $data['year'] = (int) $data['year'];
    $data['year'] = ($data['year'] >= 2025 and $data['year'] <= date('Y')) ? $data['year'] : date('Y');
    $runs = $wpdb->get_results($wpdb->prepare("SELECT * FROM rnx_starting_runs WHERE year = %d", $data['year']));

    // Get the list of distances
    $distances = $wpdb->get_results("SELECT * FROM rnx_starting_distances WHERE distanceID != 7 ORDER BY distanceID ASC");

    // Prepare the results array
    $results = [];

    // Loop through each distance
    foreach ($distances as $distance) {
        // Prepare the results for the current distance
        $distanceResults = [
            'distance' => $distance->name,
            'year' => $data['year'],
            'results' => [],
        ];

        $runsIDs = [];

        // Loop through each run
        foreach ($runs as $run) {
            // Get the results for the current run and distance
            $runResults = $wpdb->get_results($wpdb->prepare("SELECT firstName, surname, sex, club, city, category FROM rnx_starting_results WHERE runID = %d AND distanceID = %d ORDER BY position ASC", $run->runID, $distance->distanceID));

            foreach ($runResults as $result) {
                // Find the runner
                $matchedResult = array_filter($distanceResults['results'], function ($r) use ($result) {
                    return $r->firstName === $result->firstName && $r->surname === $result->surname && $r->city === $result->city && $r->sex === $result->sex;
                });
                if (!empty($matchedResult)) {
                    // If the runner already exists, update their points
                    // Get the matched key
                    $matchedKey = array_key_first($matchedResult);
                    $matchedResult = $matchedResult[$matchedKey];
                    $distanceResults['results'][$matchedKey] = $matchedResult;
                } else {
                    // If the runner does not exist, add them to the results
                    if ($distance->distanceID == 4) {
                        // Remove 'LAT' from category
                        $result->category = trim(str_replace('LAT', '', $result->category));
                        $result->category = trim(str_replace(' ', '', $result->category));
                        $result->category = $result->sex == 'mezczyzna' ? $result->category . 'm' : $result->category . 'k';
                    }
                    $matchedResult = $result;
                    $matchedResult->cupPoints = 0;
                    $distanceResults['results'][] = $matchedResult;
                }
            }

            $runsIDs[] = $run->runID;
        }

        // If there are results for the current distance, add them to the main results array
        if (!empty($distanceResults['results'])) {
            // Get all runners results for the current distance
            foreach ($distanceResults['results'] as $key => $result) {
                $runResults = $wpdb->get_results($wpdb->prepare("SELECT runID, startingNumber, time, cupPoints, position, positionSex FROM rnx_starting_results WHERE runID IN (" . implode(',', $runsIDs) . ") AND distanceID = %d AND firstName = %s AND surname = %s AND city = %s ORDER BY position ASC", $distance->distanceID, $result->firstName, $result->surname, $result->city));
                if (!empty($runResults)) {
                    $runResultsTemp = [];
                    foreach ($runResults as &$runResult) {
                        // Find the run name
                        $runName = array_filter($runs, function ($r) use ($runResult) {
                            return $r->runID === $runResult->runID;
                        });
                        if (!empty($runName)) {
                            $runName = array_shift($runName);
                            $runResult->runName = $runName->name;
                        } else {
                            $runResult->runName = 'Unknown Run';
                        }
                        $runResult->runName = $runName->name . ' - ' . $distance->name;
                        $runResultsTemp[] = (int) $runResult->cupPoints;
                        unset($runResult->runID);
                    }
                    // Sort the results by cup points in descending order
                    usort($runResultsTemp, function ($a, $b) {
                        return $b <=> $a;
                    });
                    $i = 0;
                    foreach ($runResultsTemp as $runResultTemp) {
                        if ($i > 3) {
                            break;
                        }
                        $distanceResults['results'][$key]->cupPoints += $runResultTemp;
                        $i++;
                    }
                    $distanceResults['results'][$key]->runResults = $runResults;
                }
            }

            // Sort the results by cup points in descending order
            usort($distanceResults['results'], function ($a, $b) {
                return $b->cupPoints <=> $a->cupPoints;
            });

            $results[] = $distanceResults;
        }

        unset($distanceResults);
    }

    return $results;
}

function get_xrun_current_run($data)
{
    global $wpdb;

    $date = date('Ymd');

    $runDates = $wpdb->get_results($wpdb->prepare("SELECT * FROM rnx_postmeta WHERE meta_key = 'data' AND meta_value > '" . $date . "' ORDER BY meta_value ASC LIMIT 1"));

    $startingList = null;

    if (is_array($runDates) && count($runDates) > 0) {
        $postID = $runDates[0]->post_id;

        // Get the runID from the list of runs
        $runID = $wpdb->get_var($wpdb->prepare("SELECT runID FROM rnx_starting_runs WHERE productID = '" . $postID . "'"));
        // Get starting list for the given run from the database
        $startingList = $wpdb->get_results($wpdb->prepare("SELECT list.*, runs.name AS run, dist.name AS distance FROM rnx_starting_list AS list LEFT JOIN rnx_starting_runs AS runs ON runs.runID = list.runID LEFT JOIN rnx_starting_distances AS dist ON dist.distanceID = list.distanceID WHERE list.runID = '" . $runID . "' ORDER BY list.orderNumber ASC"));
    }

    if (empty($startingList)) {
        $startingList = [];
    }

    return $startingList;
}

/**
 * End API
 */

// Remove the existing action
function remove_astra_woocommerce_before_main_content()
{
    // Ensure the class is loaded before trying to remove the action
    if (class_exists('Astra_WooCommerce')) {
        remove_action('woocommerce_before_main_content', array(Astra_WooCommerce::get_instance(), 'before_main_content_start'));
    }
}
add_action('init', 'remove_astra_woocommerce_before_main_content');

remove_filter('woocommerce_get_cart_url', 'astra_woocommerce_get_cart_url');

// Override the cart URL
function custom_wc_get_cart_url()
{
    return wpml_url_by_slug('zamowienie', 'page'); // Replace with your custom cart URL
}
add_filter('woocommerce_add_to_cart_redirect', 'custom_wc_get_cart_url', 100);

// Optionally, you can also override the cart URL in other places
add_filter('woocommerce_get_cart_url', 'custom_wc_get_cart_url', 100);

/**
 * Function to remove change prices
 */
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

    // Query for all products
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1, // Retrieve all products
    );

    // Set as a draft product if the expiration date is earlier than the current date
    // Create a new WP_Query instance
    $query = new WP_Query($args);

    // Check if there are any products to display
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $productID = get_the_ID();
            $date = get_field('data', $productID);

            if (strlen($date) > 0) {
                // Convert dd/mm/YYYY to timestamp
                list($day, $month, $year) = explode('/', $date);
                $formattedDate = "$year-$month-$day";
                $dateTime = new DateTime($formattedDate);
                $dateTime->modify("-2 days");
                $currentDateTime = new DateTime();

                // Check if the expiration date is earlier than the current date
                if ($currentDateTime > $dateTime) {
                    // Update the product status to 'draft' to disable visibility
                    wp_update_post(array(
                        'ID' => $productID,
                        'post_status' => 'draft',
                    ));
                }
            }
        }
    }

    // Restore original post data
    wp_reset_postdata();
}
add_action('custom_cron_event', 'custom_cron_event_callback');

// Add possibility
add_filter('acf/settings/remove_wp_meta_box', '__return_false');

function wpml_url_by_slug(string $slug, string $post_type): ?string
{
    $current_lang = apply_filters('wpml_current_language', null);

    // 2) Znajdź obiekt po slugu/ścieżce w JEGO języku
    $post = get_page_by_path($slug, OBJECT, $post_type);

    if (!$post instanceof WP_Post) {
        return null; // nic nie znaleziono po podanym slugu
    }

    // 3) Zmapuj ID na tłumaczenie w języku docelowym
    $translated_id = apply_filters('wpml_object_id', $post->ID, $post_type, false, $current_lang);
    if (!$translated_id) {
        return null; // brak tłumaczenia
    }

    // 4) Pobierz docelowy permalink
    $url = get_permalink($translated_id);

    // 5) (opcjonalnie) Upewnij się co do prefiksów domen/katalogów
    //    Jeśli chcesz „przełożyć” już gotowy URL wg reguł WPML (domeny/katalogi/parametr), użyj:
    // $url = apply_filters( 'wpml_permalink', $url, $target_lang );

    return $url ?: null;
}

// Remove link for specific post ID
// add_filter( 'post_link', function( $url, $post ) {
//     if ( $post->ID == 5325 ) {
//         return ''; // brak linku
//     }
//     return $url;
// }, 10, 2 );

/**
 * Wyłączenie direct checkout i powrót do standardowego działania WooCommerce
 * dla wybranych kategorii (wraz z podkategoriami).
 */

add_filter('woocommerce_add_to_cart_redirect', function ($url) {

    // Kategorie działające standardowo (slug-i)
    $normal_cats = array('dodatki', 'Dodatki', 'koszulki', 'Koszulki', 'bluzy', 'Bluzy', 'akcesoria', 'Akcesoria'); // <-- wstaw swoje kategorie, możesz dodać więcej

    // Sprawdzenie produktów w koszyku
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (has_term($normal_cats, 'product_cat', $cart_item['product_id'])) {
            return wc_get_cart_url(); // standardowy koszyk zamiast direct checkout
        }
    }

    return $url; // dla innych produktów direct checkout zostaje
});

add_action('template_redirect', function () {
    if (is_product_category('dodatki') or is_product_category('koszulki') or is_product_category('bluzy') or is_product_category('akcesoria')) {
        // ID strony zbudowanej w edytorze bloków
        $page_id = 7611; // <-- wstaw ID swojej strony blokowej Getwid
        // $page_id = 3913;

        // Zamień layout kategorii na zawartość strony blokowej
        add_action('woocommerce_before_main_content', function () use ($page_id) {
            echo '<div class="custom-gutenberg-category">';
            echo apply_filters('the_content', get_post($page_id)->post_content);
            echo '</div>';

            // Ukryj standardową listę produktów WooCommerce
            remove_all_actions('woocommerce_before_shop_loop');
            remove_all_actions('woocommerce_after_shop_loop');
        }, 1);

        // usuń standardowy loop produktów
        remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
    }
});

// ZAMIANA layoutu single product na blokową stronę (dla kategorii "dodatki")
add_action('template_redirect', function () {
    if (is_product()) {
        global $post;
        if (has_term('dodatki', 'product_cat', $post) or has_term('koszulki', 'product_cat', $post) or has_term('bluzy', 'product_cat', $post) or has_term('akcesoria', 'product_cat', $post)) {

            // ID Twojej strony-blokowego szablonu
            $page_id = 7613; // <-- PODSTAW ID
            // $page_id = 4021;

            remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);

            // 2) Wypchnij blokową stronę przed treścią produktu
            add_action('woocommerce_before_single_product', function () use ($page_id) {
                $page = get_post($page_id);
                if ($page && $page->post_status === 'publish') {
                    echo '<div class="single-dodatki-block-template">';
                    // render bloków (Gutenberg + Getwid)
                    echo do_shortcode(apply_filters('the_content', $page->post_content));
                    echo '</div>';
                }
            }, 1);
        } elseif (has_term('bieg', 'product_cat', $post)) {
            remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
        }
    }
});


// === SHORTCODES DLA BIEŻĄCEGO PRODUKTU ===

// 1) Pełna galeria WooCommerce (z zoom/lightbox jak w motywie)
add_shortcode('wc_current_product_gallery', function ($atts = []) {
    if (!function_exists('woocommerce_show_product_images')) {
        return '';
    }

    global $product;
    if (!$product || !is_product()) {
        return '';
    }

    ob_start();
    // To jest dokładnie to, co wyświetla galerię na single
    echo ' || woocommerce_show_product_images';
    woocommerce_show_product_images();
    echo ' || /woocommerce_show_product_images';
    return ob_get_clean();
});

// Shortcode: [wc_product_native_gallery]
add_shortcode('wc_product_native_gallery', function () {
    if (! is_product()) return '';

    ob_start();
    wc_get_template('single-product/product-thumbnails.php');
    return ob_get_clean();
});

// Shortcode: [wc_product_full_gallery] — duże zdjęcie + miniatury
add_shortcode('wc_product_full_gallery', function () {
    if (! is_product()) return '';

    ob_start();
    woocommerce_show_product_images();
    return ob_get_clean();
});

// 2) Pojedynczy obrazek
add_shortcode('wc_current_product_image', function ($atts = []) {
    global $product;
    if (!$product || !is_product()) {
        return '';
    }

    $atts = shortcode_atts([
        'size' => 'large', // thumbnail, medium, large, full lub własny rozmiar
        'class' => 'wc-current-product-image',
    ], $atts, 'wc_current_product_image');

    $html = $product->get_image($atts['size'], ['class' => esc_attr($atts['class'])]);
    return $html ? $html : '';
});

// 3) Cena (HTML WooCommerce)
add_shortcode('wc_current_product_price', function () {
    global $product;
    if (!$product || !is_product()) {
        return '';
    }

    return '<div class="wc-current-product-price">' . $product->get_price_html() . '</div>';
});

// 4) Krótki opis
add_shortcode('wc_current_product_excerpt', function () {
    global $product;
    if (!$product || !is_product()) {
        return '';
    }

    $short = $product->get_short_description();
    return $short ? wpautop($short) : '';
});

// 5) Pełny opis
add_shortcode('wc_current_product_description', function () {
    global $product;
    if (!$product || !is_product()) {
        return '';
    }

    $desc = $product->get_description();
    return $desc ? apply_filters('the_content', $desc) : '';
});

// 6) Formularz „Dodaj do koszyka” (obsługuje proste, zmienne itd.)
add_shortcode('wc_current_product_add_to_cart', function ($atts = []) {
    global $product;
    if (!$product || !is_product()) {
        return '';
    }

    // Możesz opcjonalnie dodać wrapper/klasę z atrybutu:
    $atts = shortcode_atts([
        'class' => 'wc-current-product-add-to-cart',
    ], $atts, 'wc_current_product_add_to_cart');

    ob_start();
    echo '<div class="' . esc_attr($atts['class']) . '">';
    // To jest ten sam fragment, który Woo wyświetla na single:
    woocommerce_template_single_add_to_cart();
    echo '</div>';
    return ob_get_clean();
});

// Shortcode: [wc_current_product_title]
add_shortcode('wc_current_product_title', function () {
    if (!is_product()) {
        return '';
    }

    global $product;
    if (!$product) {
        return '';
    }

    return '<h1 class="wc-product-title">' . esc_html($product->get_name()) . '</h1>';
});

add_filter('woocommerce_email_recipient_new_order', 'custom_new_order_email_recipient_by_category', 10, 2);
function custom_new_order_email_recipient_by_category($recipient, $order)
{

    if (! $order instanceof WC_Order) return $recipient;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        if (has_term('koszulki', 'product_cat', $product_id) or has_term('bluzy', 'product_cat', $product_id) or has_term('akcesoria', 'product_cat', $product_id) or has_term('dodatki', 'product_cat', $product_id)) {

            // DODATKOWY ODBIORCA
            $recipient .= ', sklep@xrun.pl';

            // Jeśli chcesz ZAMIAST standardowego adresu → odkomentuj:
            // $recipient = 'magazyn@firma.pl';

            break;
        }
    }

    return $recipient;
}

/**
 * Sprawdź czy WSZYSTKIE produkty w koszyku należą do podanej kategorii.
 *
 * @param string $category_slug Slug kategorii produktowej WooCommerce.
 * @return bool
 */
function xrun_cart_has_only_category(string $category_slug): bool
{
    if (! WC()->cart) {
        return false;
    }
    $cart_items = WC()->cart->get_cart();
    if (empty($cart_items)) {
        return false;
    }
    foreach ($cart_items as $cart_item) {
        if (! has_term($category_slug, 'product_cat', (int) $cart_item['product_id'])) {
            return false;
        }
    }
    return true;
}

/**
 * Usuń krok "Shipping" z Multi-Step Checkout (SilkyPress) gdy koszyk zawiera
 * wyłącznie produkty z kategorii 'bieg' – adres dostawy nie jest wtedy potrzebny.
 */
add_filter('wpmc_modify_steps', 'xrun_hide_shipping_step_for_bieg');
function xrun_hide_shipping_step_for_bieg(array $steps): array
{
    if (! xrun_cart_has_only_category('bieg')) {
        return $steps;
    }

    // Usuń oddzielny krok "Shipping"
    unset($steps['shipping']);

    // Usuń sekcję 'shipping' z kroku "Billing", gdy opcja unite_billing_shipping jest włączona
    if (isset($steps['billing']['sections'])) {
        $steps['billing']['sections'] = array_values(
            array_diff($steps['billing']['sections'], array('shipping'))
        );
    }

    return $steps;
}

/**
 * Poinformuj WooCommerce, że adres dostawy nie jest potrzebny gdy koszyk zawiera
 * wyłącznie produkty z kategorii 'bieg'. Ukrywa to checkbox "Wyślij na inny adres"
 * wewnątrz kroku Billing.
 */
add_filter('woocommerce_cart_needs_shipping_address', 'xrun_no_shipping_address_for_bieg');
function xrun_no_shipping_address_for_bieg(bool $needs_shipping): bool
{
    if (xrun_cart_has_only_category('bieg')) {
        return false;
    }
    return $needs_shipping;
}

/**
 * Wyłącz wybór metody wysyłki w koszyku i podsumowaniu zamówienia gdy
 * koszyk zawiera wyłącznie produkty z kategorii 'bieg'.
 */
add_filter('woocommerce_cart_needs_shipping', 'xrun_no_shipping_for_bieg');
function xrun_no_shipping_for_bieg(bool $needs_shipping): bool
{
    if (xrun_cart_has_only_category('bieg')) {
        return false;
    }
    return $needs_shipping;
}


// Add your custom action
function custom_woocommerce_before_main_content()
{
    // Wyświetl wszystkie kategorie aktualnego produktu
    $product_categories = wc_get_product_category_list(get_the_ID());

    $product = wc_get_product(get_the_ID());

    // Check if it's the specific product category page
    if (strpos($product_categories, 'Dodatki') !== false or strpos($product_categories, 'Koszulki') !== false or strpos($product_categories, 'Bluzy') !== false or strpos($product_categories, 'Akcesoria') !== false): ?>
        <div id="primary" class="content-area primary">
            <section class="ast-single-entry-banner" data-post-type="page" data-banner-layout="layout-2">

                <div class="ast-container">
                    <h1 class="entry-title" itemprop="headline">Sklep z dodatkami</h1>
                    <div class="ast-breadcrumbs-wrapper">
                        <div class="ast-breadcrumbs-inner">
                            <nav role="navigation" aria-label="Okruszki" class="breadcrumb-trail breadcrumbs">
                                <div class="ast-breadcrumbs">
                                    <ul class="trail-items">
                                        <li class="trail-item trail-begin"><a href="/" rel="home"><span>Strona główna</span></a></li>
                                        <li class="trail-item trail-middle"><a href="/sklep-dodatki/"><span>Sklep z dodatkami</span></a></li>
                                        <li class="trail-item trail-end"><span><span><?php echo esc_html($product->get_name()); ?></span></span></li>
                                    </ul>
                                </div>
                            </nav>
                        </div>
                    </div>
                </div>
            </section>

            <?php astra_primary_content_top(); ?>

            <main id="main" class="site-main">
                <div class="ast-woocommerce-container">
                <?php else: ?>
                    <div id="primary" class="content-area primary">
                        <section class="ast-single-entry-banner" data-post-type="page" data-banner-layout="layout-2">
                            <div class="ast-container">
                                <h1 class="entry-title" itemprop="headline"><?php esc_html_e('ZAPISY', 'woocommerce'); ?></h1>
                                <div class="ast-breadcrumbs-wrapper">
                                    <div class="ast-breadcrumbs-inner">
                                        <nav role="navigation" aria-label="Breadcrumbs" class="breadcrumb-trail breadcrumbs">
                                            <div class="ast-breadcrumbs">
                                                <ul class="trail-items">
                                                    <li class="trail-item trail-begin"><a href="/" rel="home"><span><?php esc_html_e('Home', 'woocommerce'); ?></span></a></li>
                                                    <li class="trail-item trail-end"><span><span><?php esc_html_e('ZAPISY', 'woocommerce'); ?></span></span></li>
                                                </ul>
                                            </div>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <?php astra_primary_content_top(); ?>

                        <main id="main" class="site-main">
                            <div class="ast-woocommerce-container">
                                <div class="checkout-steps">
                                    <a class="checkout-step" href="<?php echo wpml_url_by_slug('zapisy', 'page'); ?>">
                                        <button>1</button>
                                        <span><?php esc_html_e('wybierz bieg', 'woocommerce'); ?></span>
                                    </a>
                                    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
                                    <div class="checkout-step active">
                                        <button>2</button>
                                        <span><?php esc_html_e('wybierz dystans', 'woocommerce'); ?></span>
                                    </div>
                                    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
                                    <a class="checkout-step" href="<?php echo wpml_url_by_slug('zamowienie', 'page'); ?>">
                                        <button>3</button>
                                        <span><?php esc_html_e('dane zawodnika', 'woocommerce'); ?></span>
                                    </a>
                                    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
                                    <div class="checkout-step">
                                        <button>4</button>
                                        <span><?php esc_html_e('podsumowanie', 'woocommerce'); ?></span>
                                    </div>
                                </div>

                                <div class="wp-block-uagb-advanced-heading uagb-block-0bedd072">
                                    <h1 class="uagb-heading-text"><?php esc_html_e('wybierz dystans', 'woocommerce'); ?></h1>
                                </div>
                        <?php endif;
                }
                add_action('woocommerce_before_main_content', 'custom_woocommerce_before_main_content');
