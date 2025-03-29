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
 * Version:           0.0.3
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

function as_runs_validation_import_callback($data)
{
    global $wpdb;

    $runID = (int) $data['run'];
    $distanceID = (int) $data['distance'];

    // Get the run and distance name
    $pmquery = "SELECT `name` FROM `" . $wpdb->base_prefix . "starting_runs` WHERE `runID` = '$runID'";
    $runName = $wpdb->get_var($pmquery);

    $pmquery = "SELECT `name` FROM `" . $wpdb->base_prefix . "starting_distances` WHERE `distanceID` = '$distanceID'";
    $distanceName = $wpdb->get_var($pmquery);

    // Get the file content
    if (!isset($_FILES['resultsFile'])) {
        echo 'Brak pliku do importu';
        exit();
    }

    $file = $_FILES['resultsFile']['tmp_name'];

    if (!file_exists($file)) {
        echo 'Plik nie istnieje';
        exit();
    }

    $fileContent = file_get_contents($file);
    $fileContent = iconv('windows-1250', 'utf-8', $fileContent);
    $fileContent = str_replace("\r", '', $fileContent);
    $fileContent = explode("\n", $fileContent);

    $categoryPositions = [];

    if ($distanceName == 'Kids') {
        $category = $data['category'];
        $sex = $data['sex'];
        // Clear the table for the run and distance
        $table = $wpdb->base_prefix . 'starting_results';
        $wpdb->delete($table, ['runID' => $runID, 'distanceID' => $distanceID, 'category' => $category, 'sex' => $sex], ['%d', '%d', '%s', '%s']);
    } else {
        // Clear the table for the run and distance
        $table = $wpdb->base_prefix . 'starting_results';
        $wpdb->delete($table, ['runID' => $runID, 'distanceID' => $distanceID], ['%d', '%d']);
    }

    // Insert the new data
    $i = 0;
    foreach ($fileContent as $line) {
        if ($i == 0) {
            $i++;
            continue;
        }

        $line = explode(',', $line);
        $position = (int) $line[0];
        $time = $line[1];

        // Convert time to format HH:MM:SS and make sure that it is not empty
        if (strpos($time, ':') === false) {
            $time = gmdate('H:i:s', $time);
        } else {
            $time = gmdate('H:i:s', strtotime($time));
        }

        $surname = $line[2];
        $firstName = $line[3];

        $birthday = $line[5];

        if ($distanceName == 'Kids') {
            if (array_key_exists($category . ' - ' . $sex, $categoryPositions) == false) {
                $categoryPositions[$category . ' - ' . $sex] = 1;
            } else {
                $categoryPositions[$category . ' - ' . $sex] = $categoryPositions[$category . ' - ' . $sex] + 1;
            }
            $categoryPosition = $categoryPositions[$category . ' - ' . $sex];
        } else {
            // Convert birthday to category
            $category = getRunCategory($birthday, $sex, $distanceName);

            $sex = $line[4];
            if ($sex == 'M') {
                $sex = 'mezczyzna';
            } else {
                $sex = 'kobieta';
            }

            if (array_key_exists($category, $categoryPositions) == false) {
                $categoryPositions[$category] = 1;
            } else {
                $categoryPositions[$category] = $categoryPositions[$category] + 1;
            }
            $categoryPosition = $categoryPositions[$category];
        }

        //$nationality = $line[6];
        $startingNumber = $line[7];
        //$city = $line[8];
        $club = $line[9];

        $insertData = [
            'runID' => $runID,
            'distanceID' => $distanceID,
            'position' => $position,
            'categoryPosition' => $categoryPosition,
            'time' => $time,
            'surname' => $surname,
            'firstName' => $firstName,
            'sex' => $sex,
            'category' => $category,
            'startingNumber' => $startingNumber,
            'club' => $club,
        ];

        $format = ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s'];
        $wpdb->insert($table, $insertData, $format);
        $i++;
    }

    return ['message' => 'Import zakończony pomyślnie'];
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

function as_runs_import_settings_cb()
{
    global $wpdb;

    $pmquery = "SELECT `runID`, `name` FROM `" . $wpdb->base_prefix . "starting_runs`";
    $runs = $wpdb->get_results($pmquery);

?>
    <select name="option_import_field_name[run]">
        <option value=""></option>
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
    <select name="option_import_field_name[distance]">
        <option value=""></option>
        <?php

        $i = 1;
        foreach ($distances as $distance) {
            echo '<option value="' . $distance->distanceID . '">' . $distance->name . '</option>';
            $i++;
        }
        ?>
    </select>
    <select name="option_import_field_name[category]">
        <option value=""></option>
        <option value="3 - 5 LAT">3 - 5 LAT</option>
        <option value="6 - 9 LAT">6 - 9 LAT</option>
        <option value="10 - 12 LAT">10 - 12 LAT</option>
        <option value="13 - 15 LAT">13 - 15 LAT</option>
    </select>
    <select name="option_import_field_name[sex]">
        <option value=""></option>
        <option value="mezczyzna">Mężczyzna</option>
        <option value="kobieta">Kobieta</option>
    </select>
    <input type="file" name="resultsFile" id="resultsFile">
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
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('as_runs_options_group'); ?>
            <?php do_settings_sections('as_runs_page_html'); ?>
            <?php submit_button('Eksport listy startowej'); ?>
        </form>
    </div>
<?php
}

function as_runs_import_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if the form was sent
    $formSent = false;
    if (isset($_POST['option_import_field_name'])) {
        $formSent = true;
    }
?>
    <div class="wrap">
        <h1>Import wyników</h1>
        <?php if ($formSent): ?>
            <div class="notice notice-success is-dismissible">
                <p>Import zakończony pomyślnie.</p>
            </div>
        <?php endif; ?>
        <form action="options.php" method="post" enctype="multipart/form-data">
            <?php settings_fields('as_runs_import_options_group'); ?>
            <?php do_settings_sections('as_runs_import_html'); ?>
            <?php submit_button('Import wyników'); ?>
        </form>
    </div>
<?php
}

function plugin_admin_init()
{
    register_setting('as_runs_options_group', 'option_field_name', 'as_runs_validation_callback');
    register_setting('as_runs_import_options_group', 'option_import_field_name', 'as_runs_validation_import_callback');
    add_settings_section('as_runs_main_id', 'Listy startowe', 'as_runs_main_settings_cb', 'as_runs_page_html');
    add_settings_section('as_runs_import_id', 'Import wyników', 'as_runs_import_settings_cb', 'as_runs_import_html');
}
add_action('admin_init', 'plugin_admin_init');

function as_runs_page()
{
    add_submenu_page(
        'tools.php',
        'Listy startowe',
        'Listy startowe',
        'manage_options',
        'as-runs-main',
        'as_runs_page_html'
    );

    add_submenu_page(
        'tools.php',
        'Import wyników',
        'Import wyników',
        'manage_options',
        'as-runs-import',
        'as_runs_import_html'
    );
}
add_action('admin_menu', 'as_runs_page');

function getRunCategory($birthDate, $sex, $distance)
{
    // Get year from birthDate
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    $category = '';

    if ($distance == 'Kids') {
        if ($age >= 3 && $age <= 5) {
            $category = '3 - 5 LAT';
        } else if ($age >= 6 && $age <= 9) {
            $category = '6 - 9 LAT';
        } else if ($age >= 10 && $age <= 12) {
            $category = '10 - 12 LAT';
        } else if ($age >= 13 && $age <= 15) {
            $category = '13 - 15 LAT';
        }
        return $category;
    }

    if ($sex == 'mezczyzna') {
        $category = 'M';
    } else {
        $category = 'K';
    }

    if ($age < 30) {
        $category .= '20';
    } else if ($age < 40) {
        $category .= '30';
    } else if ($age < 50) {
        $category .= '40';
    } else if ($age < 60) {
        $category .= '50';
    } else if ($age < 70) {
        $category .= '60';
    } else {
        $category .= '60+';
    }

    return $category;
}
