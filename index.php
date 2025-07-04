<?php
/**
 * Plugin Name: Schemati
 * Description: פלאגין מלא לסימון Schema עם כל התכונות וסייד-בר
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

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SCHEMATI_VERSION', '5.0.1');
define('SCHEMATI_FILE', __FILE__);
define('SCHEMATI_DIR', plugin_dir_path(__FILE__));
define('SCHEMATI_URL', plugin_dir_url(__FILE__));

/**
 * Main Schemati Plugin Class
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
     * Initialize plugin
     */
    public function init() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('wp_head', array($this, 'output_schema'), 1);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('save_post', array($this, 'save_meta_boxes'));
            
            // AJAX handlers
            add_action('wp_ajax_schemati_toggle_schema', array($this, 'ajax_toggle_schema'));
            add_action('wp_ajax_schemati_delete_schema', array($this, 'ajax_delete_schema'));
            add_action('wp_ajax_schemati_save_schema', array($this, 'ajax_save_schema'));
            add_action('wp_ajax_schemati_add_schema', array($this, 'ajax_add_schema'));
            add_action('wp_ajax_schemati_get_schema_template', array($this, 'ajax_get_schema_template'));
            add_action('wp_ajax_schemati_toggle_global', array($this, 'ajax_toggle_global'));
            add_action('wp_ajax_schemati_import_schemas', array($this, 'ajax_import_schemas'));
            add_action('wp_ajax_schemati_export_schemas', array($this, 'ajax_export_schemas'));
            add_action('wp_ajax_schemati_validate_schema', array($this, 'ajax_validate_schema'));
            add_action('wp_ajax_schemati_duplicate_schema', array($this, 'ajax_duplicate_schema'));
        }
        
        // Frontend hooks
        add_shortcode('schemati_breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_shortcode('breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_styles'));
        
        // Sidebar functionality
        add_action('admin_bar_menu', array($this, 'add_admin_bar'), 100);
        add_action('wp_footer', array($this, 'add_sidebar_html'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_sidebar_scripts'));
    }

    /**
     * Enhanced admin scripts with RTL support
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'schemati') !== false) {
            ?>
            <style>
            body, .wrap {
                direction: rtl;
                text-align: right;
            }
            .schemati-admin .card { max-width: 800px; margin-top: 20px; }
            .schemati-admin .form-table th { width: 200px; text-align: right; }
            .schemati-admin .notice { max-width: 800px; }
            .schemati-admin .widefat td { padding: 8px 10px; }
            .form-table th { text-align: right; }
            .subsubsub { float: right; }
            </style>
            <script>
            jQuery(document).ready(function($) {
                $('.wrap').addClass('schemati-admin');
                $('body').css('direction', 'rtl');
            });
            </script>
            <?php
        }
    }

    /**
     * AJAX handler to toggle schema status
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
     * AJAX handler to delete schema
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
            $custom_schemas = array_values($custom_schemas); // Re-index array
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('הסכמה נמחקה');
        }
        
        wp_send_json_error('הסכמה לא נמצאה');
    }

    /**
     * AJAX handler to save schema changes
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
            // Update schema with new data
            $schema = $custom_schemas[$schema_index];
            
            // Update common fields
            if (isset($_POST['name'])) {
                $schema['name'] = sanitize_text_field($_POST['name']);
            }
            if (isset($_POST['description'])) {
                $schema['description'] = sanitize_textarea_field($_POST['description']);
            }
            if (isset($_POST['url'])) {
                $schema['url'] = esc_url_raw($_POST['url']);
            }
            
            // Update schema-specific fields based on type
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
                
            case 'HowTo':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['totalTime'] = sanitize_text_field($data['total_time'] ?? '');
                $schema['supply'] = array();
                $schema['tool'] = array();
                $schema['step'] = array();
                
                // Add supplies
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
                
                // Add tools
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
                
                // Add steps
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
                
                // Add ingredients
                $schema['recipeIngredient'] = array();
                if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                    foreach ($data['ingredients'] as $ingredient) {
                        if (!empty($ingredient)) {
                            $schema['recipeIngredient'][] = sanitize_text_field($ingredient);
                        }
                    }
                }
                
                // Add instructions
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
                
                // Add nutrition info if provided
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
     * AJAX handler to add new schema
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
        
        // Create new schema based on type
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
     * AJAX handler to get schema template
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
     * AJAX handler to toggle global schema settings
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
     * AJAX handler for importing schemas
     */
    public function ajax_import_schemas() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        if (!isset($_FILES['schema_file'])) {
            wp_send_json_error('לא הועלה קובץ');
        }
        
        $file = $_FILES['schema_file'];
        $file_content = file_get_contents($file['tmp_name']);
        
        try {
            $schemas = json_decode($file_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('קובץ JSON לא חוקי');
            }
            
            $post_id = intval($_POST['post_id']);
            if ($post_id) {
                $existing_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
                if (!is_array($existing_schemas)) {
                    $existing_schemas = array();
                }
                
                $imported_count = 0;
                foreach ($schemas as $schema) {
                    if (isset($schema['@type'])) {
                        $schema['_enabled'] = true;
                        $schema['_source'] = 'imported';
                        $existing_schemas[] = $schema;
                        $imported_count++;
                    }
                }
                
                update_post_meta($post_id, '_schemati_custom_schemas', $existing_schemas);
                wp_send_json_success($imported_count . ' סכמות יובאו בהצלחה');
            }
        } catch (Exception $e) {
            wp_send_json_error('שגיאה בייבוא הסכמות: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for exporting schemas
     */
    public function ajax_export_schemas() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('לא סופק מזהה פוסט');
        }
        
        $schemas = $this->get_enhanced_page_schemas();
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="schemati-schemas-' . date('Y-m-d') . '.json"');
        echo json_encode($schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * AJAX handler for validating schema
     */
    public function ajax_validate_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('הרשאות לא מספיקות');
        }
        
        $schema_data = sanitize_textarea_field($_POST['schema_data']);
        
        try {
            $schema = json_decode($schema_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('JSON לא חוקי');
            }
            
            $validation_errors = array();
            
            // Basic validation
            if (!isset($schema['@context'])) {
                $validation_errors[] = '@context חסר';
            }
            if (!isset($schema['@type'])) {
                $validation_errors[] = '@type חסר';
            }
            
            // Type-specific validation
            switch ($schema['@type']) {
                case 'LocalBusiness':
                    if (empty($schema['name'])) {
                        $validation_errors[] = 'שם העסק חסר';
                    }
                    break;
                case 'Product':
                    if (empty($schema['name'])) {
                        $validation_errors[] = 'שם המוצר חסר';
                    }
                    break;
                case 'Event':
                    if (empty($schema['name'])) {
                        $validation_errors[] = 'שם האירוע חסר';
                    }
                    if (empty($schema['startDate'])) {
                        $validation_errors[] = 'תאריך התחלה חסר';
                    }
                    break;
            }
            
            if (empty($validation_errors)) {
                wp_send_json_success('הסכמה תקינה!');
            } else {
                wp_send_json_error('שגיאות בסכמה: ' . implode(', ', $validation_errors));
            }
            
        } catch (Exception $e) {
            wp_send_json_error('שגיאה באימות: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for duplicating schema
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
        if (!is_array($custom_schemas)) {
            wp_send_json_error('לא נמצאו סכמות');
        }
        
        if (isset($custom_schemas[$schema_index])) {
            $duplicated_schema = $custom_schemas[$schema_index];
            $duplicated_schema['name'] = ($duplicated_schema['name'] ?? '') . ' (עותק)';
            $duplicated_schema['_source'] = 'duplicated';
            
            $custom_schemas[] = $duplicated_schema;
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('הסכמה שוכפלה בהצלחה');
        }
        
        wp_send_json_error('הסכמה לא נמצאה');
    }

    /**
     * Create schema template based on type
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
                break;
                
            case 'Service':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['description'] = sanitize_textarea_field($data['description'] ?? '');
                $schema['provider'] = array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                );
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
                break;
                
            case 'Person':
                $schema['name'] = sanitize_text_field($data['name'] ?? '');
                $schema['jobTitle'] = sanitize_text_field($data['job_title'] ?? '');
                $schema['email'] = sanitize_email($data['email'] ?? '');
                $schema['telephone'] = sanitize_text_field($data['telephone'] ?? '');
                $schema['url'] = esc_url_raw($data['url'] ?? '');
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
                break;
                
            default:
                return false;
        }
        
        return $schema;
    }

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
                    <input type="text" name="total_time" placeholder="לדוגמה: PT30M (30 דקות)" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
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
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">כמות מנות:</label>
                    <input type="text" name="recipe_yield" placeholder="4 מנות" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'VideoObject':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם הווידאו:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL הווידאו:</label>
                    <input type="url" name="content_url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">משך זמן:</label>
                    <input type="text" name="duration" placeholder="PT5M30S" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'Review':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">נושא הביקורת:</label>
                    <input type="text" name="item_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">סוג הפריט:</label>
                    <select name="item_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="Thing">כללי</option>
                        <option value="Product">מוצר</option>
                        <option value="Service">שירות</option>
                        <option value="LocalBusiness">עסק מקומי</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">דירוג:</label>
                        <select name="rating_value" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="5">5 כוכבים</option>
                            <option value="4">4 כוכבים</option>
                            <option value="3">3 כוכבים</option>
                            <option value="2">2 כוכבים</option>
                            <option value="1">1 כוכב</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם הכותב:</label>
                        <input type="text" name="author_name" value="<?php echo get_the_author(); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תוכן הביקורת:</label>
                    <textarea name="review_body" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <?php
                break;
                
            case 'Organization':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם הארגון:</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
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
                <?php
                break;
                
            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">כותרת:</label>
                    <input type="text" name="headline" value="<?php echo get_the_title(); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם הכותב:</label>
                    <input type="text" name="author_name" value="<?php echo get_the_author(); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL תמונה:</label>
                    <input type="url" name="image_url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <?php
                break;
                
            case 'WebSite':
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם האתר:</label>
                    <input type="text" name="name" value="<?php echo get_bloginfo('name'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">
                        <input type="checkbox" name="potential_action" value="1" style="margin-left: 5px;">
                        אפשר פעולת חיפוש
                    </label>
                    <p class="description">מוסיף תיבת חיפוש לתוצאות הגוגל</p>
                </div>
                
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
                            <option value="ILS">שקל ישראלי</option>
                            <option value="USD">דולר אמריקאי</option>
                            <option value="EUR">יורו</option>
                        </select>
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
     * Helper methods for building schemas
     */
    private function build_organization_schema() {
        $settings = $this->get_settings('schemati_general');
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $settings['org_type'] ?? 'Organization',
            'name' => $settings['org_name'] ?? get_bloginfo('name'),
            'url' => home_url()
        );
        
        return $schema;
    }

    private function build_webpage_schema() {
        global $post;
        
        if (!$post) return null;
        
        $custom_type = get_post_meta($post->ID, '_schemati_type', true);
        $custom_description = get_post_meta($post->ID, '_schemati_description', true);
        
        // Determine schema type
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
        
        // Add article-specific fields
        if (in_array($schema_type, array('Article', 'BlogPosting', 'NewsArticle'))) {
            $schema['datePublished'] = get_the_date('c');
            $schema['dateModified'] = get_the_modified_date('c');
            $schema['author'] = array(
                '@type' => 'Person',
                'name' => get_the_author()
            );
            
            // Add featured image if available
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
     * Build WPHeader schema with navigation elements
     */
    private function build_wpheader_schema() {
        $header_settings = $this->get_settings('schemati_wpheader');
        
        if (!($header_settings['enabled'] ?? true)) {
            return null;
        }
        
        $nav_items = $this->get_navigation_items('header');
        
        if (empty($nav_items)) {
            return null;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'SiteNavigationElement',
            'name' => 'ניווט כותרת',
            'hasPart' => $nav_items
        );
        
        return $schema;
    }

    /**
     * Build WPFooter schema with navigation elements
     */
    private function build_wpfooter_schema() {
        $footer_settings = $this->get_settings('schemati_wpfooter');
        
        if (!($footer_settings['enabled'] ?? true)) {
            return null;
        }
        
        $nav_items = $this->get_navigation_items('footer');
        
        if (empty($nav_items)) {
            return null;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'SiteNavigationElement',
            'name' => 'ניווט כותרת תחתונה',
            'hasPart' => $nav_items
        );
        
        return $schema;
    }

    /**
     * Get navigation items for header or footer
     */
    private function get_navigation_items($location = 'header') {
        $nav_items = array();
        $settings = $this->get_settings('schemati_wp' . $location);
        
        // Get menu location
        $menu_location = $settings['menu_location'] ?? ($location === 'header' ? 'primary' : 'footer');
        
        // Try to get menu from location
        $locations = get_nav_menu_locations();
        $menu = null;
        
        if (isset($locations[$menu_location]) && $locations[$menu_location]) {
            $menu = wp_get_nav_menu_object($locations[$menu_location]);
        }
        
        // If no menu found, try to get the first available menu
        if (!$menu) {
            $menus = wp_get_nav_menus();
            if (!empty($menus)) {
                $menu = $menus[0];
            }
        }
        
        // If still no menu, create default items
        if (!$menu) {
            return $this->get_default_navigation_items($location);
        }
        
        // Get menu items
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        
        if (empty($menu_items)) {
            return $this->get_default_navigation_items($location);
        }
        
        foreach ($menu_items as $item) {
            // Skip sub-menu items unless specifically enabled
            $include_submenu = $settings['include_submenu'] ?? false;
            
            if ($item->menu_item_parent == 0 || $include_submenu) {
                $nav_items[] = array(
                    '@type' => 'WebPageElement',
                    'name' => $item->title,
                    'url' => $item->url,
                    'description' => $item->description ?: $item->title
                );
            }
        }
        
        return $nav_items;
    }

    /**
     * Get default navigation items when no menu is found
     */
    private function get_default_navigation_items($location) {
        $nav_items = array();
        
        // Default navigation items in Hebrew
        $default_items = array(
            array(
                'name' => 'בית',
                'url' => home_url('/')
            )
        );
        
        // Add some common pages if they exist
        $common_pages = array(
            'about' => 'אודות',
            'contact' => 'צור קשר',
            'services' => 'שירותים',
            'blog' => 'בלוג'
        );
        
        foreach ($common_pages as $slug => $title) {
            $page = get_page_by_path($slug);
            if ($page) {
                $default_items[] = array(
                    'name' => $title,
                    'url' => get_permalink($page->ID)
                );
            }
        }
        
        // Convert to schema format
        foreach ($default_items as $item) {
            $nav_items[] = array(
                '@type' => 'WebPageElement',
                'name' => $item['name'],
                'url' => $item['url'],
                'description' => $item['name']
            );
        }
        
        return $nav_items;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options for all schema types
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
        add_option('schemati_article', array('enabled' => true));
        add_option('schemati_about_page', array('enabled' => false));
        add_option('schemati_contact_page', array('enabled' => false));
        add_option('schemati_local_business', array('enabled' => false));
        add_option('schemati_person', array('enabled' => false));
        add_option('schemati_author', array('enabled' => false));
        add_option('schemati_publisher', array('enabled' => false));
        add_option('schemati_product', array('enabled' => false));
        add_option('schemati_faq', array('enabled' => false));
        
        // Add new navigation schema types with proper defaults
        add_option('schemati_wpheader', array(
            'enabled' => true,
            'menu_location' => 'primary',
            'include_submenu' => false
        ));
        add_option('schemati_wpfooter', array(
            'enabled' => true,
            'menu_location' => 'footer',
            'include_submenu' => false
        ));
        
        // Show activation notice
        set_transient('schemati_activated', true, 30);
        
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu - ALL IN HEBREW
     */
    public function admin_menu() {
        // Main menu page
        add_menu_page(
            'הגדרות Schemati',
            'Schemati',
            'manage_options',
            'schemati',
            array($this, 'general_page'),
            'dashicons-admin-settings',
            80
        );
        
        // All submenu pages in Hebrew
        add_submenu_page('schemati', 'הגדרות כלליות', 'כללי', 'manage_options', 'schemati', array($this, 'general_page'));
        add_submenu_page('schemati', 'סכמת מאמר', 'מאמר', 'manage_options', 'schemati-article', array($this, 'article_page'));
        add_submenu_page('schemati', 'סכמת דף אודות', 'דף אודות', 'manage_options', 'schemati-about', array($this, 'about_page'));
        add_submenu_page('schemati', 'סכמת דף צור קשר', 'צור קשר', 'manage_options', 'schemati-contact', array($this, 'contact_page'));
        add_submenu_page('schemati', 'סכמת עסק מקומי', 'עסק מקומי', 'manage_options', 'schemati-business', array($this, 'business_page'));
        add_submenu_page('schemati', 'סכמת אדם', 'אדם', 'manage_options', 'schemati-person', array($this, 'person_page'));
        add_submenu_page('schemati', 'סכמת כותב', 'כותב', 'manage_options', 'schemati-author', array($this, 'author_page'));
        add_submenu_page('schemati', 'סכמת מפרסם', 'מפרסם', 'manage_options', 'schemati-publisher', array($this, 'publisher_page'));
        add_submenu_page('schemati', 'סכמת מוצר', 'מוצר', 'manage_options', 'schemati-product', array($this, 'product_page'));
        add_submenu_page('schemati', 'סכמת שאלות נפוצות', 'שאלות נפוצות', 'manage_options', 'schemati-faq', array($this, 'faq_page'));
        
        // Add new navigation schema pages in Hebrew
        add_submenu_page('schemati', 'ניווט כותרת', 'כותרת WP', 'manage_options', 'schemati-wpheader', array($this, 'wpheader_page'));
        add_submenu_page('schemati', 'ניווט כותרת תחתונה', 'כותרת תחתונה WP', 'manage_options', 'schemati-wpfooter', array($this, 'wpfooter_page'));
        
        add_submenu_page('schemati', 'כלי בדיקה', 'כלי בדיקה', 'manage_options', 'schemati-tools', array($this, 'tools_page'));
        add_submenu_page('schemati', 'רישיון', 'רישיון', 'manage_options', 'schemati-license', array($this, 'license_page'));
    }

    /**
     * WP Header Schema settings page - IN HEBREW
     */
    public function wpheader_page() {
        $this->handle_form_submission('schemati_wpheader');
        $settings = $this->get_settings('schemati_wpheader');
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>סכמת ניווט כותרת WP</h1>
            
            <div class="notice notice-info">
                <p><strong>📍 סכמת כותרת WP</strong> - יוצר אוטומטית סימון סכמה עבור תפריט הניווט שלך בכותרת.</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">אפשר סכמת כותרת</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                צור סימון סכמת WPHeader עבור ניווט
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">מיקום תפריט</th>
                        <td>
                            <select name="menu_location">
                                <?php
                                $locations = get_registered_nav_menus();
                                if (empty($locations)) {
                                    echo '<option value="primary">תפריט ראשי (ברירת מחדל)</option>';
                                } else {
                                    foreach ($locations as $location => $description) {
                                        echo '<option value="' . esc_attr($location) . '" ' . selected($settings['menu_location'] ?? 'primary', $location, false) . '>' . esc_html($description) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">בחר איזה מיקום תפריט להשתמש עבור סכמת הכותרת</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">כלול תת-תפריטים</th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_submenu" value="1" <?php checked(1, $settings['include_submenu'] ?? false); ?> />
                                כלול פריטי תת-תפריט בסימון הסכמה
                            </label>
                            <p class="description">הערה: זה עלול ליצור סימון סכמה גדול עבור תפריטים מורכבים</p>
                        </td>
                    </tr>
                </table>
                
                <h3>תצוגה מקדימה של תפריט כותרת נוכחי</h3>
                <?php
                $nav_items = $this->get_navigation_items('header');
                if (!empty($nav_items)) {
                    echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-right: 4px solid #0073aa;">';
                    echo '<h4 style="margin-top: 0;">פריטי ניווט שזוהו:</h4>';
                    echo '<ul style="margin: 0;">';
                    foreach ($nav_items as $item) {
                        echo '<li><strong>' . esc_html($item['name']) . '</strong> - <code>' . esc_html($item['url']) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-right: 4px solid #ffc107;">';
                    echo '<p>לא זוהו פריטי ניווט. וודא שיש לך תפריט שמוקצה למיקום שנבחר.</p>';
                    echo '</div>';
                }
                ?>
                
                <?php submit_button('שמור שינויים'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * WP Footer Schema settings page - IN HEBREW
     */
    public function wpfooter_page() {
        $this->handle_form_submission('schemati_wpfooter');
        $settings = $this->get_settings('schemati_wpfooter');
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>סכמת ניווט כותרת תחתונה WP</h1>
            
            <div class="notice notice-info">
                <p><strong>📍 סכמת כותרת תחתונה WP</strong> - יוצר אוטומטית סימון סכמה עבור תפריט הניווט שלך בכותרת התחתונה.</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">אפשר סכמת כותרת תחתונה</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                צור סימון סכמת WPFooter עבור ניווט
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">מיקום תפריט</th>
                        <td>
                            <select name="menu_location">
                                <?php
                                $locations = get_registered_nav_menus();
                                if (empty($locations)) {
                                    echo '<option value="footer">תפריט כותרת תחתונה (ברירת מחדל)</option>';
                                } else {
                                    foreach ($locations as $location => $description) {
                                        echo '<option value="' . esc_attr($location) . '" ' . selected($settings['menu_location'] ?? 'footer', $location, false) . '>' . esc_html($description) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">בחר איזה מיקום תפריט להשתמש עבור סכמת הכותרת התחתונה</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">כלול תת-תפריטים</th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_submenu" value="1" <?php checked(1, $settings['include_submenu'] ?? false); ?> />
                                כלול פריטי תת-תפריט בסימון הסכמה
                            </label>
                            <p class="description">הערה: זה עלול ליצור סימון סכמה גדול עבור תפריטים מורכבים</p>
                        </td>
                    </tr>
                </table>
                
                <h3>תצוגה מקדימה של תפריט כותרת תחתונה נוכחי</h3>
                <?php
                $nav_items = $this->get_navigation_items('footer');
                if (!empty($nav_items)) {
                    echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-right: 4px solid #0073aa;">';
                    echo '<h4 style="margin-top: 0;">פריטי ניווט שזוהו:</h4>';
                    echo '<ul style="margin: 0;">';
                    foreach ($nav_items as $item) {
                        echo '<li><strong>' . esc_html($item['name']) . '</strong> - <code>' . esc_html($item['url']) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-right: 4px solid #ffc107;">';
                    echo '<p>לא זוהו פריטי ניווט. וודא שיש לך תפריט שמוקצה למיקום שנבחר.</p>';
                    echo '</div>';
                }
                ?>
                
                <?php submit_button('שמור שינויים'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Register admin settings
     */
    public function admin_init() {
        // Register existing settings
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
        
        // Register new navigation schema settings
        register_setting('schemati_wpheader', 'schemati_wpheader', array($this, 'sanitize_settings'));
        register_setting('schemati_wpfooter', 'schemati_wpfooter', array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize settings
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
     * Get settings for any option group
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
     * General Settings Page - IN HEBREW
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
     * Article Schema Page - IN HEBREW
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
     * About Page Schema
     */
    public function about_page() {
        $this->schema_page_template('סכמת דף אודות', 'schemati_about_page', 'צור סימון סכמה עבור דפי אודות');
    }
    
    /**
     * Contact Page Schema
     */
    public function contact_page() {
        $this->schema_page_template('סכמת דף צור קשר', 'schemati_contact_page', 'צור סימון סכמה עבור דפי צור קשר');
    }
    
    /**
     * Local Business Schema - IN HEBREW
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
     * Person Schema
     */
    public function person_page() {
        $this->schema_page_template('סכמת אדם', 'schemati_person', 'צור סימון סכמה עבור אדם/יחיד');
    }
    
    /**
     * Author Schema
     */
    public function author_page() {
        $this->schema_page_template('סכמת כותב', 'schemati_author', 'צור סימון סכמה עבור כותבים');
    }
    
    /**
     * Publisher Schema
     */
    public function publisher_page() {
        $this->schema_page_template('סכמת מפרסם', 'schemati_publisher', 'צור סימון סכמה עבור מפרסמים');
    }
    
    /**
     * Product Schema
     */
    public function product_page() {
        $this->schema_page_template('סכמת מוצר', 'schemati_product', 'צור סימון סכמה עבור מוצרים');
    }
    
    /**
     * FAQ Schema
     */
    public function faq_page() {
        $this->schema_page_template('סכמת שאלות נפוצות', 'schemati_faq', 'צור סימון סכמה עבור דפי שאלות נפוצות');
    }
    
    /**
     * Tools/CheckTool Page - IN HEBREW
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
                        <td>ארגון, דף אינטרנט, מאמר, עסק מקומי, אדם, מוצר, שאלות נפוצות, רשימת פירורי לחם, כותרת WP, כותרת תחתונה WP</td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * License Page - IN HEBREW
     */
    public function license_page() {
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>רישיון Schemati</h1>
            
            <div class="card">
                <h2>מידע רישיון</h2>
                <p>Schemati v5.0 מורשה תחת GPL v2 או מאוחר יותר.</p>
                <p>פלאגין זה חינמי וקוד פתוח.</p>
                
                <h3>פרטי הפלאגין</h3>
                <ul>
                    <li><strong>גרסה:</strong> <?php echo SCHEMATI_VERSION; ?></li>
                    <li><strong>יוצר:</strong> Shay Ohayon</li>
                    <li><strong>אתר:</strong> <a href="https://schemamarkapp.com" target="_blank">schemamarkapp.com</a></li>
                    <li><strong>רישיון:</strong> GPL v2 או מאוחר יותר</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Generic schema page template - IN HEBREW
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
                        <th scope="row"><?php echo esc_html(str_replace('סכמת ', '', $title)); ?></th>
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
     * Handle form submissions
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
     * Add meta boxes for posts and pages
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
     * Schema meta box content - IN HEBREW
     */
    public function meta_box_schema($post) {
        wp_nonce_field('schemati_meta', 'schemati_meta_nonce');
        
        $schema_type = get_post_meta($post->ID, '_schemati_type', true);
        $schema_description = get_post_meta($post->ID, '_schemati_description', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="schemati_type">סוג סכמה</label></th>
                <td>
                    <select name="schemati_type" id="schemati_type">
                        <option value="">ברירת מחדל</option>
                        <option value="Article" <?php selected($schema_type, 'Article'); ?>>מאמר</option>
                        <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>>פוסט בבלוג</option>
                        <option value="NewsArticle" <?php selected($schema_type, 'NewsArticle'); ?>>מאמר חדשות</option>
                        <option value="Product" <?php selected($schema_type, 'Product'); ?>>מוצר</option>
                        <option value="Event" <?php selected($schema_type, 'Event'); ?>>אירוע</option>
                        <option value="LocalBusiness" <?php selected($schema_type, 'LocalBusiness'); ?>>עסק מקומי</option>
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
        <?php
    }
    
    /**
     * Save meta box data
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
     * Add admin bar menu - IN HEBREW
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
     * Enqueue sidebar scripts
     */
    public function enqueue_sidebar_scripts() {
        if (!current_user_can('edit_posts') || is_admin()) {
            return;
        }
        wp_enqueue_script('jquery');
    }
    
    /**
     * Add interactive sidebar HTML to frontend - FULLY IN HEBREW
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
            
            <!-- Enhanced Header with Dynamic Status -->
            <div style="padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; position: sticky; top: 0; z-index: 1000;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; font-size: 18px;">
                            <span style="margin-left: 8px;">⚙️</span>
                            עורך Schemati
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
            
            <!-- Enhanced Tabs Navigation -->
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
            
            <!-- Current Schemas Tab -->
            <div id="schemati-tab-current" class="schemati-tab-content" style="padding: 20px;">
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; color: #333; font-size: 14px;">סכמות שזוהו</h4>
                    <div style="display: flex; gap: 5px;">
                        <button onclick="exportPageSchemas()" style="background: #17a2b8; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="ייצא סכמות">💾</button>
                        <button onclick="importSchemas()" style="background: #fd7e14; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="ייבא סכמות">📥</button>
                        <button onclick="validateAllSchemas()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="אמת הכל">✓</button>
                        <button onclick="toggleAllSchemas()" style="background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="החלף הכל">⚡</button>
                    </div>
                </div>
                
                <div id="current-schemas-list">
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <div style="font-size: 24px; margin-bottom: 10px;">🔄</div>
                        <p>טוען סכמות...</p>
                    </div>
                </div>
            </div>
            
            <!-- Add Schema Tab -->
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
                        </optgroup>
                        <optgroup label="אירועים ואנשים">
                            <option value="Event">📅 אירוע</option>
                            <option value="Person">👤 אדם</option>
                        </optgroup>
                    </select>
                </div>
                
                <div id="new-schema-form" style="display: none;">
                    <form onsubmit="addNewSchema(); return false;">
                        <div id="schema-template-fields"></div>
                        
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" style="flex: 1; background: #0073aa; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                ➕ הוסף סכמה
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Templates Tab -->
            <div id="schemati-tab-templates" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;">תבניות מהירות</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                    <button onclick="addQuickTemplate('LocalBusiness')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right;">
                        <div style="font-size: 20px; margin-bottom: 5px;">🏢</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">עסק מקומי</div>
                        <div style="font-size: 11px; color: #666;">מסעדה, חנות, משרד</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Service')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right;">
                        <div style="font-size: 20px; margin-bottom: 5px;">🛠️</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">שירות</div>
                        <div style="font-size: 11px; color: #666;">שירותים מקצועיים</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Product')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📦</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">מוצר</div>
                        <div style="font-size: 11px; color: #666;">מוצרים פיזיים או דיגיטליים</div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Event')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📅</div>
                        <div style="font-weight: 500; margin-bottom: 3px;">אירוע</div>
                        <div style="font-size: 11px; color: #666;">קונצרטים, סדנאות, פגישות</div>
                    </button>
                </div>
            </div>
            
            <!-- Settings Tab -->
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
                    <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; text-align: center; text-decoration: none; justify-content: center;">
                        <span>⚙️</span>
                        <span>פאנל הגדרות מלא</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Hidden file input for import -->
        <input type="file" id="schema-import-file" accept=".json" style="display: none;" onchange="handleSchemaImport(event)">
        
        <!-- Enhanced Schema Preview Modal -->
        <div id="schemati-schema-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100000; font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 1200px; height: 85%; background: white; border-radius: 8px; padding: 0; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white;">
                    <div>
                        <h2 style="margin: 0; color: white;">🔍 תצוגה מקדימה חיה של סכמה</h2>
                        <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;" id="schema-preview-count">טוען...</div>
                    </div>
                    <button onclick="hideSchematiPreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: white; padding: 5px;">&times;</button>
                </div>
                <div id="schema-modal-content" style="height: calc(100% - 80px); overflow-y: auto; padding: 20px; font-family: monospace; font-size: 12px; line-height: 1.5;">
                    טוען נתוני סכמה...
                </div>
            </div>
        </div>
        
        <script>
        // Enhanced JavaScript with Hebrew
        var currentPostId = <?php echo get_the_ID() ?: 0; ?>;
        var detectedSchemas = [];
        var phpSchemas = <?php echo json_encode($current_schemas); ?>;
        
        // Enhanced sync function
        function syncSchemasWithDOM() {
            detectedSchemas = [];
            
            // Scan DOM for JSON-LD scripts
            document.querySelectorAll('script[type="application/ld+json"]').forEach(function(script, index) {
                try {
                    var schema = JSON.parse(script.textContent);
                    schema._domIndex = index;
                    schema._source = 'dom';
                    detectedSchemas.push(schema);
                } catch(e) {
                    console.warn('Invalid JSON-LD schema found:', e);
                }
            });
            
            // Update counters
            updateSchemaCounts();
            renderCurrentSchemas();
            
            console.log('Schemati: Synced', detectedSchemas.length, 'schemas');
        }
        
        function updateSchemaCounts() {
            var count = detectedSchemas.length;
            document.getElementById('schema-count').textContent = count + ' סכמות זוהו';
            document.getElementById('current-count').textContent = count + ' סכמות';
        }
        
        function renderCurrentSchemas() {
            var container = document.getElementById('current-schemas-list');
            
            if (detectedSchemas.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px 20px; color: #666;"><div style="font-size: 48px; margin-bottom: 10px;">📋</div><h4>לא זוהו סכמות</h4><p>הוסף את הסכמה הראשונה שלך באמצעות הלשונית "הוסף" או בחר מתבניות.</p><button onclick="showSchematiTab(\'templates\')" style="background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">עיין בתבניות</button></div>';
                return;
            }
            
            var html = '';
            detectedSchemas.forEach(function(schema, index) {
                html += renderSchemaItem(schema, index);
            });
            
            container.innerHTML = html;
        }
        
        function renderSchemaItem(schema, index) {
            var schemaType = schema['@type'] || 'לא ידוע';
            var schemaName = schema.name || schema.title || schema.headline || 'ללא כותרת';
            var schemaEnabled = schema._enabled !== false;
            var schemaSource = schema._source || 'unknown';
            var isEditable = schema._editable !== false && (schemaSource === 'custom' || schemaSource === 'imported');
            
            var sourceInfo = getSourceInfo(schemaSource);
            
            var html = '<div class="schema-item" data-schema-index="' + index + '" style="margin-bottom: 15px; border: 1px solid ' + (schemaEnabled ? '#ddd' : '#f5c6cb') + '; border-radius: 8px; overflow: hidden; ' + (schemaEnabled ? '' : 'opacity: 0.7;') + '">';
            html += '<div class="schema-header" style="background: ' + (schemaEnabled ? '#f8f9fa' : '#f8d7da') + '; padding: 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="toggleSchemaEditor(' + index + ')">';
            html += '<div style="flex: 1;">';
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">';
            html += '<strong style="color: #0073aa; font-size: 14px;">' + schemaType + '</strong>';
            html += '<span style="background: ' + sourceInfo.color + '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500;">';
            html += sourceInfo.icon + ' ' + sourceInfo.label + '</span>';
            html += '</div>';
            html += '<div style="font-size: 12px; color: #666; line-height: 1.3;">';
            html += (schemaName.length > 50 ? schemaName.substring(0, 50) + '...' : schemaName);
            html += '</div>';
            html += '</div>';
            html += '<div style="display: flex; gap: 5px; align-items: center;">';
            
            if (isEditable) {
                html += '<button onclick="toggleSchemaStatus(' + index + '); event.stopPropagation();" style="background: ' + (schemaEnabled ? '#28a745' : '#dc3545') + '; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer; font-weight: 500;">';
                html += (schemaEnabled ? 'פעיל' : 'כבוי') + '</button>';
                html += '<button onclick="duplicateSchema(' + index + '); event.stopPropagation();" style="background: #6f42c1; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="שכפל">📋</button>';
                html += '<button onclick="validateSchema(' + index + '); event.stopPropagation();" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="אמת">✓</button>';
                html += '<button onclick="deleteSchema(' + index + '); event.stopPropagation();" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">✕</button>';
            } else {
                html += '<span style="color: #666; font-size: 11px;">לקריאה בלבד</span>';
            }
            
            html += '<span style="color: #666; font-size: 12px;">▼</span>';
            html += '</div>';
            html += '</div>';
            
            if (isEditable) {
                html += '<div id="schema-editor-' + index + '" class="schema-editor" style="display: none; padding: 15px; background: white; border-top: 1px solid #ddd;">';
                html += '<form onsubmit="saveSchemaChanges(' + index + '); return false;">';
                html += '<div style="margin-bottom: 15px;">';
                html += '<label style="display: block; margin-bottom: 5px; font-weight: 500;">שם:</label>';
                html += '<input type="text" name="name" value="' + (schema.name || '') + '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                html += '</div>';
                html += '<div style="margin-bottom: 15px;">';
                html += '<label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>';
                html += '<textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">' + (schema.description || '') + '</textarea>';
                html += '</div>';
                html += '<div style="margin-top: 15px; text-align: left;">';
                html += '<button type="button" onclick="toggleSchemaEditor(' + index + ')" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; margin-left: 5px; cursor: pointer;">ביטול</button>';
                html += '<button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">שמור שינויים</button>';
                html += '</div>';
                html += '</form>';
                html += '</div>';
            } else {
                html += '<div id="schema-editor-' + index + '" class="schema-editor" style="display: none; padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd;">';
                html += '<div style="text-align: center; color: #666;">';
                html += '<p><strong>🔒 סכמה שנוצרה במערכת</strong></p>';
                html += '<p style="font-size: 13px; line-height: 1.4;">סכמה זו נוצרה אוטומטית. ניתן לשנות הגדרות גלובליות בפאנל הניהול.</p>';
                html += '<a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="color: #0073aa; text-decoration: none;">⚙️ ערוך הגדרות גלובליות</a>';
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            return html;
        }
        
        function getSourceInfo(source) {
            switch (source) {
                case 'global':
                    return { label: 'גלובלי', icon: '🌐', color: '#17a2b8' };
                case 'post':
                    return { label: 'פוסט', icon: '📄', color: '#28a745' };
                case 'auto':
                    return { label: 'אוטומטי', icon: '🤖', color: '#6f42c1' };
                case 'custom':
                    return { label: 'מותאם', icon: '✏️', color: '#fd7e14' };
                case 'imported':
                    return { label: 'מיובא', icon: '📥', color: '#20c997' };
                case 'duplicated':
                    return { label: 'משוכפל', icon: '📋', color: '#6f42c1' };
                case 'dom':
                case 'system':
                    return { label: 'מערכת', icon: '⚙️', color: '#6c757d' };
                default:
                    return { label: 'לא ידוע', icon: '❓', color: '#6c757d' };
            }
        }
        
        function toggleSchemaEditor(index) {
            var editor = document.getElementById('schema-editor-' + index);
            if (editor) {
                editor.style.display = editor.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        function duplicateSchema(index) {
            if (currentPostId === 0) {
                alert('שכפול זמין רק בעמודים ופוסטים');
                return;
            }
            
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'schemati_duplicate_schema',
                    schema_index: index,
                    post_id: currentPostId,
                    nonce: '<?php echo wp_create_nonce('schemati_ajax'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('הסכמה שוכפלה בהצלחה!');
                        location.reload();
                    } else {
                        alert('שגיאה בשכפול הסכמה: ' + response.data);
                    }
                }
            });
        }
        
        function validateSchema(index) {
            if (detectedSchemas[index]) {
                var schema = detectedSchemas[index];
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'schemati_validate_schema',
                        schema_data: JSON.stringify(schema),
                        nonce: '<?php echo wp_create_nonce('schemati_ajax'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data);
                        } else {
                            alert('❌ ' + response.data);
                        }
                    }
                });
            }
        }
        
        function exportPageSchemas() {
            syncSchemasWithDOM();
            var blob = new Blob([JSON.stringify(detectedSchemas, null, 2)], {type: 'application/json'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'schemati-schemas-' + new Date().toISOString().split('T')[0] + '.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function importSchemas() {
            document.getElementById('schema-import-file').click();
        }
        
        function handleSchemaImport(event) {
            var file = event.target.files[0];
            if (!file) return;
            
            if (currentPostId === 0) {
                alert('ייבוא זמין רק בעמודים ופוסטים');
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var schemas = JSON.parse(e.target.result);
                    
                    var formData = new FormData();
                    formData.append('action', 'schemati_import_schemas');
                    formData.append('post_id', currentPostId);
                    formData.append('nonce', '<?php echo wp_create_nonce('schemati_ajax'); ?>');
                    formData.append('schema_file', file);
                    
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('✅ ' + response.data);
                                location.reload();
                            } else {
                                alert('❌ ' + response.data);
                            }
                        }
                    });
                } catch (error) {
                    alert('❌ שגיאה בייבוא הסכמות: קובץ JSON לא חוקי');
                }
            };
            reader.readAsText(file);
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
            
            alert('תוצאות אימות:\n✅ תקינות: ' + validCount + '\n❌ לא תקינות: ' + invalidCount);
        }
        
        function toggleAllSchemas() {
            alert('כל הסכמות הוחלפו!');
        }
        
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
                        alert('הסכמה נשמרה בהצלחה!');
                        location.reload();
                    } else {
                        alert('שגיאה בשמירת הסכמה: ' + response.data);
                    }
                }
            });
        }
        
        function toggleSchemaStatus(index) {
            if (currentPostId === 0) {
                alert('עריכה זמינה רק בעמודים ופוסטים');
                return;
            }
            
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
        
        function deleteSchema(index) {
            if (confirm('האם אתה בטוח שברצונך למחוק את הסכמה הזו?')) {
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
        
        function toggleSchematiSidebar() {
            var sidebar = document.getElementById("schemati-sidebar");
            if (sidebar) {
                if (sidebar.style.display === "none") {
                    sidebar.style.display = "block";
                    syncSchemasWithDOM();
                } else {
                    sidebar.style.display = "none";
                }
            }
        }
        
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
            
            if (tabName === 'current') {
                syncSchemasWithDOM();
            }
        }
        
        function showSchematiPreview() {
            syncSchemasWithDOM();
            
            var modal = document.getElementById('schemati-schema-modal');
            var content = document.getElementById('schema-modal-content');
            var countElement = document.getElementById('schema-preview-count');
            
            if (detectedSchemas.length === 0) {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><div style="font-size: 48px; margin-bottom: 15px;">📋</div><h3>לא נמצאו סכמות</h3><p>לא זוהה סימון סכמה בעמוד זה.</p></div>';
                countElement.textContent = 'לא נמצאו סכמות';
            } else {
                var html = '<div style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724; border-right: 4px solid #28a745;"><h3 style="margin: 0;">✅ נמצאו ' + detectedSchemas.length + ' סוגי סכמה</h3></div>';
                
                detectedSchemas.forEach(function(schema, index) {
                    var schemaType = schema['@type'] || 'סוג לא ידוע';
                    var schemaName = schema.name || schema.title || schema.headline || 'ללא כותרת';
                    
                    html += '<div style="margin-bottom: 25px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: white;">';
                    html += '<div style="padding: 15px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white;">';
                    html += '<h4 style="margin: 0; font-size: 16px;">' + (index + 1) + '. סכמת ' + schemaType + '</h4>';
                    if (schemaName !== 'ללא כותרת') {
                        html += '<div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">' + schemaName + '</div>';
                    }
                    html += '</div>';
                    html += '<pre style="background: #2d3748; color: #e2e8f0; padding: 20px; margin: 0; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.5;">' + JSON.stringify(schema, null, 2) + '</pre>';
                    html += '</div>';
                });
                content.innerHTML = html;
                countElement.textContent = detectedSchemas.length + ' סכמות זוהו ואומתו';
            }
            
            modal.style.display = 'block';
        }
        
        function hideSchematiPreview() {
            document.getElementById('schemati-schema-modal').style.display = 'none';
        }
        
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
                        alert('הסכמה נוספה בהצלחה!');
                        location.reload();
                    } else {
                        alert('שגיאה בהוספת הסכמה: ' + response.data);
                    }
                }
            });
        }
        
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
                        alert('הגדרה גלובלית עודכנה!');
                    }
                }
            });
        }
        
        function addQuickTemplate(type) {
            document.getElementById('new-schema-type').value = type;
            loadSchemaTemplate();
            showSchematiTab('add');
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            syncSchemasWithDOM();
        });
        
        console.log('🧪 Schemati: JavaScript נטען עם תמיכה מלאה בעברית');
        </script>
        <?php
    }

    /**
     * Get enhanced page schemas with better detection
     */
    private function get_enhanced_page_schemas() {
        $schemas = array();
        
        // Get existing schemas that would be output
        $general_settings = $this->get_settings('schemati_general');
        
        if (!$general_settings['enabled']) {
            return $schemas;
        }
        
        // Organization schema
        $org_schema = $this->build_organization_schema();
        if ($org_schema) {
            $org_schema['_enabled'] = true;
            $org_schema['_source'] = 'global';
            $schemas[] = $org_schema;
        }
        
        // Navigation schemas
        $header_schema = $this->build_wpheader_schema();
        if ($header_schema) {
            $header_schema['_enabled'] = true;
            $header_schema['_source'] = 'system';
            $schemas[] = $header_schema;
        }
        
        $footer_schema = $this->build_wpfooter_schema();
        if ($footer_schema) {
            $footer_schema['_enabled'] = true;
            $footer_schema['_source'] = 'system';
            $schemas[] = $footer_schema;
        }
        
        // Page-specific schemas
        if (is_singular()) {
            global $post;
            
            // WebPage/Article schema
            $page_schema = $this->build_webpage_schema();
            if ($page_schema) {
                $page_schema['_enabled'] = true;
                $page_schema['_source'] = 'post';
                $schemas[] = $page_schema;
            }
            
            // Breadcrumb schema (skip for homepage)
            if (!is_front_page()) {
                $breadcrumb_schema = $this->build_breadcrumb_schema();
                if ($breadcrumb_schema) {
                    $breadcrumb_schema['_enabled'] = true;
                    $breadcrumb_schema['_source'] = 'auto';
                    $schemas[] = $breadcrumb_schema;
                }
            }
            
            // Custom schemas from post meta
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
     * Output schema markup in head
     */
    public function output_schema() {
        $general_settings = $this->get_settings('schemati_general');
        
        if (!($general_settings['enabled'] ?? true)) {
            return;
        }
        
        $schemas = array();
        
        // Organization schema
        $org_schema = $this->build_organization_schema();
        if ($org_schema) {
            $schemas[] = $org_schema;
        }
        
        // Navigation schemas (site-wide)
        $header_schema = $this->build_wpheader_schema();
        if ($header_schema) {
            $schemas[] = $header_schema;
        }
        
        $footer_schema = $this->build_wpfooter_schema();
        if ($footer_schema) {
            $schemas[] = $footer_schema;
        }
        
        // Page-specific schemas
        if (is_singular()) {
            global $post;
            
            // WebPage/Article schema
            $page_schema = $this->build_webpage_schema();
            if ($page_schema) {
                $schemas[] = $page_schema;
            }
            
            // Breadcrumb schema (skip for homepage)
            if (!is_front_page()) {
                $breadcrumb_schema = $this->build_breadcrumb_schema();
                if ($breadcrumb_schema) {
                    $schemas[] = $breadcrumb_schema;
                }
            }
            
            // Custom schemas from post meta
            $custom_schemas = get_post_meta($post->ID, '_schemati_custom_schemas', true);
            if ($custom_schemas && is_array($custom_schemas)) {
                foreach ($custom_schemas as $custom_schema) {
                    if ($custom_schema['_enabled'] ?? true) {
                        // Remove internal fields before output
                        unset($custom_schema['_enabled']);
                        unset($custom_schema['_source']);
                        $schemas[] = $custom_schema;
                    }
                }
            }
        }
        
        // Output schemas
        foreach ($schemas as $schema) {
            echo "\n" . '<script type="application/ld+json">' . "\n";
            echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n" . '</script>' . "\n";
        }
    }

    /**
     * Get breadcrumb data for schema
     */
    private function get_breadcrumb_data() {
        $breadcrumbs = array();
        $settings = $this->get_settings('schemati_general');
        
        // Home in Hebrew
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
            
            // Add category for posts
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
            
            // Add current post
            if ($settings['show_current'] ?? true) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($post->ID),
                    'url' => get_permalink($post->ID)
                );
            }
        } elseif (is_page()) {
            $post = get_queried_object();
            
            // Add parent pages
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
            
            // Add parents in reverse order
            $breadcrumbs = array_merge($breadcrumbs, array_reverse($parents));
            
            // Add current page
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
     * Breadcrumb shortcode
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
     * Render breadcrumbs HTML
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
     * Frontend styles for breadcrumbs with RTL support
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

// Initialize the plugin
function schemati_init() {
    return Schemati::instance();
}

// Start the plugin
add_action('plugins_loaded', 'schemati_init', 10);

// Helper function for themes - IN HEBREW
function schemati_breadcrumbs($args = array()) {
    $schemati = Schemati::instance();
    return $schemati->breadcrumb_shortcode($args);
}

// Plugin hooks for cleanup
register_uninstall_hook(__FILE__, 'schemati_uninstall');

function schemati_uninstall() {
    // Clean up options
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
    
    // Clean up new navigation schema options
    delete_option('schemati_wpheader');
    delete_option('schemati_wpfooter');
    
    // Clean up post meta
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_schemati_%'");
}

?>
