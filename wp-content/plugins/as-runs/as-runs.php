<?php
/**
 * WL Import Users
 *
 * @package           ASRuns
 * @author            AstoSoft
 *
 * @wordpress-plugin
 * Plugin Name:       X-run Runs
 * Plugin URI:        https://astosoft.pl
 * Description:       Simple plugin to export players data for runs.
 * Version:           0.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            AstoSoft
 * Author URI:        https://astosoft.pl
 * Text Domain:       as-runs
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

function as_runs_validation_callback($data)
{
    global $wpdb;

    $runID = (int) $data['run'];
    $distanceID = (int) $data['distance'];

    // Get the run and distance name
    $pmquery = "SELECT `name` FROM `" . $wpdb->base_prefix . "starting_runs` WHERE `runID` = '$runID'";
    $runName = $wpdb->get_var($pmquery);

    $pmquery = "SELECT `name` FROM `" . $wpdb->base_prefix . "starting_distances` WHERE `distanceID` = '$distanceID'";
    $distanceName = $wpdb->get_var($pmquery);
    // Prepare the alias from the run name
    $runAlias = strtolower(str_replace(' ', '-', $runName));
    $distanceAlias = strtolower(str_replace(' ', '-', $distanceName));

    // Get players for the run and distance
    $pmquery = "SELECT * FROM `" . $wpdb->base_prefix . "starting_list` WHERE `runID` = '$runID' and `distanceID` = '$distanceID'";
    $players = $wpdb->get_results($pmquery);

    // Prepare CSV file
    $filename = get_home_path() . 'wp-content/uploads/listy/listy-startowe-' . $runAlias . '-' . $distanceAlias . '-' . date('Y-m-d') . '.csv';
    $filenameToDisplay = 'listy-startowe-' . $runAlias . '-' . $distanceAlias . '-' . date('Y-m-d') . '.csv';
    $fp = fopen($filename, 'w');
    fputcsv($fp, ['Numer zamówienia', 'Imię', 'Nazwisko', 'Adres', 'Miejscowość', 'Kod pocztowy', 'Płeć', 'Kraj', 'Data urodzenia', 'Klub', 'Numer telefonu alarmowego', 'Numer telefonu', 'Email', 'Status płatności', 'Posiłek'], ';');

    foreach ($players as $player) {
        $orderID = (int) $player->orderNumber;

        if ($player->paymentStatus == 'tak') {
            $status = 'Opłacone';
        } else {
            $status = 'Nieopłacone';
        }

        fputcsv($fp, [$orderID, $player->firstName, $player->surname, $player->address, $player->city, $player->postCode, $player->sex, $player->country, $player->birthDate, $player->club, $player->alarmPhone, $player->phone, $player->email, $status, $player->meal], ';');
    }

    fclose($fp);

    // Send CSV file to the browser
    header('Content-Type: application/csv;charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameToDisplay . '"');
    readfile($filename);
    exit;
}

function as_runs_main_settings_cb()
{
    global $wpdb;

    $pmquery = "SELECT `runID`, `name` FROM `" . $wpdb->base_prefix . "starting_runs`";
    $runs = $wpdb->get_results($pmquery);

    ?>
    <select name="option_field_name[run]">
      <?php

    $i = 1;
    foreach ($runs as $run) {
        echo '<option value="' . $run->runID . '">' . $run->name . '</option>';
        $i++;
    }
    ?>
    </select>
    <?php

    $pmquery = "SELECT `distanceID`, `name` FROM `" . $wpdb->base_prefix . "starting_distances`";
    $distances = $wpdb->get_results($pmquery);
    ?>
    <select name="option_field_name[distance]">
      <?php

    $i = 1;
    foreach ($distances as $distance) {
        echo '<option value="' . $distance->distanceID . '">' . $distance->name . '</option>';
        $i++;
    }
    ?>
    </select>
    <?php
}

function as_runs_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
      <h1><?=esc_html(get_admin_page_title());?></h1>
      <form action="options.php" method="post">
        <?php settings_fields('as_runs_options_group');?>
        <?php do_settings_sections('as_runs_page_html');?>
        <?php submit_button('Eksport listy startowej');?>
      </form>
    </div>
    <?php
}

function plugin_admin_init()
{
    register_setting('as_runs_options_group', 'option_field_name', 'as_runs_validation_callback');
    add_settings_section('as_runs_main_id', 'Listy startowe', 'as_runs_main_settings_cb', 'as_runs_page_html');
}
add_action('admin_init', 'plugin_admin_init');

function as_runs_page()
{
    add_submenu_page(
        'tools.php',
        'Listy startowe',
        'Listy startowe',
        'manage_options',
        'as-runs',
        'as_runs_page_html'
    );
}
add_action('admin_menu', 'as_runs_page');
