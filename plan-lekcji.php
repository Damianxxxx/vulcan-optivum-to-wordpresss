<?php
/*
Plugin Name: Plan Lekcji
Description: Tworzy plan lekcji na na podstawie plików html i umożliwia wyświetlanie planu jako shortcode.
Version: 2.2
Author: Damian Wałach
*/


// Rejestracja ustawienia dla wyłączenia planu
function plan_lekcji_register_settings() {
    // Rejestracja ustawienia daty obowiązywania planu
    register_setting('plan_lekcji_options_group', 'data_obowiazywania_option');
    register_setting('plan_lekcji_options_group', 'plan_disabled_option');  // Nowe ustawienie
	register_setting('plan_lekcji_options_group', 'show_plan_for_logged_in_option');

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
}

// Funkcja wyświetlająca pole do wprowadzenia daty obowiązywania planu
function data_obowiazywania_option_field() {
    $data = get_option('data_obowiazywania_option', date('d-m-Y')); // Pobranie wartości opcji, domyślnie ustawiamy dzisiejszą datę
    ?>
    <input type="date" name="data_obowiazywania_option" value="<?php echo esc_attr($data); ?>" placeholder="d-m-Y" />
    <?php
}
function show_plan_for_logged_in_option_field() {
    $show_for_logged_in = get_option('show_plan_for_logged_in_option', false);
    ?>
    <input type="checkbox" name="show_plan_for_logged_in_option" value="1" <?php checked($show_for_logged_in, 1); ?> />
    <label for="show_plan_for_logged_in_option">Pokaż plan lekcji dla zalogowanych użytkowników, gdy plan jest wyłączony</label>
    <?php
}
// Funkcja wyświetlająca pole do włączenia/wyłączenia planu
function plan_disabled_option_field() {
    $is_disabled = get_option('plan_disabled_option', false);
    ?>
    <input type="checkbox" name="plan_disabled_option" value="1" <?php checked($is_disabled, 1); ?> />
    <label for="plan_disabled_option">Wyłącz plan lekcji</label>
    <?php
}

function upload_and_extract_zip() {
    if (isset($_FILES['plan_lekcji_zip_file']) && current_user_can('manage_options')) {
        $uploaded_file = $_FILES['plan_lekcji_zip_file'];

        // Akceptujemy oba typy MIME dla plików ZIP
        if ($uploaded_file['type'] === 'application/zip' || $uploaded_file['type'] === 'application/x-zip-compressed') {
            // Określenie ścieżki do katalogu, gdzie plik ZIP będzie rozpakowywany
            $upload_dir = wp_upload_dir()['basedir'] . '/timetable/';
            $zip_file_path = $upload_dir . basename($uploaded_file['name']);

            // Sprawdzanie, czy katalog docelowy istnieje, jeśli nie, tworzymy go
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true); // Tworzenie katalogu
            }

            // Usuwamy zawartość katalogu 'timetable' przed rozpakowaniem
            array_map('unlink', glob($upload_dir . '*'));
            rmdir($upload_dir); // Usuwamy katalog
            mkdir($upload_dir, 0755, true); // Tworzymy katalog ponownie, aby mieć pusty katalog 'timetable'

            // Sprawdzenie, czy plik już istnieje
            if (file_exists($zip_file_path)) {
                echo '<div class="error"><p>Plik o tej nazwie już istnieje. Proszę zmienić nazwę pliku.</p></div>';
            } else {
                // Przeniesienie pliku do katalogu
                if (move_uploaded_file($uploaded_file['tmp_name'], $zip_file_path)) {
                    // Rozpakowanie pliku ZIP
                    $zip = new ZipArchive();
                    if ($zip->open($zip_file_path) === TRUE) {
                        // Rozpakowanie pliku do katalogu 'timetable'
                        $zip->extractTo($upload_dir);
                        $zip->close();

                        // Opcjonalnie usuwamy plik ZIP po rozpakowaniu
                        unlink($zip_file_path);

                        echo '<div class="updated"><p>Plik ZIP został pomyślnie przesłany i rozpakowany do katalogu: ' . $upload_dir . '</p></div>';
                    } else {
                        echo '<div class="error"><p>Nie udało się otworzyć pliku ZIP.</p></div>';
                    }
                } else {
                    echo '<div class="error"><p>Wystąpił problem podczas przesyłania pliku.</p></div>';
                }
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
        <h2>Upload i Rozpakowywanie ZIP</h2>
        <form method="post" enctype="multipart/form-data">
            <?php upload_and_extract_zip(); // Funkcja przetwarzająca upload ?>
            <input type="file" name="plan_lekcji_zip_file" accept=".zip" required />
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
require 'wp-guard/src/WpGuard.php';

$guard = new Anystack\WpGuard\V001\WpGuard(
	__FILE__,
	[
		'api_key' => 'dR7xvZ22UI4uP126t8YQuPprc35RyyQm',
		'product_id' => '9cca30c7-cbe4-4dff-b04f-37ddcdc2fcb8',
		'product_name' => 'plugin',
		'license' => [
			'require_email' => false,
		],
		'updater' => [
			'enabled' => true,
		]
	]
);

$guard->validCallback(function() {

// Hook do dodania strony ustawień do menu administracyjnego
add_action('admin_menu', 'plan_lekcji_menu');
});

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



function plan_lekcji_shortcode($atts) {
    // Sprawdzamy, czy plan jest wyłączony
    $is_disabled = get_option('plan_disabled_option', false);
    $show_for_logged_in = get_option('show_plan_for_logged_in_option', false);
    
    if ($is_disabled && (!is_user_logged_in() || !$show_for_logged_in)) {
        // Jeśli plan jest wyłączony i użytkownik nie jest zalogowany lub ustawienie pokazania planu dla zalogowanych jest wyłączone, wyświetlamy tylko kod HTML z obrazkiem
        return '<!-- wp:heading {"textAlign":"center","level":3,"style":{"elements":{"link":{"color":{"text":"var:preset|color|theme-palette1"}}}},"textColor":"theme-palette1"} -->
<h3 class="wp-block-heading has-text-align-center has-theme-palette-1-color has-text-color has-link-color">Jeśli to zadziała, to nie pytaj, jak. Będziemy udawać, że wszystko jest w porządku. #programista</h3>
<!-- /wp:heading -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="/wp-content/plugins/plan-lekcji/mem.jpeg" alt=""/></figure>
<!-- /wp:image -->';
    }

    // Jeśli plan nie jest wyłączony, wyświetlamy standardowy plan lekcji
    // Ścieżka do katalogu, w którym są pliki
    $upload_dir = wp_upload_dir()['basedir'] . '/timetable/';
    
// Sprawdzamy, czy mamy parametr 'plan' w URL
if (isset($_GET['plan'])) {
    $plan = sanitize_text_field($_GET['plan']);
    
    // Ustawiamy ciasteczko na 30 dni
    setcookie('plan', $plan, time() + 30 * DAY_IN_SECONDS, '/'); 
    $_COOKIE['plan'] = $plan; // Bezpośrednie przypisanie dla bieżącej sesji (żeby natychmiast działało)
}    // Ścieżka do pliku z planem lekcji

// Sprawdzamy, czy plan jest w URL lub ciasteczku
if (isset($_GET['plan'])) {
    $plan = sanitize_text_field($_GET['plan']);
} elseif (isset($_COOKIE['plan'])) {
    $plan = sanitize_text_field($_COOKIE['plan']);
} else {
    $plan = 'o1'; // Domyślny plan, jeśli nie znaleziono
}


    $file_path = $upload_dir . $plan . '.html';

    // Sprawdzenie, czy plik istnieje
    if (file_exists($file_path)) {
        // Odczytanie zawartości pliku
        $file_contents = file_get_contents($file_path);
        
        // Pobieramy tytuł z <span class="tytulnapis">...</span>
        preg_match('/<span class="tytulnapis">(.*?)<\/span>/is', $file_contents, $title_matches);
        $teacher_title = isset($title_matches[1]) ? $title_matches[1] : $plan;
        
// Za pomocą wyrażeń regularnych wyciągamy tylko zawartość tabeli z pliku
preg_match('/<table[^>]*border="1" cellspacing="0" cellpadding="4"[^>]*class="tabela"[^>]*>(.*?)<\/table>/is', $file_contents, $matches);

// Wprowadzenie zmiany w linkach tabeli
if (isset($matches[0])) {
    // Zawartość tabeli z atrybutami
    $table_content = $matches[0];

// Modyfikacja linków w tabeli
$table_content = preg_replace_callback('/href="(.*?)"/', function ($matches) use ($plan) {
    if (preg_match('/([a-zA-Z0-9_-]+)\.html/', $matches[1], $url_matches)) {
        $new_plan = $url_matches[1]; // Wyciągamy kod planu
        return 'href="?plan=' . $new_plan . '" class="ajax-link"'; // Dodajemy klasę ajax-link
    }
    return $matches[0]; // Jeśli link nie zawiera '.html', pozostawiamy go bez zmian
}, $table_content);
   

            // Wczytanie pliku lista.html do zmiennej
            $menu_file_path = $upload_dir . 'lista.html';
            if (file_exists($menu_file_path)) {
                $menu_contents = file_get_contents($menu_file_path);

                // Zmiana linków w menu, aby wskazywały odpowiedni plan
$menu_contents = preg_replace_callback('/href="(.*?)"/', function ($matches) use ($plan) {
    // Jeśli link zawiera .html, zmień go na ?plan=o1
    if (preg_match('/plany\/([a-zA-Z0-9_-]+)\.html/', $matches[1], $url_matches)) {
        $new_plan = $url_matches[1]; // Nowy plan
        return 'href="?plan=' . $new_plan . '"'; // Link z parametrem plan
    }
    return $matches[0]; // Jeśli link nie zawiera .html, pozostaw go bez zmian
}, $menu_contents);
    $sorted_nauczyciele = sort_teachers($menu_contents);
    
    // Wstaw wynik sortowania do HTML – UPEWNIJ SIĘ, ŻE NIE WSTAWIASZ NIGDY ORYGINALNEJ NIE SORTOWANEJ LISTY


                // Wyciągnięcie oddziałów, nauczycieli i sal
                preg_match('/<h4>Oddziały<\/h4>(.*?)<h4>Nauczyciele<\/h4>/is', $menu_contents, $oddzialy);
                preg_match('/<h4>Nauczyciele<\/h4>(.*?)<h4>Sale<\/h4>/is', $menu_contents, $nauczyciele);
                preg_match('/<h4>Sale<\/h4>(.*?)<\/body>/is', $menu_contents, $sale);

                // Generowanie kodu HTML do wyświetlenia
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
    visibility: hidden; /* Ukrywa elementy */
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
$output .= '<script src="/wp-content/plugins/plan-lekcji/js/menu.js"></script>';
$output .= '<h2 style="color: var(--global-palette2); margin-top:120px; font-size: 20px; text-align: center;">' . esc_html($teacher_title) . '</h2>';

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
                        $output .= '<h6 style="display: inline; font-size: 16px;">Plan obowiązuje od:</h6>';
                        $output .= '<h5 style="display: inline; margin-left: 5px; font-size: 18px;">' . do_shortcode('[data_obowiazywania_shortcode]') . ' r.</h5>';
                    $output .= '</div>';
                    $output .= '<div class="zglos">';
                        $output .= '<h4><a href="/plan-contact" style="color: red; text-decoration: none;">Zgłoś błąd w planie</a></h4>';
                    $output .= '</div>';
                    $output .= '<div class="aktualizacja"> Aktualizacja: ' . date("d-m-Y H:i", filemtime($file_path)) . '</div>';
                    $output .= '<div class="print-button" style="text-align: left; margin-top: 5px; margin-left: 5px;">';
                        $output .= '<button onclick="printTable()" style="padding: 12px 12px; background-color: #2b6cb0; color: #fff; border: none; display: flex; align-items: center; font-size: 20px; line-height: 0;">';
                            $output .= '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="margin-right: 8px; vertical-align: middle;">';
                                $output .= '<path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>';
                                $output .= '<path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>';
                            $output .= '</svg>';
                            $output .= 'Drukuj';
                        $output .= '</button>';
                    $output .= '</div>';
                    $output .= '<script src="/wp-content/plugins/plan-lekcji/js/printTableScript.js"></script>';
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

add_shortcode('plan_lekcji', 'plan_lekcji_shortcode');
// Rejestracja shortcode dla daty obowiązywania
function data_obowiazywania_shortcode() {
    // Pobieramy wartość daty z ustawień
    $data = get_option('data_obowiazywania_option', date('d-m-Y')); // Jeśli brak wartości, domyślnie ustawiamy dzisiejszą datę
    
    // Zmieniamy format daty na d-m-Y
    $formatted_date = date('d-m-Y', strtotime($data));

    return esc_html($formatted_date);
}
add_shortcode('data_obowiazywania_shortcode', 'data_obowiazywania_shortcode');

?>
