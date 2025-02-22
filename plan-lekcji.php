<?php
/*
Plugin Name: Plan Lekcji
Description: Tworzy plan lekcji na na podstawie plików html i umożliwia wyświetlanie planu jako shortcode.
Version: 2.4
Author: Damian Wałach
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/



// Custom sanitization function for the settings
function sanitize_plan_lekcji_options($input) {
    // Handle sanitization for boolean values (checkboxes)
    if (is_bool($input)) {
        return (bool)$input; // Convert to boolean
    }
    
    // For text fields, sanitize text input
    if (is_string($input)) {
        return sanitize_text_field($input);
    }

    return $input; // Default return if no other conditions apply
}

// Rejestracja ustawienia dla wyłączenia planu
function plan_lekcji_register_settings() {
    // Rejestracja ustawienia daty obowiązywania planu
    register_setting('plan_lekcji_options_group', 'data_obowiazywania_option', 'sanitize_text_field');
    register_setting('plan_lekcji_options_group', 'data_obowiazywania_test_plan', 'sanitize_text_field');
    register_setting('plan_lekcji_options_group', 'plan_disabled_option', 'sanitize_plan_lekcji_options');  // Sanitization for boolean option
    register_setting('plan_lekcji_options_group', 'show_plan_for_logged_in_option', 'sanitize_plan_lekcji_options'); // Sanitization for boolean option
    register_setting('plan_lekcji_options_group', 'enable_test_plan_option', 'sanitize_plan_lekcji_options'); // Sanitization for boolean option
    register_setting('plan_lekcji_options_group', 'only_test_plan_option', 'sanitize_plan_lekcji_options'); // Sanitization for boolean option

    // Dodanie pola daty obowiązywania planu do sekcji ustawień
    add_settings_section('plan_lekcji_main_section', 'Główne ustawienia planu lekcji', null, 'plan_lekcji');
    add_settings_field(
        'data_obowiazywania_option', 
        'Data obowiązywania planu lekcji (format: d-m-Y)', 
        'data_obowiazywania_option_field', 
        'plan_lekcji', 
        'plan_lekcji_main_section'
    );
    add_settings_field(
        'data_obowiazywania_test_plan', 
        'Data obowiązywania planu testowego (format: d-m-Y)', 
        'data_obowiazywania_test_plan_field', 
        'plan_lekcji', 
        'plan_lekcji_main_section'
    );
    add_settings_field(
        'plan_disabled_option', 
        'Wyłącz plan lekcji', 
        'plan_disabled_option_field', 
        'plan_lekcji', 
        'plan_lekcji_main_section'
    );
    add_settings_field(
        'show_plan_for_logged_in_option', 
        'Pokaż plan lekcji dla zalogowanych użytkowników, gdy plan jest wyłączony', 
        'show_plan_for_logged_in_option_field', 
        'plan_lekcji', 
        'plan_lekcji_main_section'
    );
    add_settings_field(
        'enable_test_plan_option', 
        'Włącz plan normalny i testowy (równocześnie)', 
        'enable_test_plan_option_field', 
        'plan_lekcji', 
        'plan_lekcji_main_section'
    );
    add_settings_field(
        'only_test_plan_option', 
        'Włącz tylko plan testowy (bez normalnego planu)', 
        'only_test_plan_option_field', 
        'plan_lekcji', 
        'plan_lekcji_main_section'
    );
}

// Function to display the checkbox for "Only Test Plan"
function only_test_plan_option_field() {
    $only_test_plan = get_option('only_test_plan_option', false);
    ?>
    <input type="checkbox" name="only_test_plan_option" value="1" <?php checked($only_test_plan, 1); ?> />
    <label for="only_test_plan_option">Wyłącz normalny plan i pokazuj tylko testowy</label>
    <?php
}

// Function to display the date field for "Data obowiązywania planu"
function data_obowiazywania_option_field() {
    $data = get_option('data_obowiazywania_option', gmdate('d-m-Y')); // Pobranie wartości opcji, domyślnie ustawiamy dzisiejszą datę
    ?>
    <input type="date" name="data_obowiazywania_option" value="<?php echo esc_attr($data); ?>" placeholder="d-m-Y" />
    <?php
}

// Funkcja wyświetlająca pole dla daty planu testowego
function data_obowiazywania_test_plan_field() {
    $data_test = get_option('data_obowiazywania_test_plan', gmdate('d-m-Y')); // Domyślna data to dzisiejsza
    ?>
    <input type="date" name="data_obowiazywania_test_plan" value="<?php echo esc_attr($data_test); ?>" />
    <?php
}


// Function to display the checkbox for "Enable Test Plan"
function enable_test_plan_option_field() {
    $enable_test_plan = get_option('enable_test_plan_option', false);
    ?>
    <input type="checkbox" name="enable_test_plan_option" value="1" <?php checked($enable_test_plan, 1); ?> />
    <label for="enable_test_plan_option">Włącz jednocześnie plan normalny i testowy</label>
    <?php
}

// Function to display the checkbox for "Show Plan for Logged-In Users"
function show_plan_for_logged_in_option_field() {
    $show_for_logged_in = get_option('show_plan_for_logged_in_option', false);
    ?>
    <input type="checkbox" name="show_plan_for_logged_in_option" value="1" <?php checked($show_for_logged_in, 1); ?> />
    <label for="show_plan_for_logged_in_option">Pokaż plan lekcji dla zalogowanych użytkowników, gdy plan jest wyłączony</label>
    <?php
}

// Function to display the checkbox for "Plan Disabled"
function plan_disabled_option_field() {
    $is_disabled = get_option('plan_disabled_option', false);
    ?>
    <input type="checkbox" name="plan_disabled_option" value="1" <?php checked($is_disabled, 1); ?> />
    <label for="plan_disabled_option">Wyłącz plan lekcji</label>
    <?php
}


if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

function upload_and_extract_zip() {
    if (isset($_FILES['normal_plan_lekcji_zip_file']) && current_user_can('manage_options')) {
        $uploaded_file = $_FILES['normal_plan_lekcji_zip_file'];

        // Akceptujemy oba typy MIME dla plików ZIP
        if ($uploaded_file['type'] === 'application/zip' || $uploaded_file['type'] === 'application/x-zip-compressed') {
            // Określenie ścieżki do katalogu, gdzie plik ZIP będzie rozpakowywany
            $upload_dir = wp_upload_dir()['basedir'] . '/timetable/timetable/';
            $zip_file_path = $upload_dir . basename($uploaded_file['name']);

            // Inicjalizacja WP_Filesystem
            WP_Filesystem(); // Inicjalizacja systemu plików WordPress

            // Pobranie instancji WP_Filesystem
            global $wp_filesystem;

            // Sprawdzenie, czy WP_Filesystem działa prawidłowo
            if ( ! $wp_filesystem ) {
                echo '<div class="error"><p>Nie udało się zainicjować systemu plików WordPress.</p></div>';
                return;
            }

            // Sprawdzamy, czy katalog docelowy istnieje, jeśli nie, tworzymy go
            if ( ! $wp_filesystem->is_dir($upload_dir) ) {
                if ( ! $wp_filesystem->mkdir($upload_dir, 0755) ) {
                    echo '<div class="error"><p>Nie udało się utworzyć katalogu: ' . esc_html($upload_dir) . '</p></div>';
                    return;
                }
            }

            // Usuwanie zawartości katalogu 'timetable' przed rozpakowaniem
            $files = $wp_filesystem->dirlist($upload_dir);
            foreach ($files as $file) {
                if ($file['type'] === 'file') {
                    $wp_filesystem->delete($upload_dir . $file['name']);
                }
            }

            // Use wp_handle_upload() to securely handle the upload
            $upload_overrides = array('test_form' => false); // Avoid form check
            $uploaded = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($uploaded && !isset($uploaded['error'])) {
                // Rozpakowanie pliku ZIP
                $zip = new ZipArchive();
                if ($zip->open($uploaded['file']) === TRUE) {
                    // Rozpakowanie pliku do katalogu 'timetable'
                    $zip->extractTo($upload_dir);
                    $zip->close();

                    // Opcjonalnie usuwamy plik ZIP po rozpakowaniu
                    wp_delete_file($uploaded['file']);  // Replaced unlink() with wp_delete_file()

                    echo '<div class="updated"><p>Plik ZIP został pomyślnie przesłany i rozpakowany do katalogu: ' . esc_html($upload_dir) . '</p></div>';
                } else {
                    echo '<div class="error"><p>Nie udało się otworzyć pliku ZIP.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Wystąpił problem podczas przesyłania pliku: ' . esc_html($uploaded['error']) . '</p></div>';
            }
        } else {
            echo '<div class="error"><p>Proszę przesłać plik ZIP.</p></div>';
        }
    }
}





if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

function upload_and_extract_test_zip() {
    if (isset($_FILES['test_plan_lekcji_zip_file']) && current_user_can('manage_options')) {
        $uploaded_file = $_FILES['test_plan_lekcji_zip_file'];

        // Akceptujemy oba typy MIME dla plików ZIP
        if ($uploaded_file['type'] === 'application/zip' || $uploaded_file['type'] === 'application/x-zip-compressed') {
            // Określenie ścieżki do katalogu, gdzie plik ZIP będzie rozpakowywany
            $upload_dir = wp_upload_dir()['basedir'] . '/timetable/timetable_test/';
            $zip_file_path = $upload_dir . basename($uploaded_file['name']);

            // Inicjalizacja WP_Filesystem
            WP_Filesystem(); // Inicjalizacja systemu plików WordPress

            // Pobranie instancji WP_Filesystem
            global $wp_filesystem;

            // Sprawdzenie, czy WP_Filesystem działa prawidłowo
            if ( ! $wp_filesystem ) {
                echo '<div class="error"><p>Nie udało się zainicjować systemu plików WordPress.</p></div>';
                return;
            }

            // Sprawdzamy, czy katalog docelowy istnieje, jeśli nie, tworzymy go
            if ( ! $wp_filesystem->is_dir($upload_dir) ) {
                if ( ! $wp_filesystem->mkdir($upload_dir, 0755) ) {
                    echo '<div class="error"><p>Nie udało się utworzyć katalogu: ' . esc_html($upload_dir) . '</p></div>';
                    return;
                }
            }

            // Usuwanie zawartości katalogu 'timetable_test' przed rozpakowaniem
            $files = $wp_filesystem->dirlist($upload_dir);
            foreach ($files as $file) {
                if ($file['type'] === 'file') {
                    $wp_filesystem->delete($upload_dir . $file['name']);
                }
            }

            // Use wp_handle_upload() to securely handle the upload
            $upload_overrides = array('test_form' => false); // Avoid form check
            $uploaded = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($uploaded && !isset($uploaded['error'])) {
                // Rozpakowanie pliku ZIP
                $zip = new ZipArchive();
                if ($zip->open($uploaded['file']) === TRUE) {
                    // Rozpakowanie pliku do katalogu 'timetable'
                    $zip->extractTo($upload_dir);
                    $zip->close();

                    // Opcjonalnie usuwamy plik ZIP po rozpakowaniu
                    wp_delete_file($uploaded['file']);  // Replaced unlink() with wp_delete_file()

                    echo '<div class="updated"><p>Plik ZIP został pomyślnie przesłany i rozpakowany do katalogu: ' . esc_html($upload_dir) . '</p></div>';
                } else {
                    echo '<div class="error"><p>Nie udało się otworzyć pliku ZIP.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Wystąpił problem podczas przesyłania pliku: ' . esc_html($uploaded['error']) . '</p></div>';
            }
        } else {
            echo '<div class="error"><p>Proszę przesłać plik ZIP.</p></div>';
        }
    }
}




// Funkcja wyświetlająca stronę ustawień pluginu
function plan_lekcji_settings_page() {
    ?>
    <div class="wrap">
        <h1>Ustawienia Plan Lekcji</h1>
        <h2>Upload i Rozpakowywanie normalnego planu (ZIP)</h2>
        <form method="post" enctype="multipart/form-data">
            <?php upload_and_extract_zip(); // Funkcja przetwarzająca upload ?>
            <input type="file" name="normal_plan_lekcji_zip_file" accept=".zip" required />
            <input type="submit" value="Prześlij i Rozpakuj" class="button-primary" />
        </form>

        <h2>Upload i Rozpakowywanie testowego planu (ZIP)</h2>
        <form method="post" enctype="multipart/form-data">
            <?php upload_and_extract_test_zip(); // Funkcja przetwarzająca upload ?>
            <input type="file" name="test_plan_lekcji_zip_file" accept=".zip" required />
            <input type="submit" value="Prześlij i Rozpakuj" class="button-primary" />
        </form>


        <form method="post" action="options.php">
            <?php
            // Wykonanie akcji zapisywania ustawień
            settings_fields('plan_lekcji_options_group');
            do_settings_sections('plan_lekcji');
            submit_button();
            ?>
        </form>

    </div>
    <?php
}

// Dodanie strony ustawień do menu admina
function plan_lekcji_menu() {
    // Główne menu
    add_menu_page('Plan Lekcji', 'Plan Lekcji', 'manage_options', 'plan_lekcji', 'plan_lekcji_settings_page', 'dashicons-calendar', 100);

    // Pierwsze submenu - puste
    add_submenu_page('options-general.php', '', '', 'manage_options', 'plan_lekcji_submenu', null); 

    // Submenu w głównym menu "Ustawienia" - zawiera ustawienia
    add_submenu_page('plan_lekcji', 'Ustawienia', 'Ustawienia', 'manage_options', 'plan_lekcji', 'plan_lekcji_settings_page');
}

// Hook do dodania strony ustawień do menu administracyjnego
add_action('admin_menu', 'plan_lekcji_menu');

// Hook do rejestracji ustawień
add_action('admin_init', 'plan_lekcji_register_settings');

function sort_teachers($menu_contents) {
    // Ustaw lokalizację, aby sortowanie brało pod uwagę polskie znaki
    setlocale(LC_COLLATE, 'pl_PL.UTF-8');
    
    // Szukamy zawartości listy nauczycieli – zakładamy, że znajduje się ona w <ul> po nagłówku <h4>Nauczyciele</h4>
    if (!preg_match('/<h4>\s*Nauczyciele\s*<\/h4>\s*<ul>(.*?)<\/ul>/si', $menu_contents, $matches)) {
        return '<li>Brak nauczycieli</li>';
    }
    
    $ul_content = $matches[1];
    
    // Pobieramy wszystkie elementy <li> (całe bloki, w których zawarte są linki)
    if (!preg_match_all('/<li>(.*?)<\/li>/si', $ul_content, $li_matches)) {
        return '<li>Brak nauczycieli</li>';
    }
    
    $li_array = $li_matches[0];
    
    // Tworzymy tablicę, w której kluczem będzie nazwa nauczyciela (tekst wewnątrz <a>) a wartością – cały blok <li>
    $assoc = array();
    foreach ($li_array as $li) {
        if (preg_match('/<a[^>]*>(.*?)<\/a>/si', $li, $m)) {
            $teacher_name = trim($m[1]);
            $assoc[$teacher_name] = $li;
        }
    }
    
    // Pobieramy same nazwy nauczycieli
    $names = array_keys($assoc);
    
    // Funkcja porównująca nazwy nauczycieli
    usort($names, function($a, $b) {
        // Ekstrakcja liter po kropce
        $a_parts = explode('.', $a); // Podziel imię na części po kropce
        $b_parts = explode('.', $b); // Podziel imię na części po kropce

        // Porównanie każdej części nazwy
        $max_parts = max(count($a_parts), count($b_parts));
        for ($i = 1; $i < $max_parts; $i++) {
            // Sprawdzamy, czy istnieje ta część w obu nazwach
            $a_char = isset($a_parts[$i]) ? $a_parts[$i] : '';
            $b_char = isset($b_parts[$i]) ? $b_parts[$i] : '';
            
            // Jeśli części się różnią, porównujemy je
            if ($a_char !== $b_char) {
                return strcoll($a_char, $b_char);
            }
        }

        // Jeśli wszystkie części są równe, porównujemy pełną nazwę nauczyciela
        return strcoll($a, $b);
    });
    
    // Sklejamy posortowane bloki <li>
    $sorted_html = '';
    foreach ($names as $name) {
        $sorted_html .= $assoc[$name];
    }
    
    return $sorted_html;
}

function plan_lekcji_enqueue_scripts() {
    // Ścieżka do pliku skryptu
    $menu2_script_path = plugin_dir_path(__FILE__) . 'js/menu2.js';
    $print_table_script_path = plugin_dir_path(__FILE__) . 'js/printTableScript.js';

    // Zarejestruj skrypt menu2.js z wersją na podstawie daty modyfikacji pliku
    wp_enqueue_script('menu2-script', plugin_dir_url(__FILE__) . 'js/menu2.js', array('jquery'), filemtime($menu2_script_path), true);

    // Zarejestruj skrypt printTableScript.js z wersją na podstawie daty modyfikacji pliku
    wp_enqueue_script('printTableScript', plugin_dir_url(__FILE__) . 'js/printTableScript.js', array('jquery'), filemtime($print_table_script_path), true);
}
add_action('wp_enqueue_scripts', 'plan_lekcji_enqueue_scripts');



function plan_lekcji_shortcode($atts) {
    // Get the plugin options
    $is_disabled = get_option('plan_disabled_option', false);
    $show_for_logged_in = get_option('show_plan_for_logged_in_option', false);
    $enable_test_plan = get_option('enable_test_plan_option', false);
    $only_test_plan = get_option('only_test_plan_option', false); // New option
    $is_test_plan = isset($_GET['test']) && $_GET['test'] == '1';
	plan_lekcji_enqueue_scripts();

if ($only_test_plan && !isset($_GET['test'])) {
    wp_redirect(add_query_arg('test', '1', home_url($_SERVER['REQUEST_URI'])));
    exit;
}



    // Force the test plan mode if "only_test_plan_option" is enabled
    if ($only_test_plan) {
        $is_test_plan = true;
    }

    // If "only_test_plan_option" is enabled and no test plan is selected, redirect to test plan
    if ($only_test_plan && !$is_test_plan) {
        wp_redirect(add_query_arg('test', '1', home_url($_SERVER['REQUEST_URI'])));
        exit;
    }

    // Display a warning if it's the test plan
    $zmianna_test = '';
    if ($is_test_plan) {
        $zmianna_test = '<h5 style="font-size: 30px; color: red;">UWAGA - Testowy Plan lekcji</h5>';
    }

// Check if the timetable is disabled and if the test parameter is present
if ((!$only_test_plan && !$enable_test_plan) && $is_test_plan) {
    // Get the current URL and remove the 'test' parameter
    $current_url = $_SERVER['REQUEST_URI'];
    $parsed_url = wp_parse_url($current_url);
    parse_str($parsed_url['query'] ?? '', $query_params);
    unset($query_params['test']);  // Remove the 'test' parameter
    $new_url = $parsed_url['path'];  // Rebuild URL without 'test'

    return '
    <h3 style="padding-top: 50px; text-align:center; color:red;">Plan testowy jest wyłączony. Nie można wyświetlić planu lekcji.</h3>
    <form action="' . $new_url . '" method="get" style="text-align:center; padding-top:20px;">
        <button type="submit" style="border-radius: 10px; padding: 20px;">Powrót do normalnego planu</button>
    </form>';
}


    // Check if the timetable is disabled and the user is not logged in or cannot see the plan
    if ($is_disabled && (!is_user_logged_in() || !$show_for_logged_in)) {
        return '<!-- wp:heading {"textAlign":"center","level":3} -->
        <h3 class="wp-block-heading has-text-align-center has-theme-palette-1-color has-text-color has-link-color">Jeśli to zadziała, to nie pytaj, jak. Będziemy udawać, że wszystko jest w porządku. #programista</h3>        <!-- /wp:heading -->
        <figure class="wp-block-image size-large"><img src="/wp-content/plugins/plan-lekcji/mem.jpeg" alt=""/></figure>';
    }

    // Define the upload directories for standard and test plans
    $upload_dir = wp_upload_dir()['basedir'] . '/timetable/timetable/';
    $test_upload_dir = wp_upload_dir()['basedir'] . '/timetable/timetable_test/';

$is_test_plan = isset($_GET['test']) && $_GET['test'] == '1';

// Ustawienie ciasteczka planu
if (isset($_GET['plan'])) {
    $plan = sanitize_text_field($_GET['plan']);  // Pobieramy wartość planu z URL
    // Zmieniamy nazwę ciasteczka w zależności od trybu
    $cookie_name = $is_test_plan ? 'test_plan' : 'standard_plan';
    setcookie($cookie_name, $plan, time() + 30 * DAY_IN_SECONDS, '/');  // Zapisujemy plan do ciasteczka
    $_COOKIE[$cookie_name] = $plan;  // Ustawiamy ciasteczko w bieżącej sesji
} elseif (isset($_COOKIE['test_plan']) && $is_test_plan) {
    // Używamy ciasteczka dla planu testowego
    $plan = sanitize_text_field($_COOKIE['test_plan']);
} elseif (isset($_COOKIE['standard_plan']) && !$is_test_plan) {
    // Używamy ciasteczka dla planu standardowego
    $plan = sanitize_text_field($_COOKIE['standard_plan']);
} else {
    // Domyślny plan (np. 'o1')
    $plan = 'o1';
}

    // Choose the file path depending on whether it's a test plan or not
    $file_path = $is_test_plan ? $test_upload_dir . $plan . '.html' : $upload_dir . $plan . '.html';

    if (file_exists($file_path)) {
        // Process the timetable file contents
        $file_contents = file_get_contents($file_path);
        preg_match('/<span class="tytulnapis">(.*?)<\/span>/is', $file_contents, $title_matches);
        $teacher_title = isset($title_matches[1]) ? $title_matches[1] : $plan;

        preg_match('/<table[^>]*border="1" cellspacing="0" cellpadding="4"[^>]*class="tabela"[^>]*>(.*?)<\/table>/is', $file_contents, $matches);

        if (isset($matches[0])) {
            $table_content = $matches[0];

            // Update links in the timetable content
            $table_content = preg_replace_callback('/href="(.*?)"/', function ($matches) use ($plan, $is_test_plan) {
                if (preg_match('/([a-zA-Z0-9_-]+)\.html/', $matches[1], $url_matches)) {
                    $new_plan = $url_matches[1];
                    $new_url = '?plan=' . $new_plan;
                    if ($is_test_plan) {
                        $new_url .= '&test=1';
                    }
                    return 'href="' . $new_url . '" class="ajax-link"';
                }
                return $matches[0];
            }, $table_content);

            // Process menu links for timetable
            $menu_file_path = $is_test_plan ? $test_upload_dir . 'lista.html' : $upload_dir . 'lista.html';

            if (file_exists($menu_file_path)) {
                $menu_contents = file_get_contents($menu_file_path);
                $menu_contents = preg_replace_callback('/href="(.*?)"/', function ($matches) use ($plan, $is_test_plan) {
                    if (preg_match('/plany\/([a-zA-Z0-9_-]+)\.html/', $matches[1], $url_matches)) {
                        $new_plan = $url_matches[1];
                        return 'href="?plan=' . $new_plan . ($is_test_plan ? '&test=1' : '') . '"';
                    }
                    return $matches[0];
                }, $menu_contents);
		$sorted_nauczyciele = sort_teachers($menu_contents);

                preg_match('/<h4>Oddziały<\/h4>(.*?)<h4>Nauczyciele<\/h4>/is', $menu_contents, $oddzialy);
                preg_match('/<h4>Nauczyciele<\/h4>(.*?)<h4>Sale<\/h4>/is', $menu_contents, $nauczyciele);
                preg_match('/<h4>Sale<\/h4>(.*?)<\/body>/is', $menu_contents, $sale);

// Generate buttons for switching plans
$switch_button_html = '';
if ($enable_test_plan && !$only_test_plan) { // Hide if "only_test_plan_option" is active
    $active_test_style = $is_test_plan ? 'background-color: #1a4d80;' : 'background-color: #2b6cb0;';
    $active_standard_style = !$is_test_plan ? 'background-color: #1a4d80;' : 'background-color: #2b6cb0;';

    // Get the current URL and remove all query parameters for the standard plan
    $current_url = $_SERVER['REQUEST_URI'];
    $parsed_url = wp_parse_url($current_url);
    $new_url_standard = $parsed_url['path'];  // Remove query parameters

    $switch_button_html = '
    <div class="plan-selector" style="text-align: center; margin-bottom: 20px;">
        <a href="?test=1" style="border-radius: 10px; padding: 10px; ' . $active_test_style . ' color: white; text-decoration: none;">Plan Testowy</a> |
        <a href="' . $new_url_standard . '" style="border-radius: 10px; padding: 10px; ' . $active_standard_style . ' color: white; text-decoration: none;">Plan Standardowy</a>
    </div>';
}

$output = '<style>
    /*style planu lekcji*/
    .col, .col2, .col3 { display: none; }
    .zglos { text-align: right; }
    .aktualizacja { text-align: right; }
    .col, .col2, .col3 { display: block; }
    
    .tabela { 
        width: 100%; 
        overflow-x: auto; /* umożliwia przewijanie w poziomie */
        -webkit-overflow-scrolling: touch; /* dla lepszej responsywności na urządzeniach mobilnych */
    }

    table { 
        width: 100%; 
        border-collapse: collapse; 
    }

    th, td { 
        white-space: nowrap; /* zapobiega zawijaniu tekstu */
    }

    .tabela th, .tabela tr:first-child th { 
        background-color: var(--global-palette2); 
        color: #fff; 
    }

    /* Styl dla pierwszego wiersza */
    .tabela tr:first-child td {
        background-color: var(--global-palette2);
        color: #fff;
    }

    /* Styl dla pierwszej kolumny */
    .tabela td:first-child {
        background-color: var(--global-palette2);
        color: #fff;
    }

    /* Usuwanie tła z pustych komórek */
    .tabela td:empty, .tabela td:empty[style] {
        background-color: transparent !important;
        color: inherit !important;
    }

    /* Linki w tabeli - kolor */
    .tabela td a {
        color: var(--global-palette1);
    }

    /* Komórki z klasą "g" */
    .tabela td.g {
        background-color: var(--global-palette1);
        color: #fff;
    }

    /* Dodatkowe style */
    .col { 
        float: left; 
        width: 100%; 
        background: var(--global-palette2); 
        border: 3px solid gray; 
    }

    .col2 { 
        float: right; 
        width: 100%; 
        text-align: center; 
	overflow-x: auto;
    }

    .col3 { 
        float: none; 
        width: 100%; 
    }

    .menu2 { 
        min-width: 300px; 
    }

    .cale { 
        display: flex; 
        flex-wrap: nowrap; 
        align-items: flex-start; 
        flex-direction: row; /* Normalne ustawienie w poziomie */
    }

    .toggle-header { 
        color: #fff; 
        cursor: pointer; 
    margin: 15px 0 !important; /* Nadpisanie domyślnego margin */
    padding: 0 !important; /* Nadpisanie domyślnego padding */
    }

.toggle-list {
    opacity: 0; /* Ukrywa elementy */

    max-height: 0; /* Ukrywa menu, ustawiamy na 0 */
    overflow: hidden; /* Ukrywa zawartość poza max-height */
    padding: 0; /* Usuwa padding */
    position: relative; /* Ustalamy element w miejscu, nie zajmuje przestrzeni w układzie */
    transition: max-height 0.5s ease-in-out, opacity 5s ease-in-out; /* Animacja */
}

.toggle-list.show {
    opacity: 1; /* Pokazuje elementy */
    visibility: visible; /* Pokazuje elementy */
    max-height: 10000px; /* Ustawiamy max-height na dużą wartość, aby rozwinąć */
    padding: 0; /* Może pozostać brak paddingu */
    margin: 0; /* Usuwamy marginesy */
    position: relative; /* Przywraca normalną pozycję */
    transition: max-height 0.5s ease-in-out, opacity 0.5s ease-in-out; /* Animacja */
}

    .content-container.site-container { 
        width: 100%; 
        max-width: 100%; 
    }
ul {
    list-style-type: none !important;
    margin-right: 15px !important;
    margin-bottom: 0 !important; /* Nadpisanie domyślnego margin */
}
    a { 
        color: #fff; 
        text-decoration: none; 
    }

    a:hover { 
        color: #669cd7; 
    }


    /* Usuwanie kropek przed listą */
    ul.toggle-list {
        list-style-type: none;
        padding-left: 0;
	margin-right: 15px;
    }

    ul.toggle-list li {
        padding-left: 0;
    }

    /* Styl animacji ładowania */
    .loading {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @media screen and (max-width: 780px) {
        /* Ustawienia dla mniejszych ekranów */
        .tabela { 
            width: 100%;
            overflow-x: auto; /* zapewnia przewijanie poziome tabeli */
        }

        .menu2 { 
            width: 100%; 
        }

        .zglos, .aktualizacja { 
            text-align: center; 
        }

        .cale {
            flex-direction: column; /* Ustawienie w kolumnie na małych ekranach */
            align-items: center; 
        }
    }
</style>
';

// Wyświetlenie tytułu pobranego z pliku
$output .= '<h2 style="color: var(--global-palette2); margin-top:120px; font-size: 28px; text-align: center;">' . esc_html($teacher_title) . '</h2>';
$output .= $switch_button_html;

$output .= '<div id="loading" class="loading" style="display: none;"></div>';  // Dodanie animacji ładowania
                // Górny kontener z menu i planem lekcji w jednej linii
                $output .= '<div class="cale">';

                    // Lewa kolumna - menu
                    $output .= '<div class="menu2">';
                        $output .= '<div class="cont">';
                            $output .= '<div class="container">';
                                $output .= '<div class="col">';
                                    // Menu Oddziały
                                    $output .= '<h4  class="toggle-header oddzialy" style="text-align: center; margin-top: 15px">Oddziały</h4>';
                                    $output .= '<hr style="height: 5px; background: gray; margin: 0;">';
                                    $output .= '<ul id="oddzialy" class="toggle-list">' . $oddzialy[1] . '</ul>';

                                    // Menu Nauczyciele
                                    $output .= '<h4  class="toggle-header nauczyciele" style="text-align: center;">Nauczyciele</h4>';
                                    $output .= '<hr style="height: 5px; background: gray; margin: 0;">';
                                    $output .= '<ul id="nauczyciele" class="toggle-list"><ul>' . $sorted_nauczyciele . '</ul></ul>';

                                    // Menu Sale
                                    $output .= '<h4  class="toggle-header sale" style="text-align: center;">Sale</h4>';
                                    $output .= '<hr style="height: 5px; background: gray; margin: 0;">';
                                    $output .= '<ul id="sale" class="toggle-list">' . $sale[1] . '</ul>';
                                $output .= '</div>'; // .col
                            $output .= '</div>'; // .container
                        $output .= '</div>'; // .cont
                    $output .= '</div>'; // Lewa kolumna

                    // Środkowa kolumna - plan lekcji
                    $output .= '<div class="col2" style="flex: 1;">';
                        $output .= '<div class="tabela">';
                            $output .= '<div id="tabela-container">' . $table_content . '</div>';

                $output .= '</div>'; // koniec górnego kontenera flex

                // Sekcja col3 - dodatkowy blok, zawsze poniżej tabeli
                $output .= '<div class="col3" style="margin-top: 20px;">';
                    $output .= '<div style="text-align: center; margin-top: 5px;">';
                        $output .= $zmianna_test . '<h6 style="display: inline; font-size: 16px;">Plan obowiązuje od:</h6>';
                        $output .= '<h5 style="display: inline; margin-left: 5px; font-size: 18px;">' . do_shortcode('[data_obowiazywania_shortcode]') . ' r.</h5>';
                    $output .= '</div>';
                    $output .= '<div class="zglos">';
                        $output .= '<h4><a href="/plan-contact" style="color: red; text-decoration: none;">Zgłoś błąd w planie</a></h4>';
                    $output .= '</div>';
                    $output .= '<div class="aktualizacja"> Aktualizacja: ' . gmdate("d-m-Y H:i", filemtime($file_path)) . '</div>';
                    $output .= '<div class="print-button" style="text-align: left; margin-top: 5px; margin-left: 5px;">';
                        $output .= '<button onclick="printTable()" style="padding: 12px 12px; background-color: #2b6cb0; color: #fff; border: none; display: flex; align-items: center; font-size: 20px; line-height: 0;">';
                            $output .= '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="margin-right: 8px; vertical-align: middle;">';
                                $output .= '<path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>';
                                $output .= '<path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>';
                            $output .= '</svg>';
                            $output .= 'Drukuj';
                        $output .= '</button>';
                    $output .= '</div>';
                        $output .= '</div>'; // .tabela
                    $output .= '</div>'; // .col2
                $output .= '</div>'; // .col3 - dodatkowy blok pod tabelą

                // JavaScript for toggling visibility and loading new plan via AJAX without reloading the page

            } else {
                $output = 'Nie znaleziono pliku lista.html.';
            }
        } else {
            $output = 'Nie znaleziono tabeli z odpowiednimi atrybutami.';
        }
    } else {
        $output = 'Nie znaleziono pliku planu lekcji.';
    }

    return $output;
}






// Rejestracja shortcode dla daty obowiązywania
function data_obowiazywania_shortcode() {
    // Pobieramy wartość daty z ustawień, ale także sprawdzamy, czy tryb testowy jest aktywny
    $is_test_plan = isset($_GET['test']) && $_GET['test'] == '1';
    
    if ($is_test_plan) {
        // Jeśli testowy plan, pobieramy datę planu testowego
        $data = get_option('data_obowiazywania_test_plan', gmdate('d-m-Y')); // Domyślnie dzisiejsza data, jeśli brak ustawienia
    } else {
        // Jeśli normalny plan, pobieramy standardową datę obowiązywania
        $data = get_option('data_obowiazywania_option', gmdate('d-m-Y'));
    }

    // Zmieniamy format daty na d-m-Y
    $formatted_date = gmdate('d-m-Y', strtotime($data));

    return esc_html($formatted_date);
}

add_shortcode('data_obowiazywania_shortcode', 'data_obowiazywania_shortcode');

add_shortcode('plan_lekcji', 'plan_lekcji_shortcode');
?>
