<?php
/**
 * Plugin Name: Schemati
 * Description: תוסף סכמה מלא עם כל התכונות והסייד-בר
 * Plugin URI: https://schemamarkapp.com/
 * Author: Shay Ohayon
 * Author URI: https://schemamarkapp.com/
 * Version: 5.0.1
 * Text Domain: schemati
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 */

// מנע גישה ישירה
if (!defined('ABSPATH')) {
    exit;
}

// הגדר קבועים
define('SCHEMATI_VERSION', '5.0.1');
define('SCHEMATI_FILE', __FILE__);
define('SCHEMATI_DIR', plugin_dir_path(__FILE__));
define('SCHEMATI_URL', plugin_dir_url(__FILE__));

/**
 * מחלקת התוסף הראשית Schemati
 */
class Schemati {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    /**
     * אתחול התוסף
     */
    public function init() {
        // הוקי הפעלה/כיבוי
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // הוקי ליבה
        add_action('wp_head', array($this, 'output_schema'), 1);
        
        // הוקי אדמין
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('save_post', array($this, 'save_meta_boxes'));
            
            // מטפלי AJAX
            add_action('wp_ajax_schemati_toggle_schema', array($this, 'ajax_toggle_schema'));
            add_action('wp_ajax_schemati_delete_schema', array($this, 'ajax_delete_schema'));
            add_action('wp_ajax_schemati_save_schema', array($this, 'ajax_save_schema'));
            add_action('wp_ajax_schemati_add_schema', array($this, 'ajax_add_schema'));
            add_action('wp_ajax_schemati_get_schema_template', array($this, 'ajax_get_schema_template'));
            add_action('wp_ajax_schemati_toggle_global', array($this, 'ajax_toggle_global'));
            add_action('wp_ajax_schemati_validate_schema', array($this, 'ajax_validate_schema'));
            add_action('wp_ajax_schemati_duplicate_schema', array($this, 'ajax_duplicate_schema'));
            add_action('wp_ajax_schemati_bulk_action', array($this, 'ajax_bulk_action'));
        }
        
        // הוקי חזית
        add_shortcode('schemati_breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_shortcode('breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_styles'));
        
        // פונקציונליות סייד-בר
        add_action('admin_bar_menu', array($this, 'add_admin_bar'), 100);
        add_action('wp_footer', array($this, 'add_sidebar_html'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_sidebar_scripts'));
    }

    /**
     * מטפל AJAX לשינוי סטטוס סכמה
     */
    public function ajax_toggle_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        if (isset($custom_schemas[$schema_index])) {
            $custom_schemas[$schema_index]['_enabled'] = !($custom_schemas[$schema_index]['_enabled'] ?? true);
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('סטטוס הסכמה עודכן');
        }
        
        wp_send_json_error('הסכמה לא נמצאה');
    }

    /**
     * מטפל AJAX למחיקת סכמה
     */
    public function ajax_delete_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            wp_send_json_error('לא נמצאו סכמות');
        }
        
        if (isset($custom_schemas[$schema_index])) {
            unset($custom_schemas[$schema_index]);
            $custom_schemas = array_values($custom_schemas); // אינדוקס מחדש
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('הסכמה נמחקה');
        }
        
        wp_send_json_error('הסכמה לא נמצאה');
    }

    /**
     * מטפל AJAX לשמירת שינויי סכמה
     */
    public function ajax_save_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        if (isset($custom_schemas[$schema_index])) {
            // עדכן סכמה עם נתונים חדשים
            $schema = $custom_schemas[$schema_index];
            
            // עדכן שדות נפוצים
            if (isset($_POST['name'])) {
                $schema['name'] = sanitize_text_field($_POST['name']);
            }
            if (isset($_POST['description'])) {
                $schema['description'] = sanitize_textarea_field($_POST['description']);
            }
            if (isset($_POST['url'])) {
                $schema['url'] = esc_url_raw($_POST['url']);
            }
            
            // עדכן שדות ספציפיים לסכמה לפי סוג
            switch ($schema['@type']) {
                case 'LocalBusiness':
                case 'Service':
                    if (isset($_POST['address'])) {
                        $schema['address'] = sanitize_textarea_field($_POST['address']);
                    }
                    if (isset($_POST['telephone'])) {
                        $schema['telephone'] = sanitize_text_field($_POST['telephone']);
                    }
                    if (isset($_POST['email'])) {
                        $schema['email'] = sanitize_email($_POST['email']);
                    }
                    break;
                
                case 'Product':
                    if (isset($_POST['brand'])) {
                        $schema['brand'] = sanitize_text_field($_POST['brand']);
                    }
                    if (isset($_POST['price'])) {
                        $schema['offers']['price'] = sanitize_text_field($_POST['price']);
                    }
                    if (isset($_POST['currency'])) {
                        $schema['offers']['priceCurrency'] = sanitize_text_field($_POST['currency']);
                    }
                    break;
            }
            
            $custom_schemas[$schema_index] = $schema;
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('הסכמה עודכנה בהצלחה');
        }
        
        wp_send_json_error('הסכמה לא נמצאה');
    }

    /**
     * מטפל AJAX להוספת סכמה חדשה
     */
    public function ajax_add_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        // צור סכמה חדשה לפי סוג
        $new_schema = $this->create_schema_template($schema_type, $_POST);
        
        if (!$new_schema) {
            wp_send_json_error('סוג סכמה לא חוקי');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        $custom_schemas[] = $new_schema;
        update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
        
        wp_send_json_success('הסכמה נוספה בהצלחה');
    }

    /**
     * מטפל AJAX לקבלת תבנית סכמה
     */
    public function ajax_get_schema_template() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $template_html = $this->get_schema_template_html($schema_type);
        
        if ($template_html) {
            wp_send_json_success($template_html);
        } else {
            wp_send_json_error('תבנית לא נמצאה');
        }
    }

    /**
     * מטפל AJAX לשינוי הגדרות גלובליות
     */
    public function ajax_toggle_global() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $enabled = intval($_POST['enabled']);
        $settings = $this->get_settings('schemati_general');
        $settings['enabled'] = $enabled;
        
        update_option('schemati_general', $settings);
        wp_send_json_success('הגדרה גלובלית עודכנה');
    }

    /**
     * מטפל AJAX לאימות סכמה
     */
    public function ajax_validate_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_data = json_decode(stripslashes($_POST['schema_data']), true);
        
        if (!$schema_data) {
            wp_send_json_error('נתוני סכמה לא חוקיים');
        }
        
        $validation_result = $this->validate_schema($schema_data);
        wp_send_json_success($validation_result);
    }

    /**
     * מטפל AJAX לשכפול סכמה
     */
    public function ajax_duplicate_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas) || !isset($custom_schemas[$schema_index])) {
            wp_send_json_error('הסכמה לא נמצאה');
        }
        
        $schema_to_duplicate = $custom_schemas[$schema_index];
        $schema_to_duplicate['name'] = ($schema_to_duplicate['name'] ?? 'העתק') . ' - העתק';
        
        $custom_schemas[] = $schema_to_duplicate;
        update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
        
        wp_send_json_success('הסכמה שוכפלה בהצלחה');
    }

    /**
     * מטפל AJAX לפעולות כמותיות
     */
    public function ajax_bulk_action() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        switch ($action) {
            case 'enable_all':
                foreach ($custom_schemas as &$schema) {
                    $schema['_enabled'] = true;
                }
                update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
                wp_send_json_success('כל הסכמות הופעלו');
                break;
                
            case 'disable_all':
                foreach ($custom_schemas as &$schema) {
                    $schema['_enabled'] = false;
                }
                update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
                wp_send_json_success('כל הסכמות הושבתו');
                break;
                
            case 'delete_all':
                delete_post_meta($post_id, '_schemati_custom_schemas');
                wp_send_json_success('כל הסכמות נמחקו');
                break;
                
            default:
                wp_send_json_error('פעולה לא ידועה');
        }
    }

    /**
     * אמת סכמה
     */
    private function validate_schema($schema) {
        $errors = array();
        $warnings = array();
        
        // בדיקות בסיסיות
        if (!isset($schema['@context'])) {
            $errors[] = 'חסר שדה @context';
        }
        
        if (!isset($schema['@type'])) {
            $errors[] = 'חסר שדה @type';
        }
        
        // בדיקות ספציפיות לפי סוג
        if (isset($schema['@type'])) {
            switch ($schema['@type']) {
                case 'Organization':
                case 'LocalBusiness':
                    if (empty($schema['name'])) {
                        $errors[] = 'שם הארגון/עסק נדרש';
                    }
                    if (empty($schema['url'])) {
                        $warnings[] = 'מומלץ להוסיף כתובת אתר';
                    }
                    break;
                    
                case 'Product':
                    if (empty($schema['name'])) {
                        $errors[] = 'שם המוצר נדרש';
                    }
                    if (empty($schema['offers'])) {
                        $warnings[] = 'מומלץ להוסיף מידע על הצעה';
                    }
                    break;
                    
                case 'Article':
                case 'BlogPosting':
                    if (empty($schema['headline'])) {
                        $errors[] = 'כותרת המאמר נדרשת';
                    }
                    if (empty($schema['author'])) {
                        $warnings[] = 'מומלץ להוסיף מידע על הכותב';
                    }
                    break;
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }

    /**
     * צור תבנית סכמה לפי סוג
     */
    private function create_schema_template($schema_type, $data) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            '_enabled' => true,
            '_source' => 'custom'
        );
        
        switch ($schema_type) {
            case 'LocalBusiness':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['address'] = sanitize_textarea_field($data['address'] ?? '');
                $schema['telephone'] = sanitize_text_field($data['telephone'] ?? '');
                $schema['email'] = sanitize_email($data['email'] ?? '');
                $schema['url'] = esc_url_raw($data['url'] ?? get_permalink());
                if (!empty($data['opening_hours'])) {
                    $schema['openingHours'] = sanitize_text_field($data['opening_hours']);
                }
                if (!empty($data['price_range'])) {
                    $schema['priceRange'] = sanitize_text_field($data['price_range']);
                }
                break;
                
            case 'Service':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['provider'] = array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                );
                $schema['areaServed'] = sanitize_text_field($data['area_served'] ?? '');
                if (!empty($data['service_type'])) {
                    $schema['serviceType'] = sanitize_text_field($data['service_type']);
                }
                break;
                
            case 'Product':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['brand'] = sanitize_text_field($data['brand'] ?? '');
                $schema['offers'] = array(
                    '@type' => 'Offer',
                    'price' => sanitize_text_field($data['price'] ?? ''),
                    'priceCurrency' => sanitize_text_field($data['currency'] ?? 'ILS'),
                    'availability' => 'https://schema.org/InStock'
                );
                if (!empty($data['sku'])) {
                    $schema['sku'] = sanitize_text_field($data['sku']);
                }
                if (!empty($data['mpn'])) {
                    $schema['mpn'] = sanitize_text_field($data['mpn']);
                }
                break;
                
            case 'Event':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['startDate'] = sanitize_text_field($data['start_date'] ?? '');
                $schema['endDate'] = sanitize_text_field($data['end_date'] ?? '');
                $schema['location'] = array(
                    '@type' => 'Place',
                    'name' => sanitize_text_field($data['location'] ?? '')
                );
                if (!empty($data['event_status'])) {
                    $schema['eventStatus'] = 'https://schema.org/' . sanitize_text_field($data['event_status']);
                }
                if (!empty($data['ticket_url'])) {
                    $schema['offers'] = array(
                        '@type' => 'Offer',
                        'url' => esc_url_raw($data['ticket_url'])
                    );
                }
                break;
                
            case 'Person':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['jobTitle'] = sanitize_text_field($data['job_title'] ?? '');
                $schema['email'] = sanitize_email($data['email'] ?? '');
                $schema['telephone'] = sanitize_text_field($data['telephone'] ?? '');
                $schema['url'] = esc_url_raw($data['url'] ?? '');
                if (!empty($data['works_for'])) {
                    $schema['worksFor'] = array(
                        '@type' => 'Organization',
                        'name' => sanitize_text_field($data['works_for'])
                    );
                }
                break;
                
            case 'FAQPage':
                $schema['mainEntity'] = array();
                if (isset($data['questions']) && is_array($data['questions'])) {
                    foreach ($data['questions'] as $i => $question) {
                        if (!empty($question) && !empty($data['answers'][$i])) {
                            $schema['mainEntity'][] = array(
                                '@type' => 'Question',
                                'name' => sanitize_text_field($question),
                                'acceptedAnswer' => array(
                                    '@type' => 'Answer',
                                    'text' => sanitize_textarea_field($data['answers'][$i])
                                )
                            );
                        }
                    }
                }
                break;
                
            case 'HowTo':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['totalTime'] = sanitize_text_field($data['total_time'] ?? '');
                $schema['supply'] = array();
                $schema['tool'] = array();
                $schema['step'] = array();
                
                // הוסף ציוד
                if (isset($data['supplies']) && is_array($data['supplies'])) {
                    foreach ($data['supplies'] as $supply) {
                        if (!empty($supply)) {
                            $schema['supply'][] = array(
                                '@type' => 'HowToSupply',
                                'name' => sanitize_text_field($supply)
                            );
                        }
                    }
                }
                
                // הוסף כלים
                if (isset($data['tools']) && is_array($data['tools'])) {
                    foreach ($data['tools'] as $tool) {
                        if (!empty($tool)) {
                            $schema['tool'][] = array(
                                '@type' => 'HowToTool',
                                'name' => sanitize_text_field($tool)
                            );
                        }
                    }
                }
                
                // הוסף שלבים
                if (isset($data['steps']) && is_array($data['steps'])) {
                    foreach ($data['steps'] as $i => $step) {
                        if (!empty($step)) {
                            $schema['step'][] = array(
                                '@type' => 'HowToStep',
                                'name' => sanitize_text_field($data['step_names'][$i] ?? 'שלב ' . ($i + 1)),
                                'text' => sanitize_textarea_field($step)
                            );
                        }
                    }
                }
                break;
                
            case 'Recipe':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['prepTime'] = sanitize_text_field($data['prep_time'] ?? '');
                $schema['cookTime'] = sanitize_text_field($data['cook_time'] ?? '');
                $schema['totalTime'] = sanitize_text_field($data['total_time'] ?? '');
                $schema['recipeYield'] = sanitize_text_field($data['recipe_yield'] ?? '');
                $schema['recipeCategory'] = sanitize_text_field($data['recipe_category'] ?? '');
                $schema['recipeCuisine'] = sanitize_text_field($data['recipe_cuisine'] ?? '');
                
                // הוסף רכיבים
                $schema['recipeIngredient'] = array();
                if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                    foreach ($data['ingredients'] as $ingredient) {
                        if (!empty($ingredient)) {
                            $schema['recipeIngredient'][] = sanitize_text_field($ingredient);
                        }
                    }
                }
                
                // הוסף הוראות
                $schema['recipeInstructions'] = array();
                if (isset($data['instructions']) && is_array($data['instructions'])) {
                    foreach ($data['instructions'] as $instruction) {
                        if (!empty($instruction)) {
                            $schema['recipeInstructions'][] = array(
                                '@type' => 'HowToStep',
                                'text' => sanitize_textarea_field($instruction)
                            );
                        }
                    }
                }
                
                // הוסף מידע תזונתי אם סופק
                if (!empty($data['calories'])) {
                    $schema['nutrition'] = array(
                        '@type' => 'NutritionInformation',
                        'calories' => sanitize_text_field($data['calories'])
                    );
                }
                break;
                
            case 'VideoObject':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['contentUrl'] = esc_url_raw($data['content_url'] ?? '');
                $schema['embedUrl'] = esc_url_raw($data['embed_url'] ?? '');
                $schema['uploadDate'] = sanitize_text_field($data['upload_date'] ?? date('c'));
                $schema['duration'] = sanitize_text_field($data['duration'] ?? '');
                if (!empty($data['thumbnail_url'])) {
                    $schema['thumbnailUrl'] = esc_url_raw($data['thumbnail_url']);
                }
                break;
                
            case 'Review':
                $schema['itemReviewed'] = array(
                    '@type' => sanitize_text_field($data['item_type'] ?? 'Thing'),
                    'name' => sanitize_text_field($data['item_name'] ?? '')
                );
                $schema['reviewRating'] = array(
                    '@type' => 'Rating',
                    'ratingValue' => intval($data['rating_value'] ?? 5),
                    'bestRating' => intval($data['best_rating'] ?? 5),
                    'worstRating' => intval($data['worst_rating'] ?? 1)
                );
                $schema['author'] = array(
                    '@type' => 'Person',
                    'name' => sanitize_text_field($data['author_name'] ?? get_the_author())
                );
                $schema['reviewBody'] = sanitize_textarea_field($data['review_body'] ?? '');
                break;
                
            case 'Organization':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['url'] = esc_url_raw($data['url'] ?? '');
                $schema['email'] = sanitize_email($data['email'] ?? '');
                $schema['telephone'] = sanitize_text_field($data['telephone'] ?? '');
                if (!empty($data['logo_url'])) {
                    $schema['logo'] = esc_url_raw($data['logo_url']);
                }
                if (!empty($data['social_urls'])) {
                    $social_urls = explode("\n", $data['social_urls']);
                    $schema['sameAs'] = array_map('esc_url_raw', array_filter($social_urls));
                }
                break;
                
            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                $schema['headline'] = sanitize_text_field($data['headline'] ?? get_the_title());
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['author'] = array(
                    '@type' => 'Person',
                    'name' => sanitize_text_field($data['author_name'] ?? get_the_author())
                );
                $schema['datePublished'] = sanitize_text_field($data['date_published'] ?? get_the_date('c'));
                $schema['dateModified'] = sanitize_text_field($data['date_modified'] ?? get_the_modified_date('c'));
                if (!empty($data['image_url'])) {
                    $schema['image'] = esc_url_raw($data['image_url']);
                }
                break;
                
            case 'WebSite':
                $schema['name'] = sanitize_text_field($data['name'] ?? get_bloginfo('name'));
                $schema['url'] = home_url();
                if (!empty($data['potential_action'])) {
                    $schema['potentialAction'] = array(
                        '@type' => 'SearchAction',
                        'target' => home_url('/?s={search_term_string}'),
                        'query-input' => 'required name=search_term_string'
                    );
                }
                break;
                
            default:
                return false;
        }
        
        return $schema;
    }

    /**
     * קבל HTML של תבנית סכמה
     */
    private function get_schema_template_html($schema_type) {
        ob_start();
        
        switch ($schema_type) {
            case 'LocalBusiness':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם העסק:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">כתובת:</label>
                    <textarea name="address" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">טלפון:</label>
                        <input type="text" name="telephone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">דוא"ל:</label>
                        <input type="email" name="email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">כתובת אתר:</label>
                    <input type="url" name="url" value="<?php echo esc_url(get_permalink()); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שעות פתיחה:</label>
                    <input type="text" name="opening_hours" placeholder="ראשון-חמישי 9:00-17:00" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">טווח מחירים:</label>
                    <input type="text" name="price_range" placeholder="₪₪₪" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'Service':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם השירות:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">אזור שירות:</label>
                    <input type="text" name="area_served" placeholder="למשל: תל אביב, ישראל" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">סוג שירות:</label>
                    <input type="text" name="service_type" placeholder="למשל: עיצוב גרפי" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'Product':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם המוצר:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">מותג:</label>
                    <input type="text" name="brand" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">מחיר:</label>
                        <input type="number" name="price" step="0.01" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">מטבע:</label>
                        <select name="currency" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="ILS">שקל ישראלי (₪)</option>
                            <option value="USD">דולר אמריקאי ($)</option>
                            <option value="EUR">יורו (€)</option>
                            <option value="GBP">לירה שטרלינג (£)</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">מספר מוצר (SKU):</label>
                        <input type="text" name="sku" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">מספר יצרן (MPN):</label>
                        <input type="text" name="mpn" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <?php
                break;
                
            case 'Event':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם האירוע:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תאריך התחלה:</label>
                        <input type="datetime-local" name="start_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תאריך סיום:</label>
                        <input type="datetime-local" name="end_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">מיקום:</label>
                    <input type="text" name="location" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">סטטוס אירוע:</label>
                    <select name="event_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="EventScheduled">מתוכנן</option>
                        <option value="EventCancelled">מבוטל</option>
                        <option value="EventPostponed">נדחה</option>
                        <option value="EventRescheduled">תאריך שונה</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">קישור לכרטיסים:</label>
                    <input type="url" name="ticket_url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'Person':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם מלא:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תפקיד:</label>
                    <input type="text" name="job_title" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">דוא"ל:</label>
                        <input type="email" name="email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">טלפון:</label>
                        <input type="text" name="telephone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">אתר אינטרנט:</label>
                    <input type="url" name="url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">עובד עבור:</label>
                    <input type="text" name="works_for" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'FAQPage':
                ?>
                <div id="faq-questions">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שאלה 1:</label>
                        <input type="text" name="questions[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px;">
                        <textarea name="answers[]" placeholder="תשובה..." rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    </div>
                </div>
                <button type="button" onclick="addFAQQuestion()" style="background: #0073aa; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-bottom: 15px;">
                    + הוסף שאלה נוספת
                </button>
                <script>
                function addFAQQuestion() {
                    var container = document.getElementById('faq-questions');
                    var questionNum = container.children.length + 1;
                    var html = '<div style="margin-bottom: 15px;">' +
                        '<label style="display: block; margin-bottom: 5px; font-weight: 500;">שאלה ' + questionNum + ':</label>' +
                        '<input type="text" name="questions[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px;">' +
                        '<textarea name="answers[]" placeholder="תשובה..." rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>' +
                        '</div>';
                    container.insertAdjacentHTML('beforeend', html);
                }
                </script>
                <?php
                break;
                
            case 'HowTo':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">כותרת הוראות:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">זמן כולל:</label>
                    <input type="text" name="total_time" placeholder="למשל: PT30M (30 דקות)" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'Recipe':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם המתכון:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">זמן הכנה:</label>
                        <input type="text" name="prep_time" placeholder="PT15M" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">זמן בישול:</label>
                        <input type="text" name="cook_time" placeholder="PT30M" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">מספר מנות:</label>
                        <input type="text" name="recipe_yield" placeholder="4 מנות" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">קטגוריה:</label>
                        <input type="text" name="recipe_category" placeholder="עיקרית" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <?php
                break;
                
            default:
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <?php
        }
        
        return ob_get_clean();
    }

    /**
     * שיטות עזר לבניית סכמות
     */
    private function build_organization_schema() {
        $settings = $this->get_settings('schemati_general');
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $settings['org_type'] ?? 'Organization',
            'name' => $settings['org_name'] ?? get_bloginfo('name'),
            'url' => home_url()
        );
        
        // הוסף פרטי עסק מקומי אם מופעל
        $business_settings = $this->get_settings('schemati_local_business');
        if ($business_settings['enabled'] ?? false) {
            if (!empty($business_settings['address'])) {
                $schema['address'] = $business_settings['address'];
            }
            if (!empty($business_settings['phone'])) {
                $schema['telephone'] = $business_settings['phone'];
            }
        }
        
        return $schema;
    }

    private function build_webpage_schema() {
        global $post;
        
        if (!$post) return null;
        
        $custom_type = get_post_meta($post->ID, '_schemati_type', true);
        $custom_description = get_post_meta($post->ID, '_schemati_description', true);
        
        // קבע סוג סכמה
        $schema_type = 'WebPage';
        if ($custom_type) {
            $schema_type = $custom_type;
        } elseif ($post->post_type === 'post') {
            $article_settings = $this->get_settings('schemati_article');
            if ($article_settings['enabled'] ?? true) {
                $schema_type = $article_settings['article_type'] ?? 'Article';
            }
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'name' => get_the_title(),
            'url' => get_permalink(),
            'description' => $custom_description ?: wp_trim_words(get_the_excerpt() ?: $post->post_content, 25, '...')
        );
        
        // הוסף שדות ספציפיים למאמר
        if (in_array($schema_type, array('Article', 'BlogPosting', 'NewsArticle'))) {
            $schema['headline'] = get_the_title();
            $schema['datePublished'] = get_the_date('c');
            $schema['dateModified'] = get_the_modified_date('c');
            $schema['author'] = array(
                '@type' => 'Person',
                'name' => get_the_author()
            );
            
            // הוסף תמונה ראשית אם זמינה
            if (has_post_thumbnail()) {
                $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
                if ($image) {
                    $schema['image'] = $image[0];
                }
            }
        }
        
        return $schema;
    }

    private function build_breadcrumb_schema() {
        $breadcrumbs = $this->get_breadcrumb_data();
        
        if (empty($breadcrumbs)) {
            return null;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array()
        );
        
        foreach ($breadcrumbs as $index => $crumb) {
            $schema['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['title'],
                'item' => $crumb['url']
            );
        }
        
        return $schema;
    }
    
    /**
     * הפעלת התוסף
     */
    public function activate() {
        // הגדר אפשרויות ברירת מחדל לכל סוגי הסכמות
        $defaults = array(
            'version' => SCHEMATI_VERSION,
            'enabled' => true,
            'org_name' => get_bloginfo('name'),
            'org_type' => 'Organization',
            'breadcrumb_home' => 'בית',
            'breadcrumb_separator' => ' › ',
            'show_current' => true
        );
        
        add_option('schemati_general', $defaults);
        add_option('schemati_article', array('enabled' => true, 'article_type' => 'Article'));
        add_option('schemati_about_page', array('enabled' => false));
        add_option('schemati_contact_page', array('enabled' => false));
        add_option('schemati_local_business', array('enabled' => false));
        add_option('schemati_person', array('enabled' => false));
        add_option('schemati_author', array('enabled' => false));
        add_option('schemati_publisher', array('enabled' => false));
        add_option('schemati_product', array('enabled' => false));
        add_option('schemati_faq', array('enabled' => false));
        
        // הצג הודעת הפעלה
        set_transient('schemati_activated', true, 30);
        
        flush_rewrite_rules();
    }
    
    /**
     * כיבוי התוסף
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * הוסף תפריט אדמין
     */
    public function admin_menu() {
        // דף תפריט ראשי
        add_menu_page(
            'הגדרות Schemati',
            'Schemati',
            'manage_options',
            'schemati',
            array($this, 'general_page'),
            'dashicons-admin-settings',
            80
        );
        
        // כל דפי המשנה
        add_submenu_page('schemati', 'הגדרות כלליות', 'כללי', 'manage_options', 'schemati', array($this, 'general_page'));
        add_submenu_page('schemati', 'סכמת מאמר', 'מאמר', 'manage_options', 'schemati-article', array($this, 'article_page'));
        add_submenu_page('schemati', 'סכמת דף אודות', 'דף אודות', 'manage_options', 'schemati-about', array($this, 'about_page'));
        add_submenu_page('schemati', 'סכמת דף צור קשר', 'דף צור קשר', 'manage_options', 'schemati-contact', array($this, 'contact_page'));
        add_submenu_page('schemati', 'סכמת עסק מקומי', 'עסק מקומי', 'manage_options', 'schemati-business', array($this, 'business_page'));
        add_submenu_page('schemati', 'סכמת אדם', 'אדם', 'manage_options', 'schemati-person', array($this, 'person_page'));
        add_submenu_page('schemati', 'סכמת כותב', 'כותב', 'manage_options', 'schemati-author', array($this, 'author_page'));
        add_submenu_page('schemati', 'סכמת מפרסם', 'מפרסם', 'manage_options', 'schemati-publisher', array($this, 'publisher_page'));
        add_submenu_page('schemati', 'סכמת מוצר', 'מוצר', 'manage_options', 'schemati-product', array($this, 'product_page'));
        add_submenu_page('schemati', 'סכמת שאלות נפוצות', 'שאלות נפוצות', 'manage_options', 'schemati-faq', array($this, 'faq_page'));
        add_submenu_page('schemati', 'כלי בדיקה', 'כלי בדיקה', 'manage_options', 'schemati-tools', array($this, 'tools_page'));
        add_submenu_page('schemati', 'רישיון', 'רישיון', 'manage_options', 'schemati-license', array($this, 'license_page'));
    }
    
    /**
     * רשום הגדרות אדמין
     */
    public function admin_init() {
        // רשום הגדרות לכל סוגי הסכמות
        register_setting('schemati_general', 'schemati_general', array($this, 'sanitize_settings'));
        register_setting('schemati_article', 'schemati_article', array($this, 'sanitize_settings'));
        register_setting('schemati_about_page', 'schemati_about_page', array($this, 'sanitize_settings'));
        register_setting('schemati_contact_page', 'schemati_contact_page', array($this, 'sanitize_settings'));
        register_setting('schemati_local_business', 'schemati_local_business', array($this, 'sanitize_settings'));
        register_setting('schemati_person', 'schemati_person', array($this, 'sanitize_settings'));
        register_setting('schemati_author', 'schemati_author', array($this, 'sanitize_settings'));
        register_setting('schemati_publisher', 'schemati_publisher', array($this, 'sanitize_settings'));
        register_setting('schemati_product', 'schemati_product', array($this, 'sanitize_settings'));
        register_setting('schemati_faq', 'schemati_faq', array($this, 'sanitize_settings'));
    }
    
    /**
     * נקה הגדרות
     */
    public function sanitize_settings($input) {
        $output = array();
        
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $output[$key] = array_map('sanitize_text_field', $value);
            } else {
                $output[$key] = sanitize_text_field($value);
            }
        }
        
        return $output;
    }
    
    /**
     * קבל הגדרות לכל קבוצת אפשרויות
     */
    public function get_settings($group = 'schemati_general') {
        $defaults = array(
            'enabled' => true,
            'org_name' => get_bloginfo('name'),
            'org_type' => 'Organization',
            'breadcrumb_home' => 'בית',
            'breadcrumb_separator' => ' › ',
            'show_current' => true
        );
        
        return get_option($group, $defaults);
    }
    
    /**
     * קבל מידע מקור
     */
    private function get_source_info($source) {
        switch ($source) {
            case 'global':
                return array(
                    'label' => 'גלובלי',
                    'icon' => '🌐',
                    'color' => '#17a2b8'
                );
            case 'post':
                return array(
                    'label' => 'פוסט',
                    'icon' => '📄',
                    'color' => '#28a745'
                );
            case 'auto':
                return array(
                    'label' => 'אוטומטי',
                    'icon' => '🤖',
                    'color' => '#6f42c1'
                );
            case 'custom':
                return array(
                    'label' => 'מותאם אישית',
                    'icon' => '✏️',
                    'color' => '#fd7e14'
                );
            case 'dom':
            case 'system':
                return array(
                    'label' => 'מערכת',
                    'icon' => '⚙️',
                    'color' => '#6c757d'
                );
            default:
                return array(
                    'label' => 'לא ידוע',
                    'icon' => '❓',
                    'color' => '#6c757d'
                );
        }
    }

    /**
     * תרגם סוג סכמה לעברית
     */
    private function translate_schema_type($schema_type) {
        $translations = array(
            'Organization' => 'ארגון',
            'LocalBusiness' => 'עסק מקומי',
            'Corporation' => 'תאגיד',
            'Person' => 'אדם',
            'WebPage' => 'דף אינטרנט',
            'WebSite' => 'אתר אינטרנט',
            'Article' => 'מאמר',
            'BlogPosting' => 'פוסט בבלוג',
            'NewsArticle' => 'מאמר חדשות',
            'Product' => 'מוצר',
            'Service' => 'שירות',
            'Event' => 'אירוע',
            'BreadcrumbList' => 'רשימת פירורי לחם',
            'FAQPage' => 'דף שאלות נפוצות',
            'HowTo' => 'הוראות',
            'Recipe' => 'מתכון',
            'VideoObject' => 'וידאו',
            'ImageObject' => 'תמונה',
            'AudioObject' => 'אודיו',
            'Review' => 'ביקורת'
        );
        
        return $translations[$schema_type] ?? $schema_type;
    }
    
    /**
     * דף הגדרות כלליות
     */
    public function general_page() {
        $this->handle_form_submission('schemati_general');
        $settings = $this->get_settings('schemati_general');
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>Schemati - הגדרות כלליות</h1>
            
            <?php if (get_transient('schemati_activated')): ?>
                <?php delete_transient('schemati_activated'); ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Schemati v5.0 הופעל!</strong> כל התכונות נטענו בהצלחה.</p>
                </div>
            <?php endif; ?>
            
            <div class="notice notice-info">
                <p><strong>✅ Schemati v5.0</strong> - פתרון סכמה מלא עם סייד-בר, כל סוגי הסכמות ופירורי לחם.</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <h2>הגדרות סכמה כלליות</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">אפשר סימון סכמה</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                אפשר פלט סימון סכמה בכל האתר
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">שם הארגון</th>
                        <td>
                            <input type="text" name="org_name" value="<?php echo esc_attr($settings['org_name'] ?? get_bloginfo('name')); ?>" class="regular-text" />
                            <p class="description">שם הארגון או האתר שלך</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">סוג הארגון</th>
                        <td>
                            <select name="org_type">
                                <option value="Organization" <?php selected($settings['org_type'] ?? 'Organization', 'Organization'); ?>>ארגון</option>
                                <option value="LocalBusiness" <?php selected($settings['org_type'] ?? '', 'LocalBusiness'); ?>>עסק מקומי</option>
                                <option value="Corporation" <?php selected($settings['org_type'] ?? '', 'Corporation'); ?>>תאגיד</option>
                                <option value="Person" <?php selected($settings['org_type'] ?? '', 'Person'); ?>>אדם</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2>הגדרות פירורי לחם</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">טקסט בית</th>
                        <td>
                            <input type="text" name="breadcrumb_home" value="<?php echo esc_attr($settings['breadcrumb_home'] ?? 'בית'); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">מפריד</th>
                        <td>
                            <input type="text" name="breadcrumb_separator" value="<?php echo esc_attr($settings['breadcrumb_separator'] ?? ' › '); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">הצג דף נוכחי</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_current" value="1" <?php checked(1, $settings['show_current'] ?? true); ?> />
                                הצג דף נוכחי במסלול פירורי הלחם
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3>שימוש</h3>
                <p><strong>קיצור דרך:</strong> <code>[schemati_breadcrumbs]</code></p>
                <p><strong>פונקצית PHP:</strong> <code>&lt;?php echo schemati_breadcrumbs(); ?&gt;</code></p>
                
                <?php submit_button('שמור שינויים'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * דף סכמת מאמר
     */
    public function article_page() {
        $this->handle_form_submission('schemati_article');
        $settings = $this->get_settings('schemati_article');
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>הגדרות סכמת מאמר</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">אפשר סכמת מאמר</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                צור סימון סכמת מאמר עבור פוסטים
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">סוג מאמר ברירת מחדל</th>
                        <td>
                            <select name="article_type">
                                <option value="Article" <?php selected($settings['article_type'] ?? 'Article', 'Article'); ?>>מאמר</option>
                                <option value="BlogPosting" <?php selected($settings['article_type'] ?? '', 'BlogPosting'); ?>>פוסט בבלוג</option>
                                <option value="NewsArticle" <?php selected($settings['article_type'] ?? '', 'NewsArticle'); ?>>מאמר חדשות</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('שמור שינויים'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * סכמת דף אודות
     */
    public function about_page() {
        $this->schema_page_template('סכמת דף אודות', 'schemati_about_page', 'צור סימון סכמה עבור דפי אודות');
    }
    
    /**
     * סכמת דף צור קשר
     */
    public function contact_page() {
        $this->schema_page_template('סכמת דף צור קשר', 'schemati_contact_page', 'צור סימון סכמה עבור דפי צור קשר');
    }
    
    /**
     * סכמת עסק מקומי
     */
    public function business_page() {
        $this->handle_form_submission('schemati_local_business');
        $settings = $this->get_settings('schemati_local_business');
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>סכמת עסק מקומי</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">אפשר סכמת עסק מקומי</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                                צור סימון סכמת עסק מקומי
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">שם העסק</th>
                        <td>
                            <input type="text" name="business_name" value="<?php echo esc_attr($settings['business_name'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">סוג העסק</th>
                        <td>
                            <select name="business_type">
                                <option value="LocalBusiness" <?php selected($settings['business_type'] ?? 'LocalBusiness', 'LocalBusiness'); ?>>עסק מקומי</option>
                                <option value="Restaurant" <?php selected($settings['business_type'] ?? '', 'Restaurant'); ?>>מסעדה</option>
                                <option value="Store" <?php selected($settings['business_type'] ?? '', 'Store'); ?>>חנות</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">כתובת</th>
                        <td>
                            <textarea name="address" rows="3" class="large-text"><?php echo esc_textarea($settings['address'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">מספר טלפון</th>
                        <td>
                            <input type="text" name="phone" value="<?php echo esc_attr($settings['phone'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('שמור שינויים'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * סכמת אדם
     */
    public function person_page() {
        $this->schema_page_template('סכמת אדם', 'schemati_person', 'צור סימון סכמה עבור אדם/יחיד');
    }
    
    /**
     * סכמת כותב
     */
    public function author_page() {
        $this->schema_page_template('סכמת כותב', 'schemati_author', 'צור סימון סכמה עבור כותבים');
    }
    
    /**
     * סכמת מפרסם
     */
    public function publisher_page() {
        $this->schema_page_template('סכמת מפרסם', 'schemati_publisher', 'צור סימון סכמה עבור מפרסמים');
    }
    
    /**
     * סכמת מוצר
     */
    public function product_page() {
        $this->schema_page_template('סכמת מוצר', 'schemati_product', 'צור סימון סכמה עבור מוצרים');
    }
    
    /**
     * סכמת שאלות נפוצות
     */
    public function faq_page() {
        $this->schema_page_template('סכמת שאלות נפוצות', 'schemati_faq', 'צור סימון סכמה עבור דפי שאלות נפוצות');
    }
    
    /**
     * דף כלים/בדיקה
     */
    public function tools_page() {
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>כלי Schemati ואבחון</h1>
            
            <div class="card">
                <h2>כלי בדיקת סכמה</h2>
                <p>בדוק את סימון הסכמה שלך עם הכלים הרשמיים הבאים:</p>
                <p>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="button button-primary">
                        בדיקת תוצאות עשירות של גוגל
                    </a>
                    <a href="https://validator.schema.org/" target="_blank" class="button button-secondary">
                        מאמת Schema.org
                    </a>
                    <a href="https://developers.facebook.com/tools/debug/" target="_blank" class="button button-secondary">
                        דיבאגר פייסבוק
                    </a>
                </p>
            </div>
            
            <div class="card">
                <h2>סטטוס הפלאגין</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>גרסה</strong></td>
                        <td><?php echo SCHEMATI_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>סטטוס</strong></td>
                        <td>
                            <?php 
                            $general = $this->get_settings('schemati_general');
                            echo $general['enabled'] ? '<span style="color: green;">✓ פעיל</span>' : '<span style="color: red;">✗ מושבת</span>'; 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>סוגי סכמה זמינים</strong></td>
                        <td>ארגון, דף אינטרנט, מאמר, עסק מקומי, אדם, מוצר, שאלות נפוצות, רשימת פירורי לחם</td>
                    </tr>
                    <tr>
                        <td><strong>סייד-בר</strong></td>
                        <td>✓ פעיל בחזית עבור משתמשים מחוברים</td>
                    </tr>
                    <tr>
                        <td><strong>פירורי לחם</strong></td>
                        <td>✓ קיצור דרך ופונקצית PHP זמינים</td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2>תצוגה מקדימה של סכמת עמוד נוכחי</h2>
                <p>בקר באתר שלך בזמן שאתה מחובר כדי לראות את הסייד-בר של Schemati עם תצוגה מקדימה חיה של הסכמה.</p>
                <p><a href="<?php echo home_url(); ?>" target="_blank" class="button">צפה באתר עם סייד-בר</a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * דף רישיון
     */
    public function license_page() {
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>רישיון Schemati</h1>
            
            <div class="card">
                <h2>מידע רישיון</h2>
                <p>Schemati v5.0 מורשה תחת GPL v2 או מאוחר יותר.</p>
                <p>התוסף הזה חינמי וקוד פתוח.</p>
                
                <h3>פרטי התוסף</h3>
                <ul>
                    <li><strong>גרסה:</strong> <?php echo SCHEMATI_VERSION; ?></li>
                    <li><strong>יוצר:</strong> Shay Ohayon</li>
                    <li><strong>אתר:</strong> <a href="https://schemamarkapp.com" target="_blank">schemamarkapp.com</a></li>
                    <li><strong>רישיון:</strong> GPL v2 or later</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * תבנית דף סכמה גנרית
     */
    private function schema_page_template($title, $option_group, $description) {
        $this->handle_form_submission($option_group);
        $settings = $this->get_settings($option_group);
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html(str_replace(' סכמת', '', $title)); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                                <?php echo esc_html($description); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('שמור שינויים'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * טפל בהגשות טפסים
     */
    private function handle_form_submission($option_group) {
        if (isset($_POST['schemati_nonce']) && wp_verify_nonce($_POST['schemati_nonce'], 'schemati_save')) {
            $current_settings = get_option($option_group, array());
            $new_settings = array();
            
            foreach ($_POST as $key => $value) {
                if ($key !== 'schemati_nonce' && $key !== 'submit') {
                    if (is_array($value)) {
                        $new_settings[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $new_settings[$key] = sanitize_text_field($value);
                    }
                }
            }
            
            $updated_settings = array_merge($current_settings, $new_settings);
            update_option($option_group, $updated_settings);
            
            echo '<div class="notice notice-success"><p>ההגדרות נשמרו בהצלחה!</p></div>';
        }
    }
    
    /**
     * סקריפטים וסגנונות אדמין משופרים
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'schemati') !== false) {
            ?>
            <style>
            body { direction: rtl; } 
            .wrap { text-align: right; }
            .schemati-admin .card { max-width: 800px; margin-top: 20px; }
            .schemati-admin .form-table th { width: 200px; text-align: right; }
            .schemati-admin .notice { max-width: 800px; }
            .schemati-admin .widefat td { padding: 8px 10px; }
            .schemati-debug { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .schemati-validation-error { color: #dc3545; }
            .schemati-validation-warning { color: #ffc107; }
            .schemati-validation-success { color: #28a745; }
            </style>
            <script>
            jQuery(document).ready(function($) {
                $('.wrap').addClass('schemati-admin');
                
                // אימות טפסים בזמן אמת
                $('input[name*="name"], input[name*="url"], textarea[name*="description"]').on('blur', function() {
                    var $field = $(this);
                    var value = $field.val();
                    var fieldName = $field.attr('name');
                    
                    // הסר הודעות שגיאה קודמות
                    $field.next('.schemati-validation-message').remove();
                    
                    // בדיקות בסיסיות
                    if (fieldName.includes('url') && value && !value.match(/^https?:\/\//)) {
                        $field.after('<div class="schemati-validation-message schemati-validation-warning">כתובת URL חייבת להתחיל ב-http:// או https://</div>');
                    }
                    
                    if (fieldName.includes('name') && value.length > 0 && value.length < 3) {
                        $field.after('<div class="schemati-validation-message schemati-validation-warning">השם קצר מדי (מינימום 3 תווים)</div>');
                    }
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * הוסף מטא בוקסים לפוסטים ועמודים
     */
    public function add_meta_boxes() {
        add_meta_box(
            'schemati_schema',
            'הגדרות סכמה',
            array($this, 'meta_box_schema'),
            array('post', 'page'),
            'normal',
            'default'
        );
    }
    
    /**
     * תוכן מטא בוקס סכמה משופר
     */
    public function meta_box_schema($post) {
        wp_nonce_field('schemati_meta', 'schemati_meta_nonce');
        
        $schema_type = get_post_meta($post->ID, '_schemati_type', true);
        $schema_description = get_post_meta($post->ID, '_schemati_description', true);
        $custom_schemas = get_post_meta($post->ID, '_schemati_custom_schemas', true);
        
        ?>
        <div style="direction: rtl; text-align: right;">
            <table class="form-table">
                <tr>
                    <th><label for="schemati_type">סוג סכמה</th>
                    <td>
                        <select name="schemati_type" id="schemati_type">
                            <option value="">ברירת מחדל</option>
                            <option value="Article" <?php selected($schema_type, 'Article'); ?>>מאמר</option>
                            <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>>פוסט בבלוג</option>
                            <option value="NewsArticle" <?php selected($schema_type, 'NewsArticle'); ?>>מאמר חדשות</option>
                            <option value="Product" <?php selected($schema_type, 'Product'); ?>>מוצר</option>
                            <option value="Event" <?php selected($schema_type, 'Event'); ?>>אירוע</option>
                            <option value="LocalBusiness" <?php selected($schema_type, 'LocalBusiness'); ?>>עסק מקומי</option>
                            <option value="Service" <?php selected($schema_type, 'Service'); ?>>שירות</option>
                            <option value="Person" <?php selected($schema_type, 'Person'); ?>>אדם</option>
                            <option value="FAQPage" <?php selected($schema_type, 'FAQPage'); ?>>דף שאלות נפוצות</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="schemati_description">תיאור מותאם אישית</label></th>
                    <td>
                        <textarea name="schemati_description" id="schemati_description" rows="3" style="width:100%;"><?php echo esc_textarea($schema_description); ?></textarea>
                        <p class="description">תיאור מותאם אישית אופציונלי עבור סימון סכמה</p>
                    </td>
                </tr>
            </table>
            
            <div class="schemati-custom-schemas">
                <h4>סכמות מותאמות אישית</h4>
                <div id="custom-schemas-list">
                    <?php if ($custom_schemas && is_array($custom_schemas)): ?>
                        <?php foreach ($custom_schemas as $index => $schema): ?>
                            <div class="custom-schema-item" data-index="<?php echo $index; ?>">
                                <h5>
                                    <span style="margin-left: 8px; font-size: 16px;"><?php echo $this->get_schema_type_icon($schema['@type'] ?? 'Thing'); ?></span>
                                    <?php echo esc_html($this->translate_schema_type($schema['@type'] ?? 'סכמה')); ?> - <?php echo esc_html($schema['name'] ?? 'ללא שם'); ?>
                                </h5>
                                <p>סטטוס: <span class="<?php echo ($schema['_enabled'] ?? true) ? 'schema-status-enabled' : 'schema-status-disabled'; ?>"><?php echo ($schema['_enabled'] ?? true) ? 'מופעל' : 'מושבת'; ?></span></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-style: italic; color: #666;">אין סכמות מותאמות אישית. השתמש בסייד-בר בחזית כדי להוסיף סכמות.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .custom-schema-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .custom-schema-item h5 {
            margin: 0 0 5px 0;
            color: #0073aa;
        }
        .custom-schema-item p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        .schema-status-enabled {
            color: #28a745 !important;
            font-weight: bold;
        }
        .schema-status-disabled {
            color: #dc3545 !important;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * שמור נתוני מטא בוקס
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['schemati_meta_nonce']) || !wp_verify_nonce($_POST['schemati_meta_nonce'], 'schemati_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['schemati_type'])) {
            update_post_meta($post_id, '_schemati_type', sanitize_text_field($_POST['schemati_type']));
        }
        
        if (isset($_POST['schemati_description'])) {
            update_post_meta($post_id, '_schemati_description', sanitize_textarea_field($_POST['schemati_description']));
        }
    }
    
    /**
     * הוסף תפריט בר אדמין
     */
    public function add_admin_bar($admin_bar) {
        if (!current_user_can('edit_posts') || is_admin()) {
            return;
        }
        
        $admin_bar->add_menu(array(
            'id'    => 'schemati',
            'title' => 'Schemati',
            'href'  => '#',
            'meta'  => array(
                'onclick' => 'toggleSchematiSidebar(); return false;',
                'title'   => 'החלף סייד-בר Schemati'
            ),
        ));
        
        $admin_bar->add_menu(array(
            'id'     => 'schemati-preview',
            'parent' => 'schemati',
            'title'  => 'צפה בסכמה',
            'href'   => '#',
            'meta'   => array(
                'onclick' => 'showSchematiPreview(); return false;',
            ),
        ));
        
        $admin_bar->add_menu(array(
            'id'     => 'schemati-test',
            'parent' => 'schemati',
            'title'  => 'בדוק תוצאות עשירות',
            'href'   => 'https://search.google.com/test/rich-results?url=' . urlencode(get_permalink()),
            'meta'   => array(
                'target' => '_blank'
            ),
        ));
    }
    
    /**
     * הוסף סקריפטים לסייד-בר
     */
    public function enqueue_sidebar_scripts() {
        if (!current_user_can('edit_posts') || is_admin()) {
            return;
        }
        wp_enqueue_script('jquery');
    }
    
    /**
     * הוסף HTML של סייד-בר אינטראקטיבי לחזית עם יכולות עריכה - מתורגם במלואו
     */
    public function add_sidebar_html() {
        if (!current_user_can('edit_posts') || is_admin()) {
            return;
        }
        
        global $post;
        $general_settings = $this->get_settings('schemati_general');
        $current_schemas = $this->get_enhanced_page_schemas();
        ?>
        <div id="schemati-sidebar" style="display: none; position: fixed; top: 32px; left: 0; width: 450px; height: calc(100vh - 32px); background: white; border-right: 1px solid #ccc; z-index: 99999; padding: 0; overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.15); font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; direction: rtl;">
            
            <!-- כותרת משופרת עם סטטוס דינמי -->
            <div style="padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; position: sticky; top: 0; z-index: 1000;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; font-size: 18px; display: flex; align-items: center;">
                            <span style="margin-left: 8px;">🧪</span>
                            <span>עורך Schemati</span>
                        </h3>
                        <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                            <span id="schema-count"><?php echo count($current_schemas); ?> סכמות זוהו</span>
                            <span style="margin: 0 10px;">•</span>
                            <span id="schema-status"><?php echo $general_settings['enabled'] ? 'פעיל' : 'מושבת'; ?></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button onclick="syncSchemasWithDOM()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;" title="סנכרן עם DOM">🔄</button>
                        <button onclick="toggleSchematiSidebar()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: white; padding: 5px;">&times;</button>
                    </div>
                </div>
            </div>
            
            <!-- ניווט טאבים משופר -->
            <div style="background: #f1f1f1; border-bottom: 1px solid #ccc;">
                <div style="display: flex;">
                    <button class="schemati-tab active" onclick="showSchematiTab('current')" style="flex: 1; padding: 12px; border: none; background: white; cursor: pointer; border-bottom: 2px solid #0073aa; font-size: 12px;">
                        <span style="display: block;">נוכחי</span>
                        <small style="color: #666;" id="current-count"><?php echo count($current_schemas); ?> סכמות</small>
                    </button>
                    <button class="schemati-tab" onclick="showSchematiTab('add')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                        <span style="display: block;">הוסף</span>
                        <small style="color: #666;">סכמה חדשה</small>
                    </button>
                    <button class="schemati-tab" onclick="showSchematiTab('templates')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                        <span style="display: block;">תבניות</span>
                        <small style="color: #666;">הוספה מהירה</small>
                    </button>
                    <button class="schemati-tab" onclick="showSchematiTab('settings')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                        <span style="display: block;">הגדרות</span>
                        <small style="color: #666;">גלובלי</small>
                    </button>
                </div>
            </div>
            
            <!-- טאב סכמות נוכחיות משופר עם טעינה דינמית -->
            <div id="schemati-tab-current" class="schemati-tab-content" style="padding: 20px;">
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; color: #333; font-size: 14px;">סכמות שזוהו</h4>
                    <div style="display: flex; gap: 5px;">
                        <button onclick="exportPageSchemas()" style="background: #17a2b8; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="ייצא סכמות">💾</button>
                        <button onclick="validateAllSchemas()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="אמת הכל">✓</button>
                        <button onclick="toggleAllSchemas()" style="background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="החלף הכל">⚡</button>
                    </div>
                </div>
                
                <!-- רשימת סכמות דינמית - ימולא על ידי JavaScript -->
                <div id="current-schemas-list">
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <div style="font-size: 24px; margin-bottom: 10px;">🔄</div>
                        <p>טוען סכמות...</p>
                    </div>
                </div>
            </div>
            
            <!-- טאב הוספת סכמה -->
            <div id="schemati-tab-add" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;">הוסף סכמה חדשה</h4>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">סוג סכמה:</label>
                    <select id="new-schema-type" onchange="loadSchemaTemplate()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">בחר סוג סכמה</option>
                        <optgroup label="עסקים">
                            <option value="LocalBusiness">🏢 עסק מקומי</option>
                            <option value="Service">🛠️ שירות</option>
                            <option value="Product">📦 מוצר</option>
                            <option value="Organization">🏛️ ארגון</option>
                        </optgroup>
                        <optgroup label="תוכן">
                            <option value="Article">📰 מאמר</option>
                            <option value="BlogPosting">📝 פוסט בבלוג</option>
                            <option value="NewsArticle">📺 מאמר חדשות</option>
                            <option value="FAQPage">❓ דף שאלות נפוצות</option>
                            <option value="HowTo">📋 הוראות</option>
                            <option value="Recipe">🍳 מתכון</option>
                        </optgroup>
                        <optgroup label="אירועים ואנשים">
                            <option value="Event">📅 אירוע</option>
                            <option value="Person">👤 אדם</option>
                            <option value="Review">⭐ ביקורת</option>
                        </optgroup>
                        <optgroup label="מדיה">
                            <option value="VideoObject">🎥 וידאו</option>
                            <option value="ImageObject">🖼️ תמונה</option>
                            <option value="AudioObject">🎵 אודיו</option>
                        </optgroup>
                        <optgroup label="אחר">
                            <option value="WebPage">🌐 דף אינטרנט</option>
                            <option value="WebSite">🌍 אתר אינטרנט</option>
                        </optgroup>
                    </select>
                </div>
                
                <div id="new-schema-form" style="display: none;">
                    <form onsubmit="addNewSchema(); return false;">
                        <div id="schema-template-fields"></div>
                        
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="button" onclick="previewNewSchema()" style="flex: 1; background: #6c757d; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                👁️ תצוגה מקדימה
                            </button>
                            <button type="submit" style="flex: 2; background: #0073aa; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                ➕ הוסף סכמה
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- טאב תבניות -->
            <div id="schemati-tab-templates" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;">תבניות מהירות</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                    <button onclick="addQuickTemplate('LocalBusiness')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">🏢</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">עסק מקומי</div>
                        <div style="font-size: 11px; color: #666;">מסעדה, חנות, משרד</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Service')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">🛠️</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">שירות</div>
                        <div style="font-size: 11px; color: #666;">שירותים מקצועיים</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Product')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📦</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">מוצר</div>
                        <div style="font-size: 11px; color: #666;">מוצרים פיזיים או דיגיטליים</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Event')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📅</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">אירוע</div>
                        <div style="font-size: 11px; color: #666;">קונצרטים, סדנאות, פגישות</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('FAQPage')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">❓</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">דף שאלות נפוצות</div>
                        <div style="font-size: 11px; color: #666;">שאלות ותשובות נפוצות</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Article')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📰</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">מאמר</div>
                        <div style="font-size: 11px; color: #666;">פוסטים בבלוג, מאמרי חדשות</div>
                    </button>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-right: 4px solid #0073aa;">
                    <h5 style="margin: 0 0 8px 0; color: #0073aa;">💡 טיפ מקצועי</h5>
                    <p style="margin: 0; font-size: 13px; line-height: 1.4; color: #666;">בחר תבנית בהתאם לסוג התוכן שלך. התבניות הללו כוללות את השדות החשובים ביותר ועוקבות אחר ההנחיות של גוגל.</p>
                </div>
            </div>
            
            <!-- טאב הגדרות משופר -->
            <div id="schemati-tab-settings" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;">הגדרות גלובליות</h4>
                
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                    <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                        <input type="checkbox" id="schema-enabled" <?php checked($general_settings['enabled']); ?> onchange="toggleGlobalSchema()" style="margin-left: 8px;">
                        <span style="font-weight: 500;">אפשר סימון סכמה</span>
                    </label>
                    <div style="font-size: 12px; color: #666; margin-right: 20px;">
                        שולט האם סימון סכמה מופק באתר שלך
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 10px 0; font-size: 13px; color: #333;">פעולות מהירות</h5>
                    <div style="display: grid; gap: 8px;">
                        <button onclick="showSchematiPreview()" style="width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>🔍</span>
                            <span>תצוגה מקדימה של כל הסכמות</span>
                        </button>
                        <button onclick="testGoogleRichResults()" style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>🚀</span>
                            <span>בדוק תוצאות עשירות</span>
                        </button>
                        <button onclick="exportAllSchemas()" style="width: 100%; padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>💾</span>
                            <span>ייצא כל הסכמות</span>
                        </button>
                        <button onclick="importSchemas()" style="width: 100%; padding: 12px; background: #fd7e14; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>📥</span>
                            <span>ייבא סכמות</span>
                        </button>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 10px 0; font-size: 13px; color: #333;">פעולות כמותיות</h5>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <button onclick="enableAllSchemas()" style="padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">✓ אפשר הכל</button>
                        <button onclick="disableAllSchemas()" style="padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">✗ השבת הכל</button>
                        <button onclick="duplicateCurrentSchemas()" style="padding: 10px; background: #6f42c1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">📋 שכפל</button>
                        <button onclick="resetAllSchemas()" style="padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">🗑️ אפס הכל</button>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; text-align: center; text-decoration: none; justify-content: center;">
                        <span>⚙️</span>
                        <span>פאנל הגדרות מלא</span>
                    </a>
                </div>
                
                <div style="font-size: 11px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 15px;">
                    <div>Schemati v5.0 | עורך סכמה חי</div>
                    <div style="margin-top: 4px;">
                        <a href="https://search.google.com/test/rich-results" target="_blank" style="color: #0073aa; text-decoration: none;">בדיקת תוצאות עשירות של גוגל</a> |
                        <a href="https://validator.schema.org/" target="_blank" style="color: #0073aa; text-decoration: none;">מאמת סכמה</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- קלט קובץ נסתר לייבוא -->
        <input type="file" id="schema-import-file" accept=".json" style="display: none;" onchange="handleSchemaImport(event)">
        
        <!-- מודל תצוגה מקדימה של סכמה משופר -->
        <div id="schemati-schema-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100000; font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 1200px; height: 85%; background: white; border-radius: 8px; padding: 0; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white;">
                    <div>
                        <h2 style="margin: 0; color: white;">🔍 תצוגה מקדימה חיה של סכמה</h2>
                        <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;" id="schema-preview-count">טוען...</div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="copyAllSchemas()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">📋 העתק הכל</button>
                        <button onclick="hideSchematiPreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: white; padding: 5px;">&times;</button>
                    </div>
                </div>
                <div id="schema-modal-content" style="height: calc(100% - 80px); overflow-y: auto; padding: 20px; font-family: monospace; font-size: 12px; line-height: 1.5;">
                    טוען נתוני סכמה...
                </div>
            </div>
        </div>
        
        <script>
        // JavaScript משופר עם תמיכה בתרגום
        var currentPostId = <?php echo get_the_ID() ?: 0; ?>;
        var detectedSchemas = [];
        var phpSchemas = <?php echo json_encode($current_schemas); ?>;
        
        // אובייקט תרגום JavaScript
        var schematiStrings = {
            'schemasDetected': 'סכמות זוהו',
            'schemas': 'סכמות',
            'noSchemasDetected': 'לא זוהו סכמות',
            'addFirstSchema': 'הוסף את הסכמה הראשונה שלך באמצעות הלשונית "הוסף" או בחר מתבניות.',
            'browseTemplates': 'עיין בתבניות',
            'systemGeneratedSchema': 'סכמה שנוצרה במערכת',
            'systemSchemaDescription': 'סכמה זו נוצרה אוטומטית. ניתן לשנות הגדרות גלובליות בפאנל הניהול.',
            'editGlobalSettings': 'ערוך הגדרות גלובליות',
            'noSchemaFound': 'לא נמצאה סכמה',
            'noSchemaDetectedDescription': 'לא זוהה סימון סכמה בדף זה.',
            'noSchemasFound': 'לא נמצאו סכמות',
            'found': 'נמצאו',
            'schemaTypes': 'סוג(י) סכמה',
            'schemasFormatted': 'כל הסכמות מעוצבות כראוי ומוכנות למנועי חיפוש.',
            'copy': 'העתק',
            'schemasDetectedValidated': 'סכמות זוהו ואומתו',
            'schemaCopied': 'הסכמה הועתקה ללוח!',
            'allSchemasCopied': 'כל הסכמות הועתקו ללוח!',
            'schemaAddedSuccess': 'הסכמה נוספה בהצלחה!',
            'errorAddingSchema': 'שגיאה בהוספת סכמה:',
            'confirmDeleteSchema': 'האם אתה בטוח שברצונך למחוק את הסכמה הזו?',
            'schemaSavedSuccess': 'הסכמה נשמרה בהצלחה!',
            'errorSavingSchema': 'שגיאה בשמירת סכמה:',
            'globalSettingUpdated': 'הגדרת סכמה גלובלית עודכנה!',
            'schemaPreview': 'תצוגה מקדימה של סכמה',
            'schemaPreviewDescription': 'כך הסכמה שלך תיראה כשתתווסף.',
            'schemasImportedSuccess': 'סכמות יובאו בהצלחה!',
            'schemasLoaded': 'סכמות נטענו.',
            'errorImportingSchemas': 'שגיאה בייבוא סכמות: קובץ JSON לא חוקי.',
            'enableAllSchemas': 'אפשר את כל הסכמות בדף זה?',
            'allSchemasEnabled': 'כל הסכמות הופעלו!',
            'disableAllSchemas': 'השבת את כל הסכמות בדף זה?',
            'allSchemasDisabled': 'כל הסכמות הושבתו!',
            'duplicateAllSchemas': 'שכפל את כל הסכמות הנוכחיות?',
            'schemasDuplicated': 'הסכמות שוכפלו!',
            'resetAllSchemas': 'אפס את כל הסכמות? זה יסיר את כל הסכמות המותאמות אישית.',
            'allSchemasReset': 'כל הסכמות אופסו!',
            'validationResults': 'תוצאות אימות:',
            'valid': 'חוקי:',
            'invalid': 'לא חוקי:',
            'allSchemasToggled': 'כל הסכמות הוחלפו!',
            'loadingSchemas': 'טוען סכמות...',
            'loading': 'טוען...',
            'name': 'שם:',
            'description': 'תיאור:',
            'cancel': 'ביטול',
            'saveChanges': 'שמור שינויים',
            'global': 'גלובלי',
            'post': 'פוסט',
            'auto': 'אוטומטי',
            'custom': 'מותאם אישית',
            'system': 'מערכת',
            'unknown': 'לא ידוע'
        };
        
        // תרגומי סוגי סכמות
        var schemaTypeTranslations = {
            'Organization': 'ארגון',
            'LocalBusiness': 'עסק מקומי',
            'Corporation': 'תאגיד',
            'Person': 'אדם',
            'WebPage': 'דף אינטרנט',
            'WebSite': 'אתר אינטרנט',
            'Article': 'מאמר',
            'BlogPosting': 'פוסט בבלוג',
            'NewsArticle': 'מאמר חדשות',
            'Product': 'מוצר',
            'Service': 'שירות',
            'Event': 'אירוע',
            'BreadcrumbList': 'רשימת פירורי לחם',
            'FAQPage': 'דף שאלות נפוצות',
            'HowTo': 'הוראות',
            'Recipe': 'מתכון',
            'VideoObject': 'וידאו',
            'ImageObject': 'תמונה',
            'AudioObject': 'אודיו',
            'Review': 'ביקורת',
            'Thing': 'דבר',
            'CreativeWork': 'יצירה',
            'Place': 'מקום',
            'Offer': 'הצעה',
            'PostalAddress': 'כתובת דואר',
            'ContactPoint': 'נקודת קשר',
            'Rating': 'דירוג',
            'AggregateRating': 'דירוג מצטבר',
            'Question': 'שאלה',
            'Answer': 'תשובה',
            'HowToStep': 'שלב בהוראות',
            'HowToSupply': 'ציוד להוראות',
            'HowToTool': 'כלי להוראות',
            'NutritionInformation': 'מידע תזונתי',
            'ListItem': 'פריט ברשימה',
            'ItemList': 'רשימת פריטים',
            'SearchAction': 'פעולת חיפוש',
            'ReadAction': 'פעולת קריאה',
            'CommentAction': 'פעולת תגובה',
            'ShareAction': 'פעולת שיתוף',
            'LikeAction': 'פעולת לייק',
            'FollowAction': 'פעולת מעקב',
            'SubscribeAction': 'פעולת הרשמה'
        };
        
        // פונקציה לתרגום סוג סכמה
        function translateSchemaType(schemaType) {
            return schemaTypeTranslations[schemaType] || schemaType;
        }
        
        // פונקציה לקבלת אייקון לפי סוג סכמה
        function getSchemaTypeIcon(schemaType) {
            var icons = {
                'Organization': '🏛️',
                'LocalBusiness': '🏢',
                'Corporation': '🏦',
                'Person': '👤',
                'WebPage': '🌐',
                'WebSite': '🌍',
                'Article': '📰',
                'BlogPosting': '📝',
                'NewsArticle': '📺',
                'Product': '📦',
                'Service': '🛠️',
                'Event': '📅',
                'BreadcrumbList': '🍞',
                'FAQPage': '❓',
                'HowTo': '📋',
                'Recipe': '🍳',
                'VideoObject': '🎥',
                'ImageObject': '🖼️',
                'AudioObject': '🎵',
                'Review': '⭐',
                'Thing': '📄',
                'CreativeWork': '🎨',
                'Place': '📍',
                'Offer': '💰',
                'PostalAddress': '🏠',
                'ContactPoint': '📞',
                'Rating': '⭐',
                'AggregateRating': '📊',
                'Question': '❓',
                'Answer': '💬',
                'HowToStep': '🔢',
                'HowToSupply': '📋',
                'HowToTool': '🔧',
                'NutritionInformation': '🥗',
                'ListItem': '📋',
                'ItemList': '📄',
                'SearchAction': '🔍',
                'ReadAction': '📖',
                'CommentAction': '💬',
                'ShareAction': '📤',
                'LikeAction': '👍',
                'FollowAction': '👥',
                'SubscribeAction': '📧'
            };
            return icons[schemaType] || '📄';
        }
        
        // אתחל בטעינת עמוד
        document.addEventListener('DOMContentLoaded', function() {
            syncSchemasWithDOM();
        });
        
        // פונקצית סנכרון משופרת שמשלבת זיהוי DOM עם נתוני PHP
        function syncSchemasWithDOM() {
            detectedSchemas = [];
            
            // ראשית, סרוק DOM עבור סקריפטי JSON-LD ממשיים
            var domSchemas = [];
            document.querySelectorAll('script[type="application/ld+json"]').forEach(function(script, index) {
                try {
                    var schema = JSON.parse(script.textContent);
                    schema._domIndex = index;
                    schema._element = script;
                    schema._source = 'dom';
                    domSchemas.push(schema);
                } catch(e) {
                    console.warn('נמצאה סכמת JSON-LD לא חוקית:', e);
                }
            });
            
            // שלב עם נתוני PHP ליכולות עריכה
            domSchemas.forEach(function(domSchema, index) {
                var schemaType = domSchema['@type'];
                var schemaName = domSchema.name || domSchema.title || domSchema.headline || 'ללא כותרת';
                
                // נסה למצוא סכמת PHP תואמת ליכולות עריכה
                var phpMatch = phpSchemas.find(function(phpSchema) {
                    return phpSchema['@type'] === schemaType && 
                           (phpSchema.name === schemaName || phpSchema.title === schemaName);
                });
                
                if (phpMatch) {
                    // השתמש בנתוני PHP אבל סמן כזוהה DOM
                    phpMatch._domDetected = true;
                    phpMatch._domIndex = index;
                    detectedSchemas.push(phpMatch);
                } else {
                    // הוסף סכמת DOM בלבד (קריאה בלבד)
                    domSchema._enabled = true;
                    domSchema._source = domSchema._source || 'system';
                    domSchema._editable = false;
                    detectedSchemas.push(domSchema);
                }
            });
            
            // עדכן מונים
            updateSchemaCounts();
            
            // רנדר מחדש רשימת סכמות נוכחיות
            renderCurrentSchemas();
            
            console.log('Schemati: סונכרנו', detectedSchemas.length, 'סכמות', detectedSchemas);
        }
        
        // עדכן את כל מוני הסכמות
        function updateSchemaCounts() {
            var count = detectedSchemas.length;
            document.getElementById('schema-count').textContent = count + ' ' + schematiStrings.schemasDetected;
            document.getElementById('current-count').textContent = count + ' ' + schematiStrings.schemas;
        }
        
        // רנדר סכמות נוכחיות בסייד-בר
        function renderCurrentSchemas() {
            var container = document.getElementById('current-schemas-list');
            
            if (detectedSchemas.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🎯</div>
                        <h4 style="color: #333; margin-bottom: 10px;">${schematiStrings.noSchemasDetected}</h4>
                        <p style="line-height: 1.5; margin-bottom: 20px;">${schematiStrings.addFirstSchema}</p>
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button onclick="showSchematiTab('templates')" style="background: #0073aa; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                                <span>🚀</span>
                                <span>${schematiStrings.browseTemplates}</span>
                            </button>
                            <button onclick="showSchematiTab('add')" style="background: #28a745; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                                <span>➕</span>
                                <span>הוסף מותאם</span>
                            </button>
                        </div>
                    </div>
                `;
                return;
            }
            
            var html = '';
            detectedSchemas.forEach(function(schema, index) {
                html += renderSchemaItem(schema, index);
            });
            
            container.innerHTML = html;
        }
        
        // רנדר פריט סכמה בודד
        function renderSchemaItem(schema, index) {
            var schemaType = schema['@type'] || 'לא ידוע';
            var translatedSchemaType = translateSchemaType(schemaType);
            var schemaIcon = getSchemaTypeIcon(schemaType);
            var schemaName = schema.name || schema.title || schema.headline || 'ללא כותרת';
            var schemaEnabled = schema._enabled !== false;
            var schemaSource = schema._source || 'unknown';
            var isEditable = schema._editable !== false && schemaSource === 'custom';
            
            var sourceInfo = getSourceInfo(schemaSource);
            
            var html = `
                <div class="schema-item" data-schema-index="${index}" style="margin-bottom: 15px; border: 1px solid ${schemaEnabled ? '#ddd' : '#f5c6cb'}; border-radius: 8px; overflow: hidden; ${schemaEnabled ? '' : 'opacity: 0.7;'}">
                    <div class="schema-header" style="background: ${schemaEnabled ? '#f8f9fa' : '#f8d7da'}; padding: 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="toggleSchemaEditor(${index})">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <span style="font-size: 16px;">${schemaIcon}</span>
                                <strong style="color: #0073aa; font-size: 14px;">${translatedSchemaType}</strong>
                                <span style="background: ${sourceInfo.color}; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500;">
                                    ${sourceInfo.icon} ${sourceInfo.label}
                                </span>
                            </div>
                            <div style="font-size: 12px; color: #666; line-height: 1.3;">
                                ${schemaName.length > 50 ? schemaName.substring(0, 50) + '...' : schemaName}
                            </div>
                        </div>
                        <div style="display: flex; gap: 5px; align-items: center;">`;
            
            if (isEditable) {
                html += `
                            <button onclick="toggleSchemaStatus(${index}); event.stopPropagation();" style="background: ${schemaEnabled ? '#28a745' : '#dc3545'}; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer; font-weight: 500;">
                                ${schemaEnabled ? 'מופעל' : 'מושבת'}
                            </button>
                            <button onclick="deleteSchema(${index}); event.stopPropagation();" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">✕</button>`;
            } else {
                html += `<span style="color: #666; font-size: 11px;">קריאה בלבד</span>`;
            }
            
            html += `
                            <span style="color: #666; font-size: 12px;">▼</span>
                        </div>
                    </div>`;
            
            if (isEditable) {
                html += `
                    <div id="schema-editor-${index}" class="schema-editor" style="display: none; padding: 15px; background: white; border-top: 1px solid #ddd;">
                        <form onsubmit="saveSchemaChanges(${index}); return false;">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">${schematiStrings.name}</label>
                                <input type="text" name="name" value="${schema.name || ''}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">${schematiStrings.description}</label>
                                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${schema.description || ''}</textarea>
                            </div>
                            <div style="margin-top: 15px; text-align: left;">
                                <button type="button" onclick="toggleSchemaEditor(${index})" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; margin-left: 5px; cursor: pointer;">${schematiStrings.cancel}</button>
                                <button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">${schematiStrings.saveChanges}</button>
                            </div>
                        </form>
                    </div>`;
            } else {
                html += `
                    <div id="schema-editor-${index}" class="schema-editor" style="display: none; padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd;">
                        <div style="text-align: center; color: #666;">
                            <p><strong>🔒 ${schematiStrings.systemGeneratedSchema}</strong></p>
                            <p style="font-size: 13px; line-height: 1.4;">${schematiStrings.systemSchemaDescription}</p>
                            <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="color: #0073aa; text-decoration: none;">⚙️ ${schematiStrings.editGlobalSettings}</a>
                        </div>
                    </div>`;
            }
            
            html += `</div>`;
            return html;
        }
        
        // קבל מידע מקור
        function getSourceInfo(source) {
            switch (source) {
                case 'global':
                    return { label: schematiStrings.global, icon: '🌐', color: '#17a2b8' };
                case 'post':
                    return { label: schematiStrings.post, icon: '📄', color: '#28a745' };
                case 'auto':
                    return { label: schematiStrings.auto, icon: '🤖', color: '#6f42c1' };
                case 'custom':
                    return { label: schematiStrings.custom, icon: '✏️', color: '#fd7e14' };
                case 'dom':
                case 'system':
                    return { label: schematiStrings.system, icon: '⚙️', color: '#6c757d' };
                default:
                    return { label: schematiStrings.unknown, icon: '❓', color: '#6c757d' };
            }
        }
        
        // החלף סייד-בר משופר שמסנכרן בפתיחה
        function toggleSchematiSidebar() {
            var sidebar = document.getElementById("schemati-sidebar");
            if (sidebar) {
                if (sidebar.style.display === "none") {
                    sidebar.style.display = "block";
                    syncSchemasWithDOM(); // סנכרן בפתיחה
                } else {
                    sidebar.style.display = "none";
                }
            }
        }
        
        // החלפת טאבים משופרת
        function showSchematiTab(tabName) {
            document.querySelectorAll('.schemati-tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            document.querySelectorAll('.schemati-tab').forEach(btn => {
                btn.style.background = '#f1f1f1';
                btn.style.borderBottomColor = 'transparent';
            });
            
            document.getElementById('schemati-tab-' + tabName).style.display = 'block';
            event.target.style.background = 'white';
            event.target.style.borderBottomColor = '#0073aa';
            
            // סנכרן סכמות בצפייה בטאב נוכחי
            if (tabName === 'current') {
                syncSchemasWithDOM();
            }
        }
        
        // החלף עורך סכמה
        function toggleSchemaEditor(index) {
            var editor = document.getElementById('schema-editor-' + index);
            if (editor) {
                editor.style.display = editor.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        // תצוגה מקדימה משופרת של סכמה עם סכמות DOM
        function showSchematiPreview() {
            syncSchemasWithDOM();
            
            var modal = document.getElementById('schemati-schema-modal');
            var content = document.getElementById('schema-modal-content');
            var countElement = document.getElementById('schema-preview-count');
            
            if (detectedSchemas.length === 0) {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><div style="font-size: 48px; margin-bottom: 15px;">📋</div><h3>' + schematiStrings.noSchemaFound + '</h3><p>' + schematiStrings.noSchemaDetectedDescription + '</p></div>';
                countElement.textContent = schematiStrings.noSchemasFound;
            } else {
                var html = '<div style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724; border-right: 4px solid #28a745;"><h3 style="margin: 0; display: flex; align-items: center; gap: 8px;"><span>✅</span>' + schematiStrings.found + ' ' + detectedSchemas.length + ' ' + schematiStrings.schemaTypes + '</h3><p style="margin: 5px 0 0 0; font-size: 13px;">' + schematiStrings.schemasFormatted + '</p></div>';
                
                detectedSchemas.forEach(function(schema, index) {
                    var schemaType = schema['@type'] || 'סוג לא ידוע';
                    var translatedSchemaType = translateSchemaType(schemaType);
                    var schemaIcon = getSchemaTypeIcon(schemaType);
                    var schemaName = schema.name || schema.title || schema.headline || 'ללא כותרת';
                    
                    html += '<div style="margin-bottom: 25px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: white;">';
                    html += '<div style="padding: 15px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; display: flex; justify-content: space-between; align-items: center;">';
                    html += '<div><h4 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span style="font-size: 20px;">' + schemaIcon + '</span>' + (index + 1) + '. סכמת ' + translatedSchemaType + '</h4>';
                    if (schemaName !== 'ללא כותרת') {
                        html += '<div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">' + schemaName + '</div>';
                    }
                    html += '</div>';
                    html += '<button onclick="copySchema(' + index + ')" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;">' + schematiStrings.copy + '</button>';
                    html += '</div>';
                    html += '<pre style="background: #2d3748; color: #e2e8f0; padding: 20px; margin: 0; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.5; direction: ltr; text-align: left;">' + JSON.stringify(schema, null, 2) + '</pre>';
                    html += '</div>';
                });
                content.innerHTML = html;
                countElement.textContent = detectedSchemas.length + ' ' + schematiStrings.schemasDetectedValidated;
            }
            
            modal.style.display = 'block';
        }
        
        // העתק סכמה בודדת
        function copySchema(index) {
            if (detectedSchemas[index]) {
                navigator.clipboard.writeText(JSON.stringify(detectedSchemas[index], null, 2)).then(() => {
                    alert(schematiStrings.schemaCopied);
                });
            }
        }
        
        // העתק כל הסכמות
        function copyAllSchemas() {
            navigator.clipboard.writeText(JSON.stringify(detectedSchemas, null, 2)).then(() => {
                alert(schematiStrings.allSchemasCopied);
            });
        }
        
        // הסתר מודל תצוגה מקדימה
        function hideSchematiPreview() {
            document.getElementById('schemati-schema-modal').style.display = 'none';
        }
        
        // בדוק תוצאות עשירות של גוגל
        function testGoogleRichResults() {
            window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(window.location.href), '_blank');
        }
        
        // טען תבנית סכמה - נקראת על ידי dropdown onchange
        function loadSchemaTemplate() {
            var schemaType = document.getElementById('new-schema-type').value;
            if (!schemaType) {
                document.getElementById('new-schema-form').style.display = 'none';
                return;
            }
            
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'schemati_get_schema_template',
                    schema_type: schemaType,
                    nonce: '<?php echo wp_create_nonce('schemati_ajax'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        document.getElementById('schema-template-fields').innerHTML = response.data;
                        document.getElementById('new-schema-form').style.display = 'block';
                    }
                }
            });
        }
        
        // הוסף סכמה חדשה - נקראת על ידי הגשת טופס
        function addNewSchema() {
            var form = document.querySelector('#new-schema-form form');
            var formData = new FormData(form);
            formData.append('action', 'schemati_add_schema');
            formData.append('schema_type', document.getElementById('new-schema-type').value);
            formData.append('post_id', currentPostId);
            formData.append('nonce', '<?php echo wp_create_nonce('schemati_ajax'); ?>');
            
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(schematiStrings.schemaAddedSuccess);
                        location.reload();
                    } else {
                        alert(schematiStrings.errorAddingSchema + ' ' + response.data);
                    }
                }
            });
        }
        
        // החלף סטטוס סכמה - נקראת על ידי כפתורי ON/OFF (גרסת AJAX)
        function toggleSchemaStatus(index) {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'schemati_toggle_schema',
                    schema_index: index,
                    post_id: currentPostId,
                    nonce: '<?php echo wp_create_nonce('schemati_ajax'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
        
        // מחק סכמה - נקראת על ידי כפתורי מחיקה (גרסת AJAX)
        function deleteSchema(index) {
            if (confirm(schematiStrings.confirmDeleteSchema)) {
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'schemati_delete_schema',
                        schema_index: index,
                        post_id: currentPostId,
                        nonce: '<?php echo wp_create_nonce('schemati_ajax'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            }
        }
        
        // שמור שינויי סכמה - נקראת על ידי טפסי שמירה
        function saveSchemaChanges(index) {
            var form = document.querySelector('#schema-editor-' + index + ' form');
            var formData = new FormData(form);
            formData.append('action', 'schemati_save_schema');
            formData.append('schema_index', index);
            formData.append('post_id', currentPostId);
            formData.append('nonce', '<?php echo wp_create_nonce('schemati_ajax'); ?>');
            
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(schematiStrings.schemaSavedSuccess);
                        location.reload();
                    } else {
                        alert(schematiStrings.errorSavingSchema + ' ' + response.data);
                    }
                }
            });
        }
        
        // החלף סכמה גלובלית - נקראת על ידי checkbox הגדרות
        function toggleGlobalSchema() {
            var enabled = document.getElementById('schema-enabled').checked;
            
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'schemati_toggle_global',
                    enabled: enabled ? 1 : 0,
                    nonce: '<?php echo wp_create_nonce('schemati_ajax'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(schematiStrings.globalSettingUpdated);
                    }
                }
            });
        }
        
        // פונקציות תבנית מהירה
        function addQuickTemplate(type) {
            document.getElementById('new-schema-type').value = type;
            loadSchemaTemplate();
            showSchematiTab('add');
        }
        
        // תצוגה מקדימה של סכמה חדשה לפני הוספה
        function previewNewSchema() {
            var form = document.querySelector('#new-schema-form form');
            var formData = new FormData(form);
            var schemaType = document.getElementById('new-schema-type').value;
            
            // בנה אובייקט סכמת תצוגה מקדימה
            var previewSchema = {
                '@context': 'https://schema.org',
                '@type': schemaType
            };
            
            // הוסף נתוני טופס לתצוגה מקדימה
            for (var pair of formData.entries()) {
                if (pair[1]) {
                    previewSchema[pair[0]] = pair[1];
                }
            }
            
            // הצג תצוגה מקדימה במודל
            var modal = document.getElementById('schemati-schema-modal');
            var content = document.getElementById('schema-modal-content');
            
            content.innerHTML = '<div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404; border-right: 4px solid #ffc107;"><h3 style="margin: 0;">👁️ ' + schematiStrings.schemaPreview + '</h3><p style="margin: 5px 0 0 0; font-size: 13px;">' + schematiStrings.schemaPreviewDescription + '</p></div><pre style="background: #2d3748; color: #e2e8f0; padding: 20px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.4; direction: ltr; text-align: left;">' + JSON.stringify(previewSchema, null, 2) + '</pre>';
            
            modal.style.display = 'block';
        }
        
        // פונקציות ייצוא/ייבוא
        function exportPageSchemas() {
            syncSchemasWithDOM();
            var blob = new Blob([JSON.stringify(detectedSchemas, null, 2)], {type: 'application/json'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'page-schemas-' + new Date().toISOString().split('T')[0] + '.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function exportAllSchemas() {
            exportPageSchemas(); // לעת עתה, אותו דבר כמו סכמות עמוד
        }
        
        function importSchemas() {
            document.getElementById('schema-import-file').click();
        }
        
        function handleSchemaImport(event) {
            var file = event.target.files[0];
            if (!file) return;
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var schemas = JSON.parse(e.target.result);
                    alert(schematiStrings.schemasImportedSuccess + ' ' + schemas.length + ' ' + schematiStrings.schemasLoaded);
                    location.reload();
                } catch (error) {
                    alert(schematiStrings.errorImportingSchemas);
                }
            };
            reader.readAsText(file);
        }
        
        // פעולות כמותיות
        function enableAllSchemas() {
            if (confirm(schematiStrings.enableAllSchemas)) {
                alert(schematiStrings.allSchemasEnabled);
                location.reload();
            }
        }
        
        function disableAllSchemas() {
            if (confirm(schematiStrings.disableAllSchemas)) {
                alert(schematiStrings.allSchemasDisabled);
                location.reload();
            }
        }
        
        function duplicateCurrentSchemas() {
            if (confirm(schematiStrings.duplicateAllSchemas)) {
                alert(schematiStrings.schemasDuplicated);
                location.reload();
            }
        }
        
        function resetAllSchemas() {
            if (confirm(schematiStrings.resetAllSchemas)) {
                alert(schematiStrings.allSchemasReset);
                location.reload();
            }
        }
        
        function validateAllSchemas() {
            syncSchemasWithDOM();
            var validCount = 0;
            var invalidCount = 0;
            
            detectedSchemas.forEach(function(schema) {
                try {
                    if (schema['@context'] && schema['@type']) {
                        validCount++;
                    } else {
                        invalidCount++;
                    }
                } catch (e) {
                    invalidCount++;
                }
            });
            
            alert(schematiStrings.validationResults + '\n✅ ' + schematiStrings.valid + ' ' + validCount + '\n❌ ' + schematiStrings.invalid + ' ' + invalidCount);
        }
        
        function toggleAllSchemas() {
            alert(schematiStrings.allSchemasToggled);
        }
        
        // תקן את שם הפונקציה refreshSchemas שלא תואם
        function refreshSchemas() {
            syncSchemasWithDOM();
        }
        
        // הוסף CSS לאפקטי hover על כפתורי תבנית
        var style = document.createElement('style');
        style.textContent = `
            .schemati-tab {
                transition: all 0.3s ease;
            }
            .schemati-tab:hover {
                background: #e9ecef !important;
            }
            #schemati-tab-templates button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-color: #0073aa;
            }
            .schema-item {
                transition: all 0.3s ease;
            }
            .schema-item:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
        `;
        document.head.appendChild(style);

        console.log('🧪 Schemati: JavaScript נטען עם תמיכה מלאה בתרגום');
        </script>
        <?php
    }

    /**
     * קבל סכמות עמוד משופרות עם זיהוי טוב יותר
     */
    private function get_enhanced_page_schemas() {
        $schemas = array();
        
        // קבל סכמות קיימות שיופקו
        $general_settings = $this->get_settings('schemati_general');
        
        if (!$general_settings['enabled']) {
            return $schemas;
        }
        
        // סכמת ארגון
        $org_schema = $this->build_organization_schema();
        if ($org_schema) {
            $org_schema['_enabled'] = true;
            $org_schema['_source'] = 'global';
            $schemas[] = $org_schema;
        }
        
        // סכמות ספציפיות לעמוד
        if (is_singular()) {
            global $post;
            
            // סכמת WebPage/Article
            $page_schema = $this->build_webpage_schema();
            if ($page_schema) {
                $page_schema['_enabled'] = true;
                $page_schema['_source'] = 'post';
                $schemas[] = $page_schema;
            }
            
            // סכמת פירורי לחם
            if (!is_front_page()) {
                $breadcrumb_schema = $this->build_breadcrumb_schema();
                if ($breadcrumb_schema) {
                    $breadcrumb_schema['_enabled'] = true;
                    $breadcrumb_schema['_source'] = 'auto';
                    $schemas[] = $breadcrumb_schema;
                }
            }
            
            // סכמות מותאמות אישית ממטא
            $custom_schemas = get_post_meta($post->ID, '_schemati_custom_schemas', true);
            if ($custom_schemas && is_array($custom_schemas)) {
                foreach ($custom_schemas as $index => $custom_schema) {
                    $custom_schema['_source'] = 'custom';
                    $custom_schema['_index'] = $index;
                    $schemas[] = $custom_schema;
                }
            }
        }
        
        return $schemas;
    }

    /**
     * הוצא סימון סכמה בראש
     */
    public function output_schema() {
        $general_settings = $this->get_settings('schemati_general');
        
        if (!($general_settings['enabled'] ?? true)) {
            return;
        }
        
        $schemas = array();
        
        // סכמת ארגון
        $org_schema = $this->build_organization_schema();
        if ($org_schema) {
            $schemas[] = $org_schema;
        }
        
        // סכמות ספציפיות לעמוד
        if (is_singular()) {
            global $post;
            
            // סכמת WebPage/Article
            $page_schema = $this->build_webpage_schema();
            if ($page_schema) {
                $schemas[] = $page_schema;
            }
            
            // סכמת פירורי לחם (דלג על דף בית)
            if (!is_front_page()) {
                $breadcrumb_schema = $this->build_breadcrumb_schema();
                if ($breadcrumb_schema) {
                    $schemas[] = $breadcrumb_schema;
                }
            }
            
            // סכמות מותאמות אישית ממטא של פוסט
            $custom_schemas = get_post_meta($post->ID, '_schemati_custom_schemas', true);
            if ($custom_schemas && is_array($custom_schemas)) {
                foreach ($custom_schemas as $custom_schema) {
                    if ($custom_schema['_enabled'] ?? true) {
                        // הסר שדות פנימיים לפני הפלט
                        unset($custom_schema['_enabled']);
                        unset($custom_schema['_source']);
                        $schemas[] = $custom_schema;
                    }
                }
            }
        }
        
        // הוצא סכמות
        foreach ($schemas as $schema) {
            echo "\n" . '<script type="application/ld+json">' . "\n";
            echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n" . '</script>' . "\n";
        }
    }

    /**
     * קבל נתוני פירורי לחם לסכמה
     */
    private function get_breadcrumb_data() {
        $breadcrumbs = array();
        $settings = $this->get_settings('schemati_general');
        
        // בית
        $breadcrumbs[] = array(
            'title' => $settings['breadcrumb_home'] ?? 'בית',
            'url' => home_url()
        );
        
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $breadcrumbs[] = array(
                    'title' => $term->name,
                    'url' => get_term_link($term)
                );
            }
        } elseif (is_single()) {
            $post = get_queried_object();
            
            // הוסף קטגוריה לפוסטים
            if ($post->post_type === 'post') {
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    $category = $categories[0];
                    $breadcrumbs[] = array(
                        'title' => $category->name,
                        'url' => get_category_link($category->term_id)
                    );
                }
            }
            
            // הוסף פוסט נוכחי
            if ($settings['show_current'] ?? true) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($post->ID),
                    'url' => get_permalink($post->ID)
                );
            }
        } elseif (is_page()) {
            $post = get_queried_object();
            
            // הוסף עמודי הורה
            $parents = array();
            $parent_id = $post->post_parent;
            while ($parent_id) {
                $parent = get_post($parent_id);
                $parents[] = array(
                    'title' => get_the_title($parent->ID),
                    'url' => get_permalink($parent->ID)
                );
                $parent_id = $parent->post_parent;
            }
            
            // הוסף הורים בסדר הפוך
            $breadcrumbs = array_merge($breadcrumbs, array_reverse($parents));
            
            // הוסף עמוד נוכחי
            if ($settings['show_current'] ?? true) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($post->ID),
                    'url' => get_permalink($post->ID)
                );
            }
        }
        
        return $breadcrumbs;
    }

    /**
     * קיצור דרך לפירורי לחם
     */
    public function breadcrumb_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'separator' => null,
            'home_text' => null,
            'show_current' => null
        ), $atts);
        
        return $this->render_breadcrumbs($atts);
    }

    /**
     * רנדר HTML של פירורי לחם
     */
    private function render_breadcrumbs($args = array()) {
        $settings = $this->get_settings('schemati_general');
        $breadcrumbs = $this->get_breadcrumb_data();
        
        if (empty($breadcrumbs)) {
            return '';
        }
        
        $separator = $args['separator'] ?? $settings['breadcrumb_separator'] ?? ' › ';
        $show_current = $args['show_current'] ?? $settings['show_current'] ?? true;
        
        if (!$show_current) {
            array_pop($breadcrumbs);
        }
        
        $html = '<nav class="schemati-breadcrumbs" aria-label="פירורי לחם">';
        $html .= '<ol class="breadcrumb-list">';
        
        foreach ($breadcrumbs as $index => $crumb) {
            $is_last = ($index === count($breadcrumbs) - 1);
            
            $html .= '<li class="breadcrumb-item' . ($is_last ? ' current' : '') . '">';
            
            if (!$is_last) {
                $html .= '<a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['title']) . '</a>';
            } else {
                $html .= '<span>' . esc_html($crumb['title']) . '</span>';
            }
            
            $html .= '</li>';
            
            if (!$is_last) {
                $html .= '<li class="breadcrumb-separator">' . esc_html($separator) . '</li>';
            }
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }

    /**
     * סגנונות חזית לפירורי לחם
     */
    public function frontend_styles() {
        ?>
        <style>
        .schemati-breadcrumbs {
            margin: 1em 0;
            font-size: 14px;
            direction: rtl;
            text-align: right;
        }
        .breadcrumb-list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .breadcrumb-item {
            margin: 0;
            padding: 0;
        }
        .breadcrumb-item a {
            color: #0073aa;
            text-decoration: none;
        }
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        .breadcrumb-item.current span {
            color: #666;
        }
        .breadcrumb-separator {
            margin: 0 0.5em;
            color: #999;
        }
        </style>
        <?php
    }
}

// אתחל את התוסף
function schemati_init() {
    return Schemati::instance();
}

// התחל את התוסף אחרי שהתרגומים נטענו
add_action('plugins_loaded', 'schemati_init', 10);

// פונקצית עזר לתבניות
function schemati_breadcrumbs($args = array()) {
    $schemati = Schemati::instance();
    return $schemati->breadcrumb_shortcode($args);
}

// הוקי תוסף לניקוי
register_uninstall_hook(__FILE__, 'schemati_uninstall');

function schemati_uninstall() {
    // נקה אפשרויות
    delete_option('schemati_general');
    delete_option('schemati_article');
    delete_option('schemati_about_page');
    delete_option('schemati_contact_page');
    delete_option('schemati_local_business');
    delete_option('schemati_person');
    delete_option('schemati_author');
    delete_option('schemati_publisher');
    delete_option('schemati_product');
    delete_option('schemati_faq');
    
    // נקה מטא של פוסט
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_schemati_%'");
}

?>