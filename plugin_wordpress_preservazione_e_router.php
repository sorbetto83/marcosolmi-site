<?php
/**
 * Plugin Name: Marco Solmi Bilingual Preserve Layout, SEO, IME & Geo-Router
 * Description: Preserves 100% of existing Elementor structure on marcosolmi.com while adding: (1) IT/EN location-based routing (/ vs /homepage-english/), (2) CTP & Psichiatria Forense block on IT Homepage & Servizi, (3) Canadian/English version on Page 436 (no clinical visits, IME-focused, Ottawa Rideau imagery, Educational Events), (4) SEO keywords (Veneto/Belluno/Milano/Firenze/Roma + Ottawa/Ontario + Bipolar/Depression/Burnout/Anxiety/OCD/PTSD/Stress), (5) Contact Form routing directly to WordPress admin_email, and (6) Minimal IT (GDPR) & Ontario (PIPEDA/PHIPA) consent footer.
 * Version: 5.0.0
 * Author: Prof. Marco Solmi, MD, PhD
 */

if (!defined('ABSPATH')) {
    exit;
}

class MarcoSolmi_Preserve_Bilingual {

    private static $form_sent = false;

    public static function init() {
        add_action('init', array(__CLASS__, 'ensure_dedicated_pages_exist'));
        add_action('init', array(__CLASS__, 'handle_contact_form_post'));
        add_action('elementor_pro/forms/new_record', array(__CLASS__, 'forward_elementor_form_to_admin'), 10, 2);
        add_action('template_redirect', array(__CLASS__, 'maybe_geo_redirect_root'), 1);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_elementor_styles_for_english'), 20);
        add_filter('pre_get_document_title', array(__CLASS__, 'filter_document_title'), 20);
        add_action('wp_head', array(__CLASS__, 'inject_seo_schema_and_favicon'), 5);
        add_action('wp_footer', array(__CLASS__, 'render_consent_footer_and_switcher'), 99);
    }

    public static function is_canada_page() {
        return is_page(436) || is_page('homepage-english') || is_page('ime-services') || is_page('educational-events');
    }

    public static function is_italy_english_page() {
        return is_page('en') || (isset($_GET['lang']) && $_GET['lang'] === 'en' && !self::is_canada_page());
    }

    public static function ensure_dedicated_pages_exist() {
        if (get_option('ms_dedicated_pages_created_v5') === '1') {
            return;
        }
        $home_page = get_post(9);
        if ($home_page) {
            foreach (array('padova' => 'Psichiatra a Padova – Dr. Marco Solmi', 'treviso' => 'Psichiatra a Treviso – Dr. Marco Solmi') as $slug => $title) {
                if (!get_page_by_path($slug)) {
                    $new_id = wp_insert_post(array(
                        'post_title'   => $title,
                        'post_name'    => $slug,
                        'post_content' => $home_page->post_content,
                        'post_status'  => 'publish',
                        'post_type'    => 'page',
                    ));
                    if ($new_id && !is_wp_error($new_id)) {
                        update_post_meta($new_id, '_elementor_edit_mode', 'builder');
                        update_post_meta($new_id, '_elementor_data', get_post_meta(9, '_elementor_data', true));
                    }
                }
            }
        }
        update_option('ms_dedicated_pages_created_v5', '1');
    }

    public static function enqueue_elementor_styles_for_english() {
        if (self::is_canada_page() || self::is_italy_english_page() || is_page('padova') || is_page('treviso') || is_page('ctp-psichiatria-forense') || is_page('formazione-divulgazione') || is_page('visite') || is_page('testimonianze')) {
            $upload_dir = wp_upload_dir();
            $post9_css = $upload_dir['baseurl'] . '/elementor/css/post-9.css';
            wp_enqueue_style('ms-elementor-post-9-clone', $post9_css, array(), null);
        }
    }

    public static function handle_contact_form_post() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_fields']) && is_array($_POST['form_fields'])) {
            if (wp_doing_ajax()) return;
            $fields = wp_unslash($_POST['form_fields']);
            $name    = isset($fields['name']) ? sanitize_text_field($fields['name']) : '';
            $email   = isset($fields['email']) ? sanitize_email($fields['email']) : '';
            $message = isset($fields['message']) ? sanitize_textarea_field($fields['message']) : '';

            if (!empty($email) && !empty($message)) {
                $admin_email = get_option('admin_email');
                $subject = sprintf('[marcosolmi.com] Richiesta da %s', $name ? $name : $email);
                $body  = "Nome: " . $name . "\nEmail: " . $email . "\n\nMessaggio:\n" . $message;
                wp_mail($admin_email, $subject, $body, array('Reply-To: ' . $email));
                self::$form_sent = true;
            }
        }
    }

    public static function forward_elementor_form_to_admin($record, $handler) {
        $raw_fields = $record->get('fields');
        $name    = isset($raw_fields['name']['value']) ? sanitize_text_field($raw_fields['name']['value']) : '';
        $email   = isset($raw_fields['email']['value']) ? sanitize_email($raw_fields['email']['value']) : '';
        $message = isset($raw_fields['message']['value']) ? sanitize_textarea_field($raw_fields['message']['value']) : '';

        if (!empty($email)) {
            wp_mail(get_option('admin_email'), '[marcosolmi.com] Contatto da ' . $name, "Nome: $name\nEmail: $email\n\nMessaggio:\n$message", array('Reply-To: ' . $email));
        }
    }

    public static function maybe_geo_redirect_root() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return;
        $request_uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
        if ($request_uri !== '/' && $request_uri !== '') return;
        if (isset($_GET['noredirect']) || isset($_GET['lang'])) return;

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        foreach (array('googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot') as $bot) {
            if (strpos($ua, $bot) !== false) return;
        }

        $country = '';
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country = strtoupper(sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY']));
        } elseif (!empty($_SERVER['GEOIP_COUNTRY_CODE'])) {
            $country = strtoupper(sanitize_text_field($_SERVER['GEOIP_COUNTRY_CODE']));
        }

        if (in_array($country, array('CA', 'US'), true)) {
            wp_redirect(home_url('/homepage-english/'), 302);
            exit;
        }
    }

    public static function filter_document_title($title) {
        if (is_page('ime-services')) return 'Psychiatric Independent Medical Examinations (IMEs) & File Reviews in Ontario | Dr. Marco Solmi, MD, PhD';
        if (is_page('educational-events')) return 'Educational & Scientific Events across North America, Europe & Asia | Dr. Marco Solmi, MD, PhD';
        if (self::is_canada_page()) return 'Dr. Marco Solmi, MD, PhD | Psychiatric IMEs, Second Opinions & Educational Events in Ottawa & Ontario';
        if (is_page('visite')) return 'Visite Psichiatriche, Secondi Pareri e Percorsi Personalizzati | Dr. Marco Solmi, PhD';
        if (is_page('ctp-psichiatria-forense')) return 'CTP e Psichiatria Forense Civile e Penale | Dr. Marco Solmi – Psichiatra, Psichiatra Forense, PhD';
        if (is_page('formazione-divulgazione')) return 'Formazione e Divulgazione Scientifica in Salute Mentale | Dr. Marco Solmi, PhD';
        if (is_page('testimonianze')) return 'Testimonianze e Recensioni dei Pazienti | Dr. Marco Solmi, Psichiatra';
        if (is_page('padova')) return 'Dr. Marco Solmi | Psichiatra e Psichiatra Forense a Padova (Veneto) – Visite, Secondi Pareri e CTP';
        if (is_page('treviso')) return 'Dr. Marco Solmi | Psichiatra e Psichiatra Forense a Treviso (Veneto) – Visite, Secondi Pareri e CTP';
        return $title;
    }

    public static function inject_seo_schema_and_favicon() {
        $favicon_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230f172a'/%3E%3Crect x='3' y='3' width='58' height='58' rx='11' fill='none' stroke='%2338bdf8' stroke-width='1.5' stroke-opacity='0.35'/%3E%3Ctext x='32' y='41' text-anchor='middle' font-family='sans-serif' font-weight='800' font-size='26' letter-spacing='-1' fill='%23ffffff'%3EM%3Ctspan fill='%2338bdf8'%3ES%3C/tspan%3E%3C/text%3E%3C/svg%3E";
        echo '<link rel="icon" type="image/svg+xml" href="' . $favicon_svg . '" />' . "\n";
        echo '<link rel="apple-touch-icon" href="' . $favicon_svg . '" />' . "\n";
        echo '<style>
            img.wp-image-278, img[src*="Soggetto"], .elementor-element-83eeed2, .elementor-element-967b9b5 img {
                border: none !important; box-shadow: none !important; background: transparent !important;
                -webkit-mask-image: radial-gradient(ellipse 82% 85% at 50% 47%, #000 50%, rgba(0,0,0,0.82) 66%, rgba(0,0,0,0.32) 82%, transparent 98%);
                mask-image: radial-gradient(ellipse 82% 85% at 50% 47%, #000 50%, rgba(0,0,0,0.82) 66%, rgba(0,0,0,0.32) 82%, transparent 98%);
            }
            .elementor-element-e60220e, .elementor-element-83eeed2 {
                background-image: radial-gradient(ellipse 72% 72% at 50% 50%, rgba(226,228,233,0.85) 0%, rgba(235,238,242,0.45) 52%, rgba(248,250,252,0) 80%) !important;
            }
        </style>' . "\n";
    }

    public static function render_consent_footer_and_switcher() {
        $is_ca = self::is_canada_page();
        $is_it_en = self::is_italy_english_page();
        ?>
        <style>
            .ms-lang-bar { position:fixed; bottom:18px; right:18px; z-index:99999; background:#0f172a; color:#fff; border-radius:999px; padding:8px 16px; font-size:12.5px; font-weight:600; display:flex; gap:10px; align-items:center; box-shadow:0 8px 24px rgba(0,0,0,0.25); }
            .ms-lang-bar a { color:#94a3b8; text-decoration:none; }
            .ms-lang-bar a.active, .ms-lang-bar a:hover { color:#fff; text-decoration:underline; }
        </style>
        <div class="ms-lang-bar">
            <a href="<?php echo esc_url(add_query_arg('noredirect','1',home_url('/'))); ?>" class="<?php echo (!$is_ca && !$is_it_en) ? 'active' : ''; ?>">🇮🇹 IT</a>
            <a href="<?php echo esc_url(add_query_arg('lang','en',home_url('/'))); ?>" class="<?php echo $is_it_en ? 'active' : ''; ?>">🇮🇹 EN</a>
            <span style="color:#475569">|</span>
            <a href="<?php echo esc_url(home_url('/padova/')); ?>">/padova</a>
            <a href="<?php echo esc_url(home_url('/treviso/')); ?>">/treviso</a>
            <span style="color:#475569">|</span>
            <a href="<?php echo esc_url(home_url('/homepage-english/')); ?>" class="<?php echo $is_ca ? 'active' : ''; ?>">🇨🇦 Canada (IME)</a>
        </div>
        <?php
    }
}

MarcoSolmi_Preserve_Bilingual::init();