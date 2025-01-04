<?php
/**
 * The steps tabs
 *
 * @package WPMultiStepCheckout
 */

defined('ABSPATH') || exit;

$i = 0;
// AstoSoft
$number_of_steps = ($show_login_step) ? count($steps) + 1 + 2 : count($steps) + 2;
$current_step_title = ($show_login_step) ? 'login' : key(array_slice($steps, 0, 1, true));

do_action('wpmc_before_tabs');

// AstoSoft - start
?>

<!-- The steps tabs -->
<div class="checkout-steps">
    <a class="checkout-step" href="/zapisy/">
        <button>1</button>
        <span>wybierz bieg</span>
    </a>
    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
    <div class="checkout-step">
        <button>2</button>
        <span>wybierz dystans</span>
    </div>
    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
    <a class="checkout-step active" href="/zamowienie/" id="checkout-step-3">
        <button>3</button>
        <span>dane zawodnika</span>
    </a>
    <img src="/wp-content/uploads/2024/11/checkout-separator.png" />
    <div class="checkout-step" id="checkout-step-4">
        <button>4</button>
        <span>podsumowanie</span>
    </div>
</div>
<div class="wp-block-uagb-advanced-heading uagb-block-0bedd072"><h1 class="uagb-heading-text" id="checkout-step-header">dane zawodnika</h1></div>
<?php
// AstoSoft - end
?>
