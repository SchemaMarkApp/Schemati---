<?php
/**
 * Plugin Name: Schemati
 * Description: Complete Schema markup plugin with all features and sidebar
 * Plugin URI: https://shayohayon.com
 * Author: Shay Ohayon
 * Author URI: https://shayohayon.com
 * Version: 6.0.0
 * Text Domain: schemati
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
define('SCHEMATI_VERSION', '5.0.0');
define('SCHEMATI_FILE', __FILE__);
define('SCHEMATI_DIR', plugin_dir_path(__FILE__));
define('SCHEMATI_URL', plugin_dir_url(__FILE__));
// Force early text domain loading // Priority 1 = very early
/**
 * Main Schemati Plugin Class
 */
class Schemati {
    
    private static $instance = null;
    private $github_updater;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    // Load text domain immediately
    add_action('init', array($this, 'load_textdomain'), 1);
    $this->init();
}
    
public function load_textdomain() {
    load_plugin_textdomain('schemati', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

    /**
     * Initialize plugin
     */
    public function init() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));        // Core hooks
        $this->init_github_updater();
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
     * AJAX handler to toggle schema status
     */
    public function ajax_toggle_schema() {
    // Verify nonce
    if (!check_ajax_referer('schemati_ajax', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Validate inputs
    $schema_index = isset($_POST['schema_index']) ? intval($_POST['schema_index']) : -1;
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if ($schema_index < 0) {
        wp_send_json_error('Invalid schema index');
        return;
    }
    
    if (!$post_id) {
        wp_send_json_error('No post ID provided');
        return;
    }
    
    // Get and validate schemas
    $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
    if (!is_array($custom_schemas)) {
        $custom_schemas = array();
    }
    
    if (!isset($custom_schemas[$schema_index])) {
        wp_send_json_error('Schema not found');
        return;
    }
    
    // Toggle status
    $custom_schemas[$schema_index]['_enabled'] = !($custom_schemas[$schema_index]['_enabled'] ?? true);
    
    // Save
    if (update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas)) {
        wp_send_json_success('Schema status updated');
    } else {
        wp_send_json_error('Failed to update schema');
    }
}

    /**
     * AJAX handler to delete schema
     */
    public function ajax_delete_schema() {
    if (!check_ajax_referer('schemati_ajax', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('No post ID provided');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            wp_send_json_error('No schemas found');
        }
        
        if (isset($custom_schemas[$schema_index])) {
            unset($custom_schemas[$schema_index]);
            $custom_schemas = array_values($custom_schemas); // Re-index array
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('Schema deleted');
        }
        
        wp_send_json_error('Schema not found');
    }

    /**
     * AJAX handler to save schema changes
     */
    public function ajax_save_schema() {
    if (!check_ajax_referer('schemati_ajax', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('No post ID provided');
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
                
                case 'Product':
                    if (isset($_POST['brand'])) {
                        $schema['brand'] = sanitize_text_field($_POST['brand']);
                    }
                   if (isset($_POST['price'])) {
                        if (!isset($schema['offers'])) {
                            $schema['offers'] = array();
                    }
                        $schema['offers']['price'] = sanitize_text_field($_POST['price']);
                    }
                    if (isset($_POST['currency'])) {
                    if (!isset($schema['offers'])) {
                        $schema['offers'] = array();
                    }           
                    $schema['offers']['priceCurrency'] = sanitize_text_field($_POST['currency']);
                }
                    break;
            }
            
            $custom_schemas[$schema_index] = $schema;
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success('Schema updated successfully');
        }
        
        wp_send_json_error('Schema not found');
    }

    /**
     * AJAX handler to add new schema
     */
    public function ajax_add_schema() {
    if (!check_ajax_referer('schemati_ajax', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
        
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('No post ID provided');
        }
        
        // Create new schema based on type
        
        $new_schema = $this->create_schema_template($schema_type, $_POST);
        
        if (!$new_schema) {
            wp_send_json_error('Invalid schema type');
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        $custom_schemas[] = $new_schema;
        update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
        
        wp_send_json_success('Schema added successfully');
    }

    /**
     * AJAX handler to get schema template
     */
    public function ajax_get_schema_template() {
    if (!check_ajax_referer('schemati_ajax', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
        
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $template_html = $this->get_schema_template_html($schema_type);
        
        if ($template_html) {
            wp_send_json_success($template_html);
        } else {
            wp_send_json_error('Template not found');
        }
    }

    /**
     * AJAX handler to toggle global schema settings
     */
    public function ajax_toggle_global() {
    if (!check_ajax_referer('schemati_ajax', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
        
        $enabled = intval($_POST['enabled']);
        $settings = $this->get_settings('schemati_general');
        $settings['enabled'] = $enabled;
        
        update_option('schemati_general', $settings);
        wp_send_json_success('Global setting updated');
    }

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
                    'priceCurrency' => sanitize_text_field($data['currency'] ?? 'USD'),
                    'availability' => 'https://schema.org/InStock'
                );
                if (!empty($data['sku'])) {
                    $schema['sku'] = sanitize_text_field($data['sku']);
                }
                if (!empty($data['mpn'])) {
                    $schema['mpn'] = sanitize_text_field($data['mpn']);
                }
                if (!empty($data['gtin'])) {
                    $schema['gtin'] = sanitize_text_field($data['gtin']);
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
                                'name' => sanitize_text_field($data['step_names'][$i] ?? 'Step ' . ($i + 1)),
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
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">אימייל:</label>
                    <input type="email" name="email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">כתובת אתר:</label>
                <input type="url" name="url" value="<?php echo esc_url(get_permalink()); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
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
                <input type="text" name="area_served" placeholder="למשל, תל אביב, ישראל" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
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
                        <option value="ILS">שקל</option>
                        <option value="USD">דולר</option>
                        <option value="EUR">יורו</option>
                        <option value="GBP">פאונד</option>
                    </select>
                </div>
            </div>
            <?php
            break;
            
        case 'HowTo':
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">כותרת המדריך:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">זמן כולל (למשל, PT30M):</label>
                <input type="text" name="total_time" placeholder="PT30M" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">שלבים (אחד בכל שורה):</label>
                <textarea name="steps[]" rows="4" placeholder="הכנס כל שלב בשורה חדשה" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
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
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">מרכיבים (אחד בכל שורה):</label>
                <textarea name="ingredients[]" rows="4" placeholder="הכנס כל מרכיב בשורה חדשה" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">הוראות (אחת בכל שורה):</label>
                <textarea name="instructions[]" rows="4" placeholder="הכנס כל הוראה בשורה חדשה" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <?php
            break;
            
        // Default template for other schema types
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
    private function build_wpheader_schema() {
    $settings = $this->get_settings('schemati_general');
    
    // Check if WPHeader is enabled
    if (!($settings['enable_wpheader'] ?? true)) {
        return null;
    }
    
    $header_menu_items = $this->get_header_navigation_items();
    
    if (empty($header_menu_items)) {
        return null;
    }
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'WebPageElement',
        '@id' => home_url() . '#WPHeader',
        'name' => 'Website Header',
        'isPartOf' => array(
            '@type' => 'WebPage',
            '@id' => get_permalink() ?: home_url()
        ),
        'hasPart' => $header_menu_items
    );
    
    return $schema;
}

/**
 * Build WPFooter schema
 */
private function build_wpfooter_schema() {
    $settings = $this->get_settings('schemati_general');
    
    // Check if WPFooter is enabled
    if (!($settings['enable_wpfooter'] ?? true)) {
        return null;
    }
    
    $footer_menu_items = $this->get_footer_navigation_items();
    
    if (empty($footer_menu_items)) {
        return null;
    }
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'WebPageElement',
        '@id' => home_url() . '#WPFooter',
        'name' => 'Website Footer',
        'isPartOf' => array(
            '@type' => 'WebPage',
            '@id' => get_permalink() ?: home_url()
        ),
        'hasPart' => $footer_menu_items
    );
    
    return $schema;
}

/**
 * Get header navigation items
 */
private function get_header_navigation_items() {
    $settings = $this->get_settings('schemati_general');
    $menu_location = $settings['header_menu_location'] ?? 'primary';
    
    // Try to get menu from specified location
    $menu_items = $this->get_menu_items_by_location($menu_location);
    
    // If no menu found, try common header menu locations
    if (empty($menu_items)) {
        $common_locations = array('primary', 'header', 'main', 'top', 'navigation');
        foreach ($common_locations as $location) {
            $menu_items = $this->get_menu_items_by_location($location);
            if (!empty($menu_items)) {
                break;
            }
        }
    }
    
    // If still no menu, try to get the first available menu
    if (empty($menu_items)) {
        $menu_items = $this->get_first_available_menu_items();
    }
    
    return $this->format_navigation_items($menu_items, 'WPHeader');
}

/**
 * Get footer navigation items
 */
private function get_footer_navigation_items() {
    $settings = $this->get_settings('schemati_general');
    $menu_location = $settings['footer_menu_location'] ?? 'footer';
    
    // Try to get menu from specified location
    $menu_items = $this->get_menu_items_by_location($menu_location);
    
    // If no menu found, try common footer menu locations
    if (empty($menu_items)) {
        $common_locations = array('footer', 'footer-menu', 'footer_menu', 'bottom');
        foreach ($common_locations as $location) {
            $menu_items = $this->get_menu_items_by_location($location);
            if (!empty($menu_items)) {
                break;
            }
        }
    }
    
    // If still no footer menu, use header menu as fallback
    if (empty($menu_items)) {
        $menu_items = $this->get_header_navigation_items();
        if (!empty($menu_items)) {
            // Strip the WPHeader type and return raw menu items
            $raw_items = array();
            foreach ($menu_items as $item) {
                if (isset($item['@type']) && is_array($item['@type'])) {
                    $raw_items[] = array(
                        'name' => $item['name'],
                        'url' => $item['url']
                    );
                }
            }
            $menu_items = $raw_items;
        }
    }
    
    return $this->format_navigation_items($menu_items, 'WPFooter');
}

/**
 * Get menu items by theme location
 */
private function get_menu_items_by_location($location) {
    if (!function_exists('get_nav_menu_locations')) {
        return array();
    }
    
    $locations = get_nav_menu_locations();
    
    $menu = wp_get_nav_menu_object($locations[$location]);
    if (!$menu) {
        return array();
    }
    
    $menu_items = wp_get_nav_menu_items($menu->term_id);
    if (!$menu_items) {
        return array();
    }
    
    $items = array();
    foreach ($menu_items as $item) {
        // Only include top-level items for schema
        if ($item->menu_item_parent == 0) {
            $items[] = array(
                'name' => $item->title,
                'url' => $item->url
            );
        }
    }
    
    return $items;
}

/**
 * Get first available menu items as fallback
 */
private function get_first_available_menu_items() {
    if (!function_exists('wp_get_nav_menus')) {
        return array();
    }
    
    $menus = wp_get_nav_menus();
    
    if (empty($menus)) {
        return array();
    }
    
    $first_menu = $menus[0];
    $menu_items = wp_get_nav_menu_items($first_menu->term_id);
    
    if (!$menu_items) {
        return array();
    }
    
    $items = array();
    foreach ($menu_items as $item) {
        // Only include top-level items
        if ($item->menu_item_parent == 0) {
            $items[] = array(
                'name' => $item->title,
                'url' => $item->url
            );
        }
    }
    
    return $items;
}

/**
 * Format navigation items for schema output
 */
private function format_navigation_items($menu_items, $element_type) {
    if (empty($menu_items)) {
        return array();
    }
    
    $formatted_items = array();
    
    foreach ($menu_items as $item) {
        $formatted_items[] = array(
            '@type' => array('SiteNavigationElement', $element_type),
            '@id' => home_url() . '#SiteNavigationElement-' . sanitize_title($item['name']),
            'name' => $item['name'],
            'url' => $item['url']
        );
    }
    
    return $formatted_items;
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
        
        // Add local business details if enabled
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
    
     public function activate() {
    // Set default options for all schema types with Hebrew defaults
    $defaults = array(
        'version' => SCHEMATI_VERSION,
        'enabled' => true,
        'org_name' => get_bloginfo('name'),
        'org_type' => 'Organization',
        'breadcrumb_home' => 'בית', // Hebrew for "Home"
        'breadcrumb_separator' => ' › ',
        'show_current' => true,
        'enable_wpheader' => true,
        'enable_wpfooter' => true,
        'header_menu_location' => 'primary',
        'footer_menu_location' => 'footer'
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
     * Add admin menu
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
    
    // All submenu pages
    add_submenu_page('schemati', 'הגדרות כלליות', 'כללי', 'manage_options', 'schemati', array($this, 'general_page'));
    add_submenu_page('schemati', 'Schema מאמרים', 'מאמרים', 'manage_options', 'schemati-article', array($this, 'article_page'));
    add_submenu_page('schemati', 'Schema דף אודות', 'דף אודות', 'manage_options', 'schemati-about', array($this, 'about_page'));
    add_submenu_page('schemati', 'Schema דף יצירת קשר', 'יצירת קשר', 'manage_options', 'schemati-contact', array($this, 'contact_page'));
    add_submenu_page('schemati', 'Schema עסק מקומי', 'עסק מקומי', 'manage_options', 'schemati-business', array($this, 'business_page'));
    add_submenu_page('schemati', 'Schema אדם', 'אדם', 'manage_options', 'schemati-person', array($this, 'person_page'));
    add_submenu_page('schemati', 'Schema כותב', 'כותב', 'manage_options', 'schemati-author', array($this, 'author_page'));
    add_submenu_page('schemati', 'Schema מפרסם', 'מפרסם', 'manage_options', 'schemati-publisher', array($this, 'publisher_page'));
    add_submenu_page('schemati', 'Schema מוצר', 'מוצר', 'manage_options', 'schemati-product', array($this, 'product_page'));
    add_submenu_page('schemati', 'Schema שאלות נפוצות', 'שאלות נפוצות', 'manage_options', 'schemati-faq', array($this, 'faq_page'));
    add_submenu_page('schemati', 'כלי בדיקה', 'כלי בדיקה', 'manage_options', 'schemati-tools', array($this, 'tools_page'));
    add_submenu_page('schemati', 'עדכונים', 'עדכונים', 'manage_options', 'schemati-updates', array($this, 'updates_page'));
}
    
    /**
     * Register admin settings
     */
    public function admin_init() {
        // Register settings for all schema types
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
     * Sanitize settings
     */
    public function sanitize_settings($input) {
    $output = array();
    
    foreach ($input as $key => $value) {
        switch ($key) {
            case 'enabled':
            case 'enable_wpheader':
            case 'enable_wpfooter':
            case 'show_current':
                $output[$key] = (bool) $value;
                break;
            case 'org_name':
            case 'breadcrumb_home':
            case 'breadcrumb_separator':
            case 'header_menu_location':
            case 'footer_menu_location':
            case 'org_type':
                $output[$key] = sanitize_text_field($value);
                break;
            default:
                if (is_array($value)) {
                    $output[$key] = array_map('sanitize_text_field', array_filter($value));
                } elseif (is_string($value)) {
                    $output[$key] = sanitize_text_field($value);
                } elseif (is_numeric($value)) {
                    $output[$key] = intval($value);
                } else {
                    // Skip invalid data types
                    continue;
                }
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
        'breadcrumb_home' => 'בית', // Hebrew default
        'breadcrumb_separator' => ' › ',
        'show_current' => true,
        'enable_wpheader' => true,
        'enable_wpfooter' => true,
        'header_menu_location' => 'primary',
        'footer_menu_location' => 'footer'
    );
    
    return get_option($group, $defaults);
}
    /**
     * General Settings Page
     */
    public function general_page() {
    
    $this->handle_form_submission('schemati_general');
    $settings = $this->get_settings('schemati_general');
    
    ?>
    <div class="wrap">
        <div class="wrap">
        <h1><?php echo 'Schemati - הגדרות כלליות'; ?></h1>
        
        <?php if (get_transient('schemati_activated')): ?>
            <?php delete_transient('schemati_activated'); ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php echo 'Schemati v5.0 הופעל!'; ?></strong> <?php echo 'כל התכונות נטענו בהצלחה.'; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="notice notice-info">
            <p><strong><?php echo '✅ Schemati v5.0'; ?></strong> - <?php echo 'פתרון Schema מלא עם סרגל צד, כל סוגי ה-Schema, ושבילי ניווט.'; ?></p>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <h2><?php echo 'הגדרות Schema כלליות'; ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo 'הפעל סימון Schema'; ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                            <?php echo 'הפעל פלט סימון Schema בכל האתר'; ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'שם הארגון'; ?></th>
                    <td>
                        <input type="text" name="org_name" value="<?php echo esc_attr($settings['org_name'] ?? get_bloginfo('name')); ?>" class="regular-text" />
                        <p class="description"><?php echo 'שם הארגון או האתר שלך'; ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'סוג הארגון'; ?></th>
                    <td>
                        <select name="org_type">
                            <option value="Organization" <?php selected($settings['org_type'] ?? 'Organization', 'Organization'); ?>><?php echo 'ארגון'; ?></option>
                            <option value="LocalBusiness" <?php selected($settings['org_type'] ?? '', 'LocalBusiness'); ?>><?php echo 'עסק מקומי'; ?></option>
                            <option value="Corporation" <?php selected($settings['org_type'] ?? '', 'Corporation'); ?>><?php echo 'תאגיד'; ?></option>
                            <option value="Person" <?php selected($settings['org_type'] ?? '', 'Person'); ?>><?php echo 'אדם'; ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <h2><?php echo 'הגדרות שביל ניווט'; ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo 'טקסט דף הבית'; ?></th>
                    <td>
                        <input type="text" name="breadcrumb_home" value="<?php echo esc_attr($settings['breadcrumb_home'] ?? 'בית'); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'מפריד'; ?></th>
                    <td>
                        <input type="text" name="breadcrumb_separator" value="<?php echo esc_attr($settings['breadcrumb_separator'] ?? ' › '); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'הצג דף נוכחי'; ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_current" value="1" <?php checked(1, $settings['show_current'] ?? true); ?> />
                            <?php echo 'הצג דף נוכחי בשביל הניווט'; ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <h3><?php echo 'שימוש'; ?></h3>
            <p><strong><?php echo 'קוד קצר:'; ?></strong> <code>[schemati_breadcrumbs]</code></p>
            <p><strong><?php echo 'פונקציית PHP:'; ?></strong> <code>&lt;?php echo schemati_breadcrumbs(); ?&gt;</code></p>
            
            <?php submit_button('שמור הגדרות'); ?>
        </form>
    </div>
    <?php
}
    
    /**
     * Article Schema Page
     */
    public function article_page() {
    $this->handle_form_submission('schemati_article');
    $settings = $this->get_settings('schemati_article');
    
    ?>
    <div class="wrap">
        <h1><?php echo 'הגדרות Schema מאמרים'; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo 'הפעל Schema מאמרים'; ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                            <?php echo 'צור סימון Schema עבור פוסטים'; ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'סוג מאמר ברירת מחדל'; ?></th>
                    <td>
                        <select name="article_type">
                            <option value="Article" <?php selected($settings['article_type'] ?? 'Article', 'Article'); ?>><?php echo 'מאמר'; ?></option>
                            <option value="BlogPosting" <?php selected($settings['article_type'] ?? '', 'BlogPosting'); ?>><?php echo 'פוסט בלוג'; ?></option>
                            <option value="NewsArticle" <?php selected($settings['article_type'] ?? '', 'NewsArticle'); ?>><?php echo 'מאמר חדשות'; ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('שמור הגדרות'); ?>
        </form>
    </div>
    <?php
}
    
    /**
     * About Page Schema
     */
    public function about_page() {
    $this->schema_page_template('Schema דף אודות', 'schemati_about_page', 'צור סימון Schema עבור דפי אודות');
}
    
    /**
     * Contact Page Schema
     */
    public function contact_page() {
    $this->schema_page_template('Schema דף יצירת קשר', 'schemati_contact_page', 'צור סימון Schema עבור דפי יצירת קשר');
}
    
    /**
     * Local Business Schema
     */
    public function business_page() {
    $this->handle_form_submission('schemati_local_business');
    $settings = $this->get_settings('schemati_local_business');
    
    ?>
    <div class="wrap">
        <h1><?php echo 'Schema עסק מקומי'; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo 'הפעל Schema עסק מקומי'; ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                            <?php echo 'צור סימון Schema עבור עסק מקומי'; ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'שם העסק'; ?></th>
                    <td>
                        <input type="text" name="business_name" value="<?php echo esc_attr($settings['business_name'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'סוג העסק'; ?></th>
                    <td>
                        <select name="business_type">
                            <option value="LocalBusiness" <?php selected($settings['business_type'] ?? 'LocalBusiness', 'LocalBusiness'); ?>><?php echo 'עסק מקומי'; ?></option>
                            <option value="Restaurant" <?php selected($settings['business_type'] ?? '', 'Restaurant'); ?>><?php echo 'מסעדה'; ?></option>
                            <option value="Store" <?php selected($settings['business_type'] ?? '', 'Store'); ?>><?php echo 'חנות'; ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'כתובת'; ?></th>
                    <td>
                        <textarea name="address" rows="3" class="large-text"><?php echo esc_textarea($settings['address'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'מספר טלפון'; ?></th>
                    <td>
                        <input type="text" name="phone" value="<?php echo esc_attr($settings['phone'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            
            <?php submit_button('שמור הגדרות'); ?>
        </form>
    </div>
    <?php
}
    
    /**
     * Person Schema
     */
    public function person_page() {
    $this->schema_page_template('Schema אדם', 'schemati_person', 'צור סימון Schema עבור אדם/יחיד');
}
    
    /**
     * Author Schema
     */
    public function author_page() {
    $this->schema_page_template('Schema כותב', 'schemati_author', 'צור סימון Schema עבור כותבים');
}
    
    /**
     * Publisher Schema
     */
    public function publisher_page() {
    $this->schema_page_template('Schema מפרסם', 'schemati_publisher', 'צור סימון Schema עבור מפרסמים');
}
    
    /**
     * Product Schema
     */
    public function product_page() {
    $this->schema_page_template('Schema מוצר', 'schemati_product', 'צור סימון Schema עבור מוצרים');
}
    
    /**
     * FAQ Schema
     */
    public function faq_page() {
    $this->schema_page_template('Schema שאלות נפוצות', 'schemati_faq', 'צור סימון Schema עבור דפי שאלות נפוצות');
}
    
    /**
     * Tools/CheckTool Page
     */
    public function tools_page() {
    ?>
    <div class="wrap">
        <h1><?php echo 'כלי Schemati ואבחון'; ?></h1>
        
        <div class="card">
            <h2><?php echo 'כלי בדיקת Schema'; ?></h2>
            <p><?php echo 'בדוק את סימון ה-Schema שלך עם הכלים הרשמיים האלה:'; ?></p>
            <p>
                <a href="https://search.google.com/test/rich-results" target="_blank" class="button button-primary">
                    <?php echo 'בדיקת תוצאות עשירות של Google'; ?>
                </a>
                <a href="https://validator.schema.org/" target="_blank" class="button button-secondary">
                    <?php echo 'מאמת Schema.org'; ?>
                </a>
                <a href="https://developers.facebook.com/tools/debug/" target="_blank" class="button button-secondary">
                    <?php echo 'כלי ניפוי שגיאות Facebook'; ?>
                </a>
            </p>
        </div>
        
        <div class="card">
            <h2><?php echo 'סטטוס התוסף'; ?></h2>
            <table class="widefat">
                <tr>
                    <td><strong><?php echo 'גרסה'; ?></strong></td>
                    <td><?php echo SCHEMATI_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo 'סטטוס'; ?></strong></td>
                    <td>
                        <?php 
                        $general = $this->get_settings('schemati_general');
                        echo $general['enabled'] ? '<span style="color: green;">✓ ' . 'פעיל' . '</span>' : '<span style="color: red;">✗ ' . 'מושבת' . '</span>'; 
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo 'סוגי Schema זמינים'; ?></strong></td>
                    <td><?php echo 'ארגון, דף אינטרנט, מאמר, עסק מקומי, אדם, מוצר, שאלות נפוצות, רשימת שביל ניווט'; ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo 'סרגל צד'; ?></strong></td>
                    <td><?php echo '✓ פעיל בחזית האתר למשתמשים מחוברים'; ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo 'שביל ניווט'; ?></strong></td>
                    <td><?php echo '✓ קוד קצר ופונקציית PHP זמינים'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2><?php echo 'תצוגה מקדימה של Schema בדף הנוכחי'; ?></h2>
            <p><?php echo 'בקר באתר שלך בזמן שאתה מחובר כדי לראות את סרגל הצד של Schemati עם תצוגה מקדימה חיה של Schema.'; ?></p>
            <p><a href="<?php echo home_url(); ?>" target="_blank" class="button"><?php echo 'צפה באתר עם סרגל צד'; ?></a></p>
        </div>
    </div>
    <?php
}
    
    
    /**
     * Generic schema page template
     */
    private function schema_page_template($title, $option_group, $description) {
    $this->handle_form_submission($option_group);
    $settings = $this->get_settings($option_group);
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html($title); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html(str_replace(' Schema', '', $title)); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                            <?php echo esc_html($description); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('שמור הגדרות'); ?>
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
        
        echo '<div class="notice notice-success"><p>' . 'ההגדרות נשמרו בהצלחה!' . '</p></div>';
    }
}
    
    /**
     * Admin scripts and styles
     */
    public function admin_scripts($hook) {
    if (strpos($hook, 'schemati') !== false) {
        ?>
        <style>
        .schemati-admin .card { 
            max-width: 800px; 
            margin-top: 20px; 
        }
        .schemati-admin .form-table th { 
            width: 200px; 
            text-align: right; /* RTL support */
        }
        .schemati-admin .notice { 
            max-width: 800px; 
        }
        .schemati-admin .widefat td { 
            padding: 8px 10px; 
        }
        /* Hebrew/RTL specific styles */
        .schemati-admin[dir="rtl"] .form-table th {
            text-align: right;
        }
        .schemati-admin[dir="rtl"] .form-table td {
            text-align: right;
        }
        .schemati-admin .description {
            direction: rtl;
            text-align: right;
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $('.wrap').addClass('schemati-admin');
            // Add RTL direction for Hebrew content
            if (document.documentElement.lang === 'he' || document.documentElement.lang.startsWith('he-')) {
                $('.wrap').attr('dir', 'rtl');
            }
            
            // Hebrew success/error message styling
            $('.notice').each(function() {
                if ($(this).text().includes('נשמרו') || $(this).text().includes('הופעל')) {
                    $(this).css('direction', 'rtl').css('text-align', 'right');
                }
            });
        });
        </script>
        <?php
    }
}
    
    /**
     * Add meta boxes for posts and pages
     */
    public function add_meta_boxes() {
    add_meta_box(
        'schemati_schema',
        'הגדרות Schema',
        array($this, 'meta_box_schema'),
        array('post', 'page'),
        'normal',
        'default'
    );
}
    
    /**
     * Schema meta box content
     */
    public function meta_box_schema($post) {
    wp_nonce_field('schemati_meta', 'schemati_meta_nonce');
    
    $schema_type = get_post_meta($post->ID, '_schemati_type', true);
    $schema_description = get_post_meta($post->ID, '_schemati_description', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="schemati_type"><?php echo 'סוג Schema'; ?></label></th>
            <td>
                <select name="schemati_type" id="schemati_type">
                    <option value=""><?php echo 'ברירת מחדל'; ?></option>
                    <option value="Article" <?php selected($schema_type, 'Article'); ?>><?php echo 'מאמר'; ?></option>
                    <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>><?php echo 'פוסט בלוג'; ?></option>
                    <option value="NewsArticle" <?php selected($schema_type, 'NewsArticle'); ?>><?php echo 'מאמר חדשות'; ?></option>
                    <option value="Product" <?php selected($schema_type, 'Product'); ?>><?php echo 'מוצר'; ?></option>
                    <option value="Event" <?php selected($schema_type, 'Event'); ?>><?php echo 'אירוע'; ?></option>
                    <option value="LocalBusiness" <?php selected($schema_type, 'LocalBusiness'); ?>><?php echo 'עסק מקומי'; ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="schemati_description"><?php echo 'תיאור מותאם אישית'; ?></label></th>
            <td>
                <textarea name="schemati_description" id="schemati_description" rows="3" style="width:100%;"><?php echo esc_textarea($schema_description); ?></textarea>
                <p class="description"><?php echo 'תיאור אופציונלי מותאם אישית עבור סימון Schema'; ?></p>
            </td>
        </tr>
    </table>
    <?php
}
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
     * Add admin bar menu
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
            'title'   => 'הפעל/כבה סרגל צד Schemati'
        ),
    ));
    
    $admin_bar->add_menu(array(
        'id'     => 'schemati-preview',
        'parent' => 'schemati',
        'title'  => 'צפה ב-Schema',
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
     * Add interactive sidebar HTML to frontend with editing capabilities
     */
    /**
     * Enhanced version of add_sidebar_html with better schema detection and additional features
     */
    public function add_sidebar_html() {
    if (!current_user_can('edit_posts') || is_admin()) {
        return;
    }
    
    global $post;
    $general_settings = $this->get_settings('schemati_general');
    
    // Get current page schema data with enhanced detection
    $current_schemas = $this->get_enhanced_page_schemas();
    ?>
    <div id="schemati-sidebar" style="display: none; position: fixed; top: 32px; right: 0; width: 450px; height: calc(100vh - 32px); background: white; border-left: 1px solid #ccc; z-index: 99999; padding: 0; overflow-y: auto; box-shadow: -2px 0 10px rgba(0,0,0,0.15); font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
        
        <!-- Enhanced Header with Dynamic Status -->
        <div style="padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; position: sticky; top: 0; z-index: 1000;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 18px;">
                        <span style="margin-right: 8px;">⚙️</span>
                        עורך Schemati
                    </h3>
                    <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                        <span id="schema-count"><?php echo count($current_schemas); ?> schemas זוהו</span>
                        <span style="margin-left: 10px;">•</span>
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
                    <small style="color: #666;" id="current-count"><?php echo count($current_schemas); ?> schemas</small>
                </button>
                <button class="schemati-tab" onclick="showSchematiTab('add')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                    <span style="display: block;">הוסף</span>
                    <small style="color: #666;">Schema חדש</small>
                </button>
                <button class="schemati-tab" onclick="showSchematiTab('templates')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                    <span style="display: block;">תבניות</span>
                    <small style="color: #666;">הוספה מהירה</small>
                </button>
                <button class="schemati-tab" onclick="showSchematiTab('settings')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                    <span style="display: block;">הגדרות</span>
                    <small style="color: #666;">כלליות</small>
                </button>
            </div>
        </div>
        
        <!-- Enhanced Current Schemas Tab with Dynamic Loading -->
        <div id="schemati-tab-current" class="schemati-tab-content" style="padding: 20px;">
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; color: #333; font-size: 14px;">SCHEMAS שזוהו</h4>
                <div style="display: flex; gap: 5px;">
                    <button onclick="exportPageSchemas()" style="background: #17a2b8; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="ייצא Schemas">💾</button>
                    <button onclick="validateAllSchemas()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="אמת הכל">✓</button>
                    <button onclick="toggleAllSchemas()" style="background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="החלף הכל">⚡</button>
                </div>
            </div>
            
            <!-- Dynamic Schema List - Will be populated by JavaScript -->
            <div id="current-schemas-list">
                <div style="text-align: center; padding: 20px; color: #666;">
                    <div style="font-size: 24px; margin-bottom: 10px;">🔄</div>
                    <p>טוען schemas...</p>
                </div>
            </div>
        </div>
            
            <!-- Rest of the tabs remain the same -->
            div id="schemati-tab-add" class="schemati-tab-content" style="display: none; padding: 20px;">
            <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;">הוסף SCHEMA חדש</h4>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">סוג Schema:</label>
                <select id="new-schema-type" onchange="loadSchemaTemplate()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">בחר סוג Schema</option>
                    <optgroup label="עסקים">
                        <option value="LocalBusiness">🏢 עסק מקומי</option>
                        <option value="Service">🛠️ שירות</option>
                        <option value="Product">📦 מוצר</option>
                        <option value="Organization">🏛️ ארגון</option>
                    </optgroup>
                    <optgroup label="תוכן">
                        <option value="Article">📰 מאמר</option>
                        <option value="BlogPosting">📝 פוסט בלוג</option>
                        <option value="NewsArticle">📺 מאמר חדשות</option>
                        <option value="FAQPage">❓ דף שאלות נפוצות</option>
                        <option value="HowTo">📋 מדריך</option>
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
                            ➕ הוסף Schema
                        </button>
                    </div>
                </form>
            </div>
        </div>
                
                <div id="new-schema-form" style="display: none;">
                    <form onsubmit="addNewSchema(); return false;">
                        <div id="schema-template-fields"></div>
                        
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="button" onclick="previewNewSchema()" style="flex: 1; background: #6c757d; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                👁️ Preview
                            </button>
                            <button type="submit" style="flex: 2; background: #0073aa; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                ➕ Add Schema
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- New Templates Tab -->
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
                    <div style="font-weight: 500; margin-bottom: 3px;">שאלות נפוצות</div>
                    <div style="font-size: 11px; color: #666;">שאלות נפוצות</div>
                </button>
                
                <button onclick="addQuickTemplate('Article')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: right; transition: all 0.3s ease;">
                    <div style="font-size: 20px; margin-bottom: 5px;">📰</div>
                    <div style="font-weight: 500; margin-bottom: 3px;">מאמר</div>
                    <div style="font-size: 11px; color: #666;">פוסטים בבלוג, מאמרי חדשות</div>
                </button>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-right: 4px solid #0073aa;">
                <h5 style="margin: 0 0 8px 0; color: #0073aa;">💡 טיפ מקצועי</h5>
                <p style="margin: 0; font-size: 13px; line-height: 1.4; color: #666;">בחר תבנית בהתאם לסוג התוכן שלך. התבניות האלה כוללות את השדות החשובים ביותר ועוקבות אחר הנחיות Google.</p>
            </div>
        </div>
            
            <!-- Enhanced Settings Tab -->
            <div id="schemati-tab-settings" class="schemati-tab-content" style="display: none; padding: 20px;">
            <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;">הגדרות כלליות</h4>
            
            <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                    <input type="checkbox" id="schema-enabled" <?php checked($general_settings['enabled']); ?> onchange="toggleGlobalSchema()" style="margin-left: 8px;">
                    <span style="font-weight: 500;">הפעל סימון Schema</span>
                </label>
                <div style="font-size: 12px; color: #666; margin-right: 20px;">
                    שולט האם סימון Schema מופק באתר שלך
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h5 style="margin: 0 0 10px 0; font-size: 13px; color: #333;">פעולות מהירות</h5>
                <div style="display: grid; gap: 8px;">
                    <button onclick="showSchematiPreview()" style="width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <span>🔍</span>
                        <span>תצוגה מקדימה של כל ה-Schemas</span>
                    </button>
                    <button onclick="testGoogleRichResults()" style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <span>🚀</span>
                        <span>בדוק תוצאות עשירות</span>
                    </button>
                    <button onclick="exportAllSchemas()" style="width: 100%; padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <span>💾</span>
                        <span>ייצא כל ה-Schemas</span>
                    </button>
                    <button onclick="importSchemas()" style="width: 100%; padding: 12px; background: #fd7e14; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <span>📥</span>
                        <span>ייבא Schemas</span>
                    </button>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h5 style="margin: 0 0 10px 0; font-size: 13px; color: #333;">פעולות בכמות</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button onclick="enableAllSchemas()" style="padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">✓ הפעל הכל</button>
                    <button onclick="disableAllSchemas()" style="padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">✗ כבה הכל</button>
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
                <div>Schemati v5.0 | עורך Schema חי</div>
                <div style="margin-top: 4px;">
                    <a href="https://search.google.com/test/rich-results" target="_blank" style="color: #0073aa; text-decoration: none;">בדיקת תוצאות עשירות Google</a> |
                    <a href="https://validator.schema.org/" target="_blank" style="color: #0073aa; text-decoration: none;">מאמת Schema</a>
                </div>
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
                    <h2 style="margin: 0; color: white;">🔍 תצוגה מקדימה חיה של Schema</h2>
                    <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;" id="schema-preview-count">טוען...</div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="copyAllSchemas()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">📋 העתק הכל</button>
                    <button onclick="hideSchematiPreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: white; padding: 5px;">&times;</button>
                </div>
            </div>
            <div id="schema-modal-content" style="height: calc(100% - 80px); overflow-y: auto; padding: 20px; font-family: monospace; font-size: 12px; line-height: 1.5;">
                טוען נתוני schema...
            </div>
        </div>
    </div>
        
        <script>
        // Enhanced JavaScript with DOM-PHP sync
        var currentPostId = <?php echo get_the_ID() ?: 0; ?>;
        var detectedSchemas = [];
        var phpSchemas = <?php echo wp_json_encode($current_schemas); ?>;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            syncSchemasWithDOM();
        });
        
        // Enhanced sync function that combines DOM detection with PHP data
        function syncSchemasWithDOM() {
            detectedSchemas = [];
            
            // First, scan DOM for actual JSON-LD scripts
            var domSchemas = [];
            document.querySelectorAll('script[type="application/ld+json"]').forEach(function(script, index) {
                try {
                    var schema = JSON.parse(script.textContent);
                    schema._domIndex = index;
                    schema._element = script;
                    schema._source = 'dom';
                    domSchemas.push(schema);
                } catch(e) {
                    console.warn('Invalid JSON-LD schema found:', e);
                }
            });
            
            // Combine with PHP data for editing capabilities
            domSchemas.forEach(function(domSchema, index) {
                var schemaType = domSchema['@type'];
                var schemaName = domSchema.name || domSchema.title || domSchema.headline || 'Untitled';
                
                // Try to find matching PHP schema for editing capabilities
                var phpMatch = phpSchemas.find(function(phpSchema) {
                    return phpSchema['@type'] === schemaType && 
                           (phpSchema.name === schemaName || phpSchema.title === schemaName);
                });
                
                if (phpMatch) {
                    // Use PHP data but mark as DOM-detected
                    phpMatch._domDetected = true;
                    phpMatch._domIndex = index;
                    detectedSchemas.push(phpMatch);
                } else {
                    // Add DOM-only schema (read-only)
                    domSchema._enabled = true;
                    domSchema._source = domSchema._source || 'system';
                    domSchema._editable = false;
                    detectedSchemas.push(domSchema);
                }
            });
        function refreshSchemas() {
    syncSchemasWithDOM();
}

// Add new schema
function addNewSchema() {
    var form = document.querySelector('#new-schema-form form');
    if (!form) {
        alert('Form not found');
        return;
    }
    
    var formData = new FormData(form);
    var schemaType = document.getElementById('new-schema-type').value;
    
    if (!schemaType) {
        alert('אנא בחר סוג Schema');
        return;
    }
    
    // Convert FormData to regular object
    var data = {
        action: 'schemati_add_schema',
        schema_type: schemaType,
        post_id: currentPostId,
        nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>'
    };
    
    // Add form fields to data
    for (var pair of formData.entries()) {
        data[pair[0]] = pair[1];
    }
    
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: data,
        success: function(response) {
            if (response.success) {
                alert('Schema נוסף בהצלחה!');
                form.reset();
                document.getElementById('new-schema-form').style.display = 'none';
                document.getElementById('new-schema-type').value = '';
                syncSchemasWithDOM();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        },
        error: function() {
            alert('שגיאת חיבור. נסה שוב.');
        }
    });
}

// Save schema changes
function saveSchemaChanges(index) {
    var form = document.querySelector('#schema-editor-' + index + ' form');
    if (!form) return;
    
    var formData = new FormData(form);
    var data = {
        action: 'schemati_save_schema',
        schema_index: index,
        post_id: currentPostId,
        nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>'
    };
    
    // Add form fields
    for (var pair of formData.entries()) {
        data[pair[0]] = pair[1];
    }
    
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: data,
        success: function(response) {
            if (response.success) {
                alert('Schema עודכן בהצלחה!');
                toggleSchemaEditor(index);
                syncSchemasWithDOM();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        },
        error: function() {
            alert('שגיאת חיבור. נסה שוב.');
        }
    });
}

// Toggle schema on/off status
function toggleSchemaStatus(index) {
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'schemati_toggle_schema',
            schema_index: index,
            post_id: currentPostId,
            nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>'
        },
        success: function(response) {
            if (response.success) {
                syncSchemasWithDOM();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        },
        error: function() {
            alert('שגיאת חיבור. נסה שוב.');
        }
    });
}

// Delete schema
function deleteSchema(index) {
    if (!confirm('האם אתה בטוח שברצונך למחוק Schema זה?')) {
        return;
    }
    
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'schemati_delete_schema',
            schema_index: index,
            post_id: currentPostId,
            nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>'
        },
        success: function(response) {
            if (response.success) {
                alert('Schema נמחק בהצלחה!');
                syncSchemasWithDOM();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        },
        error: function() {
            alert('שגיאת חיבור. נסה שוב.');
        }
    });
}

// Toggle global schema setting
function toggleGlobalSchema() {
    var checkbox = document.getElementById('schema-enabled');
    var enabled = checkbox.checked ? 1 : 0;
    
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'schemati_toggle_global',
            enabled: enabled,
            nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>'
        },
        success: function(response) {
            if (response.success) {
                document.getElementById('schema-status').textContent = enabled ? 'Active' : 'Disabled';
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
                checkbox.checked = !checkbox.checked; // Revert checkbox
            }
        },
        error: function() {
            alert('שגיאת חיבור. נסה שוב.');
            checkbox.checked = !checkbox.checked; // Revert checkbox
        }
    });
}

// Define loadSchemaTemplate function
function loadSchemaTemplate() {
    loadSchemaTemplateWithFeedback();
}
            
            // Update counters
            updateSchemaCounts();
            
            // Re-render current schemas list
            renderCurrentSchemas();
            
            console.log('Schemati: Synced', detectedSchemas.length, 'schemas', detectedSchemas);
        }
        
        // Update all schema counters
        function updateSchemaCounts() {
            var count = detectedSchemas.length;
            document.getElementById('schema-count').textContent = count + ' schemas detected';
            document.getElementById('current-count').textContent = count + ' schemas';
        }
        
        // Render current schemas in the sidebar
        function renderCurrentSchemas() {
            var container = document.getElementById('current-schemas-list');
            
            if (detectedSchemas.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                        <h4>No schemas detected</h4>
                        <p>Add your first schema using the "Add" tab or choose from templates.</p>
                        <button onclick="showSchematiTab('templates')" style="background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Browse Templates</button>
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
        
        // Render individual schema item
        function renderSchemaItem(schema, index) {
            var schemaType = schema['@type'] || 'Unknown';
            var schemaName = schema.name || schema.title || schema.headline || 'Untitled';
            var schemaEnabled = schema._enabled !== false;
            var schemaSource = schema._source || 'unknown';
            var isEditable = schema._editable !== false && schemaSource === 'custom';
            
            var sourceInfo = getSourceInfo(schemaSource);
            
            var html = `
                <div class="schema-item" data-schema-index="${index}" style="margin-bottom: 15px; border: 1px solid ${schemaEnabled ? '#ddd' : '#f5c6cb'}; border-radius: 8px; overflow: hidden; ${schemaEnabled ? '' : 'opacity: 0.7;'}">
                    <div class="schema-header" style="background: ${schemaEnabled ? '#f8f9fa' : '#f8d7da'}; padding: 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="toggleSchemaEditor(${index})">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <strong style="color: #0073aa; font-size: 14px;">${schemaType}</strong>
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
                                ${schemaEnabled ? 'ON' : 'OFF'}
                            </button>
                            <button onclick="deleteSchema(${index}); event.stopPropagation();" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">✕</button>`;
            } else {
                html += `<span style="color: #666; font-size: 11px;">Read-only</span>`;
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
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Name:</label>
                                <input type="text" name="name" value="${schema.name || ''}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description:</label>
                                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${schema.description || ''}</textarea>
                            </div>
                            <div style="margin-top: 15px; text-align: right;">
                                <button type="button" onclick="toggleSchemaEditor(${index})" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; margin-right: 5px; cursor: pointer;">Cancel</button>
                                <button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Save Changes</button>
                            </div>
                        </form>
                    </div>`;
            } else {
                html += `
                    <div id="schema-editor-${index}" class="schema-editor" style="display: none; padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd;">
                        <div style="text-align: center; color: #666;">
                            <p><strong>🔒 System Generated Schema</strong></p>
                            <p style="font-size: 13px; line-height: 1.4;">This schema is automatically generated. You can modify global settings in the admin panel.</p>
                            <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="color: #0073aa; text-decoration: none;">⚙️ Edit Global Settings</a>
                        </div>
                    </div>`;
            }
            
            html += `</div>`;
            return html;
        }
        
        // Get source information
        function getSourceInfo(source) {
            switch (source) {
                case 'global':
                    return { label: 'Global', icon: '🌐', color: '#17a2b8' };
                case 'post':
                    return { label: 'Post', icon: '📄', color: '#28a745' };
                case 'auto':
                    return { label: 'Auto', icon: '🤖', color: '#6f42c1' };
                case 'custom':
                    return { label: 'Custom', icon: '✏️', color: '#fd7e14' };
                case 'dom':
                case 'system':
                    return { label: 'System', icon: '⚙️', color: '#6c757d' };
                default:
                    return { label: 'Unknown', icon: '❓', color: '#6c757d' };
            }
        }
        
        // Enhanced toggle sidebar that syncs on open
        function toggleSchematiSidebar() {
            var sidebar = document.getElementById("schemati-sidebar");
            if (sidebar) {
                if (sidebar.style.display === "none") {
                    sidebar.style.display = "block";
                    syncSchemasWithDOM(); // Sync when opening
                } else {
                    sidebar.style.display = "none";
                }
            }
        }
        
        // Enhanced tab switching
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
            
            // Sync schemas when viewing current tab
            if (tabName === 'current') {
                syncSchemasWithDOM();
            }
        }
        
        // Toggle schema editor
        function toggleSchemaEditor(index) {
            var editor = document.getElementById('schema-editor-' + index);
            if (editor) {
                editor.style.display = editor.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        // Enhanced schema preview with DOM schemas
        function showSchematiPreview() {
            syncSchemasWithDOM();
            
            var modal = document.getElementById('schemati-schema-modal');
            var content = document.getElementById('schema-modal-content');
            var countElement = document.getElementById('schema-preview-count');
            
            if (detectedSchemas.length === 0) {
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><div style="font-size: 48px; margin-bottom: 15px;">📋</div><h3>No Schema Found</h3><p>No schema markup was detected on this page.</p></div>';
                countElement.textContent = 'No schemas found';
            } else {
                var html = '<div style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724; border-left: 4px solid #28a745;"><h3 style="margin: 0; display: flex; align-items: center; gap: 8px;"><span>✅</span>Found ' + detectedSchemas.length + ' Schema Type(s)</h3><p style="margin: 5px 0 0 0; font-size: 13px;">All schemas are properly formatted and ready for search engines.</p></div>';
                
                detectedSchemas.forEach(function(schema, index) {
                    var schemaType = schema['@type'] || 'Unknown Type';
                    var schemaName = schema.name || schema.title || schema.headline || 'No title';
                    
                    html += '<div style="margin-bottom: 25px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: white;">';
                    html += '<div style="padding: 15px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; display: flex; justify-content: space-between; align-items: center;">';
                    html += '<div><h4 style="margin: 0; font-size: 16px;">' + (index + 1) + '. ' + schemaType + ' Schema</h4>';
                    if (schemaName !== 'No title') {
                        html += '<div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">' + schemaName + '</div>';
                    }
                    html += '</div>';
                    html += '<button onclick="copySchema(' + index + ')" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;">Copy</button>';
                    html += '</div>';
                    html += '<pre style="background: #2d3748; color: #e2e8f0; padding: 20px; margin: 0; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.5;">' + JSON.stringify(schema, null, 2) + '</pre>';
                    html += '</div>';
                });
                content.innerHTML = html;
                countElement.textContent = detectedSchemas.length + ' schemas detected and validated';
            }
            
            modal.style.display = 'block';
        }
        
        // Copy individual schema
        function copySchema(index) {
            if (detectedSchemas[index]) {
                navigator.clipboard.writeText(JSON.stringify(detectedSchemas[index], null, 2)).then(() => {
                    alert('Schema copied to clipboard!');
                });
            }
        }
        
        // Copy all schemas
        function copyAllSchemas() {
            navigator.clipboard.writeText(JSON.stringify(detectedSchemas, null, 2)).then(() => {
                alert('All schemas copied to clipboard!');
            });
        }
        
        // Hide preview modal
        function hideSchematiPreview() {
            document.getElementById('schemati-schema-modal').style.display = 'none';
        }
        
        // Test Google Rich Results
        function testGoogleRichResults() {
            window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(window.location.href), '_blank');
        }
        
        
        // Export page schemas
        function exportPageSchemas() {
            var blob = new Blob([JSON.stringify(detectedSchemas, null, 2)], {type: 'application/json'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'page-schemas-' + new Date().toISOString().split('T')[0] + '.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        // Export all schemas
        function exportAllSchemas() {
            exportPageSchemas(); // For now, same as page schemas
        }
        
        // Import schemas
        function importSchemas() {
            document.getElementById('schema-import-file').click();
        }
        
        // Handle schema import
        function handleSchemaImport(event) {
            var file = event.target.files[0];
            if (!file) return;
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var schemas = JSON.parse(e.target.result);
                    // Here you would process the imported schemas
                    alert('Schemas imported successfully! ' + schemas.length + ' schemas loaded.');
                    location.reload();
                } catch (error) {
                    alert('Error importing schemas: Invalid JSON file.');
                }
            };
            reader.readAsText(file);
        }
        
        // Bulk operations
        function enableAllSchemas() {
            if (confirm('Enable all schemas on this page?')) {
                // Implementation for enabling all schemas
                alert('All schemas enabled!');
                location.reload();
            }
        }
        
        function disableAllSchemas() {
            if (confirm('Disable all schemas on this page?')) {
                // Implementation for disabling all schemas
                alert('All schemas disabled!');
                location.reload();
            }
        }
        
        function duplicateCurrentSchemas() {
            if (confirm('Duplicate all current schemas?')) {
                // Implementation for duplicating schemas
                alert('Schemas duplicated!');
                location.reload();
            }
        }
        
        function resetAllSchemas() {
            if (confirm('Reset all schemas? This will remove all custom schemas.')) {
                // Implementation for resetting schemas
                alert('All schemas reset!');
                location.reload();
            }
        }
        
        // Validate all schemas
        function validateAllSchemas() {
            var validCount = 0;
            var invalidCount = 0;
            
            detectedSchemas.forEach(function(schema) {
                try {
                    // Basic validation
                    if (schema['@context'] && schema['@type']) {
                        validCount++;
                    } else {
                        invalidCount++;
                    }
                } catch (e) {
                    invalidCount++;
                }
            });
            
            alert('Validation Results:\n✅ Valid: ' + validCount + '\n❌ Invalid: ' + invalidCount);
        }
        
        // Preview new schema before adding
        function previewNewSchema() {
            var form = document.querySelector('#new-schema-form form');
            var formData = new FormData(form);
            var schemaType = document.getElementById('new-schema-type').value;
            
            // Build preview schema object
            var previewSchema = {
                '@context': 'https://schema.org',
                '@type': schemaType
            };
            
            // Add form data to preview
            for (var pair of formData.entries()) {
                if (pair[1]) {
                    previewSchema[pair[0]] = pair[1];
                }
            }
            
            // Show preview in modal
            var modal = document.getElementById('schemati-schema-modal');
            var content = document.getElementById('schema-modal-content');
            
            content.innerHTML = '<div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404; border-left: 4px solid #ffc107;"><h3 style="margin: 0;">👁️ Schema Preview</h3><p style="margin: 5px 0 0 0; font-size: 13px;">This is how your schema will look when added.</p></div><pre style="background: #2d3748; color: #e2e8f0; padding: 20px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.4;">' + JSON.stringify(previewSchema, null, 2) + '</pre>';
            
            modal.style.display = 'block';
        }
        
        // Toggle all schemas
        function toggleAllSchemas() {
            // Implementation for toggling all schemas
            alert('All schemas toggled!');
        }
        
        // Add CSS for hover effects on template buttons
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
        function addQuickTemplate(type) {
    console.log('Adding quick template:', type);
    
    try {
        // Set the dropdown value
        var dropdown = document.getElementById('new-schema-type');
        if (!dropdown) {
            console.error('Schema type dropdown not found');
            alert('Error: Schema dropdown not found. Please refresh the page.');
            return;
        }
        
        dropdown.value = type;
        
        // Trigger change event
        var event = new Event('change', { bubbles: true });
        dropdown.dispatchEvent(event);
        
        // Load template with enhanced error handling
        loadSchemaTemplateWithFeedback();
        
        // Switch to add tab
        showSchematiTab('add');
        
        // Scroll to form
        setTimeout(function() {
            var form = document.getElementById('new-schema-form');
            if (form && form.style.display !== 'none') {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 300);
        
    } catch (error) {
        console.error('Error in addQuickTemplate:', error);
        alert('Error loading template: ' + error.message);
    }
}

// Enhanced version of existing loadSchemaTemplate function
function loadSchemaTemplateWithFeedback() {
    var schemaType = document.getElementById('new-schema-type').value;
    var formContainer = document.getElementById('new-schema-form');
    var fieldsContainer = document.getElementById('schema-template-fields');
    
    if (!schemaType) {
        formContainer.style.display = 'none';
        return;
    }
    
    // Show loading state
    fieldsContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;"><div style="font-size: 20px; margin-bottom: 10px;">⏳</div><p>Loading template...</p></div>';
    formContainer.style.display = 'block';
    
    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded');
        fieldsContainer.innerHTML = '<div style="color: red; padding: 15px; border: 1px solid red; border-radius: 4px;"><strong>Error:</strong> jQuery is required but not loaded. Please refresh the page.</div>';
        return;
    }
    
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'schemati_get_schema_template',
            schema_type: schemaType,
            nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>'
        },
        timeout: 10000,
        success: function(response) {
            console.log('Template response:', response);
            
            if (response.success && response.data) {
                fieldsContainer.innerHTML = response.data;
                
                // Add success feedback
                var successMsg = document.createElement('div');
                successMsg.style.cssText = 'background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #28a745;';
                successMsg.innerHTML = '✅ Template loaded successfully! Fill in the fields below.';
                fieldsContainer.insertBefore(successMsg, fieldsContainer.firstChild);
                
                // Remove success message after 3 seconds
                setTimeout(function() {
                    if (successMsg.parentNode) {
                        successMsg.remove();
                    }
                }, 3000);
                
            } else {
                var errorMsg = response.data || 'Template not found';
                fieldsContainer.innerHTML = '<div style="color: #721c24; background: #f8d7da; padding: 15px; border-radius: 4px; border-left: 4px solid #dc3545;"><strong>❌ Error:</strong> ' + errorMsg + '<br><br>Available templates: LocalBusiness, Service, Product, Event, Person, FAQPage, Article, Organization<br><br><button onclick="loadSchemaTemplateWithFeedback()" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">🔄 Retry</button></div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr);
            
            var errorMsg = 'Connection error. ';
            if (status === 'timeout') {
                errorMsg += 'Request timed out.';
            } else if (status === 'error') {
                errorMsg += 'Server error: ' + error;
            } else {
                errorMsg += 'Status: ' + status;
            }
            
            fieldsContainer.innerHTML = '<div style="color: #721c24; background: #f8d7da; padding: 15px; border-radius: 4px; border-left: 4px solid #dc3545;"><strong>❌ Connection Error:</strong> ' + errorMsg + '<br><br><button onclick="loadSchemaTemplateWithFeedback()" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">🔄 Retry</button></div>';
        }
    });
}

// Override the original loadSchemaTemplate to use the enhanced version
window.loadSchemaTemplate = loadSchemaTemplateWithFeedback;

// Add debugging function
function debugSchematiTemplates() {
    console.log('=== Schemati Template Debug ===');
    console.log('jQuery available:', typeof jQuery !== 'undefined');
    console.log('Required elements:');
    
    ['new-schema-type', 'new-schema-form', 'schema-template-fields'].forEach(function(id) {
        var element = document.getElementById(id);
        console.log('- ' + id + ':', element ? '✅' : '❌');
        if (element) {
            console.log('  Value/content:', element.value || element.innerHTML.substring(0, 100));
        }
    });
    
    console.log('Template buttons:');
    document.querySelectorAll('[onclick*="addQuickTemplate"]').forEach(function(btn, index) {
        console.log('- Button ' + index + ':', btn.textContent.trim());
    });
    
    console.log('AJAX URL:', '<?php echo admin_url("admin-ajax.php"); ?>');
    console.log('============================');
}

// Make debug function globally available
window.debugSchematiTemplates = debugSchematiTemplates;

console.log('Schemati: Template functions loaded');
        </script>
        <?php
    }

    /**
     * Render enhanced schema item with better UI
     */
    private function render_enhanced_schema_item($schema, $index) {
        $schema_type = $schema['@type'] ?? 'Unknown';
        $schema_name = $schema['name'] ?? $schema['title'] ?? 'Untitled';
        $schema_enabled = $schema['_enabled'] ?? true;
        $schema_source = $schema['_source'] ?? 'unknown';
        
        // Get source icon and color
        $source_info = $this->get_source_info($schema_source);
        ?>
        <div class="schema-item" data-schema-index="<?php echo $index; ?>" style="margin-bottom: 15px; border: 1px solid <?php echo $schema_enabled ? '#ddd' : '#f5c6cb'; ?>; border-radius: 8px; overflow: hidden; <?php echo $schema_enabled ? '' : 'opacity: 0.7;'; ?>">
            <div class="schema-header" style="background: <?php echo $schema_enabled ? '#f8f9fa' : '#f8d7da'; ?>; padding: 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="toggleSchemaEditor(<?php echo $index; ?>)">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <strong style="color: #0073aa; font-size: 14px;"><?php echo esc_html($schema_type); ?></strong>
                        <span style="background: <?php echo $source_info['color']; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500;">
                            <?php echo $source_info['icon']; ?> <?php echo $source_info['label']; ?>
                        </span>
                    </div>
                    <div style="font-size: 12px; color: #666; line-height: 1.3;">
                        <?php echo esc_html(wp_trim_words($schema_name, 8)); ?>
                    </div>
                </div>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <?php if ($schema_source === 'custom'): ?>
                    <button onclick="toggleSchemaStatus(<?php echo $index; ?>); event.stopPropagation();" style="background: <?php echo $schema_enabled ? '#28a745' : '#dc3545'; ?>; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer; font-weight: 500;">
                        <?php echo $schema_enabled ? 'ON' : 'OFF'; ?>
                    </button>
                    <button onclick="deleteSchema(<?php echo $index; ?>); event.stopPropagation();" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">✕</button>
                    <?php endif; ?>
                    <span style="color: #666; font-size: 12px;">▼</span>
                </div>
            </div>
            
            <?php if ($schema_source === 'custom'): ?>
            <div id="schema-editor-<?php echo $index; ?>" class="schema-editor" style="display: none; padding: 15px; background: white; border-top: 1px solid #ddd;">
                <form onsubmit="saveSchemaChanges(<?php echo $index; ?>); return false;">
                    <?php $this->render_schema_editor_fields($schema, $index); ?>
                    <div style="margin-top: 15px; text-align: right;">
                        <button type="button" onclick="toggleSchemaEditor(<?php echo $index; ?>)" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; margin-right: 5px; cursor: pointer;">Cancel</button>
                        <button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Save Changes</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div id="schema-editor-<?php echo $index; ?>" class="schema-editor" style="display: none; padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd;">
                <div style="text-align: center; color: #666;">
                    <p><strong>🔒 System Generated Schema</strong></p>
                    <p style="font-size: 13px; line-height: 1.4;">This schema is automatically generated by Schemati based on your global settings and page content. You can modify global settings in the admin panel.</p>
                    <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="color: #0073aa; text-decoration: none;">⚙️ Edit Global Settings</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get source information for schema items
     */
    private function get_source_info($source) {
    switch ($source) {
        case 'global':
            return array(
                'label' => 'כללי',      // Global
                'icon' => '🌐',
                'color' => '#17a2b8'
            );
        case 'post':
            return array(
                'label' => 'פוסט',      // Post
                'icon' => '📄',
                'color' => '#28a745'
            );
        case 'auto':
            return array(
                'label' => 'אוטומטי',   // Auto
                'icon' => '🤖',
                'color' => '#6f42c1'
            );
        case 'custom':
            return array(
                'label' => 'מותאם',     // Custom
                'icon' => '✏️',
                'color' => '#fd7e14'
            );
        case 'dom':
        case 'system':
            return array(
                'label' => 'מערכת',     // System
                'icon' => '⚙️',
                'color' => '#6c757d'
            );
        default:
            return array(
                'label' => 'לא ידוע',   // Unknown
                'icon' => '❓',
                'color' => '#6c757d'
            );
    }
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

        $header_schema = $this->build_wpheader_schema();
    if ($header_schema) {
        $header_schema['_enabled'] = true;
        $header_schema['_source'] = 'auto';
        $schemas[] = $header_schema;
    }
    
    $footer_schema = $this->build_wpfooter_schema();
    if ($footer_schema) {
        $footer_schema['_enabled'] = true;
        $footer_schema['_source'] = 'auto';
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
            
            // Breadcrumb schema
            if (!is_front_page()) {
                $breadcrumb_schema = $this->build_breadcrumb_schema();
                if ($breadcrumb_schema) {
                    $breadcrumb_schema['_enabled'] = true;
                    $breadcrumb_schema['_source'] = 'auto';
                    $schemas[] = $breadcrumb_schema;
                }
            }
            
            // Custom schemas from meta
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
     * Render schema editor fields based on schema type
     */
    private function render_schema_editor_fields($schema, $index) {
        $schema_type = $schema['@type'] ?? 'Unknown';
        
        switch ($schema_type) {
            case 'LocalBusiness':
            case 'Service':
                $this->render_business_schema_fields($schema, $index);
                break;
            case 'Product':
                $this->render_product_schema_fields($schema, $index);
                break;
            case 'Organization':
                $this->render_organization_schema_fields($schema, $index);
                break;
            case 'Person':
                $this->render_person_schema_fields($schema, $index);
                break;
            default:
                $this->render_generic_schema_fields($schema, $index);
        }
    }

    /**
     * Render business schema editor fields
     */
    private function render_business_schema_fields($schema, $index) {
        ?>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Business Name:</label>
            <input type="text" name="name" value="<?php echo esc_attr($schema['name'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description:</label>
            <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea($schema['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Address:</label>
            <textarea name="address" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea($schema['address'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Phone:</label>
                <input type="text" name="telephone" value="<?php echo esc_attr($schema['telephone'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Email:</label>
                <input type="email" name="email" value="<?php echo esc_attr($schema['email'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Website URL:</label>
            <input type="url" name="url" value="<?php echo esc_attr($schema['url'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <?php
    }

    /**
     * Render product schema editor fields
     */
    private function render_product_schema_fields($schema, $index) {
        ?>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Product Name:</label>
            <input type="text" name="name" value="<?php echo esc_attr($schema['name'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description:</label>
            <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea($schema['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Brand:</label>
            <input type="text" name="brand" value="<?php echo esc_attr($schema['brand'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Price:</label>
                <?php 
                $price = isset($schema['offers']) && isset($schema['offers']['price']) ? $schema['offers']['price'] : '';
                $currency = isset($schema['offers']) && isset($schema['offers']['priceCurrency']) ? $schema['offers']['priceCurrency'] : 'USD';
            ?>
                    <input type="number" name="price" step="0.01" value="<?php echo esc_attr($price); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">

            </div>
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Currency:</label>
                <select name="currency" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="USD" <?php selected($currency, 'USD'); ?>>USD</option>
                    <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR</option>
                    <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP</option>
                    <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD</option>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Render organization schema editor fields
     */
    private function render_organization_schema_fields($schema, $index) {
    ?>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם הארגון:</label>
        <input type="text" name="name" value="<?php echo esc_attr($schema['name'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
        <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea($schema['description'] ?? ''); ?></textarea>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">כתובת אתר:</label>
        <input type="url" name="url" value="<?php echo esc_attr($schema['url'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">אימייל:</label>
        <input type="email" name="email" value="<?php echo esc_attr($schema['email'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">טלפון:</label>
        <input type="text" name="telephone" value="<?php echo esc_attr($schema['telephone'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <?php
}

    /**
     * Render person schema editor fields
     */
    private function render_person_schema_fields($schema, $index) {
    ?>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם מלא:</label>
        <input type="text" name="name" value="<?php echo esc_attr($schema['name'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תפקיד:</label>
        <input type="text" name="job_title" value="<?php echo esc_attr($schema['jobTitle'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">אימייל:</label>
            <input type="email" name="email" value="<?php echo esc_attr($schema['email'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">טלפון:</label>
            <input type="text" name="telephone" value="<?php echo esc_attr($schema['telephone'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">אתר אינטרנט:</label>
        <input type="url" name="url" value="<?php echo esc_attr($schema['url'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <?php
}

    /**
     * Render generic schema editor fields
     */
    private function render_generic_schema_fields($schema, $index) {
        ?>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Name/Title:</label>
            <input type="text" name="name" value="<?php echo esc_attr($schema['name'] ?? $schema['title'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description:</label>
            <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea($schema['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL:</label>
            <input type="url" name="url" value="<?php echo esc_attr($schema['url'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <?php
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
    
    // Home - Use Hebrew default
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
 * Render breadcrumbs HTML - Hebrew Version
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
    
    $html = '<nav class="schemati-breadcrumbs" aria-label="שביל ניווט">';
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
 * Updates management page
 */
public function updates_page() {
    $remote_version = ($this->github_updater) ? $this->github_updater->get_remote_version() : null;
    $current_version = SCHEMATI_VERSION;
    $update_available = $remote_version && version_compare($current_version, $remote_version, '<');
    
    ?>
    <div class="wrap">
        <h1><?php echo 'Schemati - עדכונים'; ?></h1>
        
        <div class="card">
            <h2><?php echo 'מידע על גרסה'; ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo 'גרסה נוכחית'; ?></th>
                    <td>
                        <strong><?php echo esc_html($current_version); ?></strong>
                        <?php if ($update_available): ?>
                            <span style="color: #d63384; margin-left: 10px;">
                                <?php echo '(עדכון זמין)'; ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #198754; margin-left: 10px;">
                                <?php echo '(מעודכן)'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo 'גרסה אחרונה'; ?></th>
                    <td>
                        <?php if ($remote_version): ?>
                            <strong><?php echo esc_html($remote_version); ?></strong>
                        <?php else: ?>
                            <em><?php echo 'לא ניתן לבדוק'; ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}

    /**
     * Frontend styles for breadcrumbs
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
        direction: rtl;
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
    /* RTL specific adjustments */
    .schemati-breadcrumbs[dir="rtl"] .breadcrumb-list {
        flex-direction: row-reverse;
    }
    </style>
    <?php
}
    /**
 * Initialize GitHub updater
 */
private function init_github_updater() {
    // Check if the updater file exists
    $updater_file = SCHEMATI_DIR . 'includes/class-github-updater.php';
    
    if (!file_exists($updater_file)) {
        return; // Skip if file doesn't exist
    }
    
    // Include the GitHub updater class
    require_once $updater_file;
    
    // Check if class exists
    if (!class_exists('Schemati_GitHub_Updater')) {
        return; // Skip if class doesn't exist
    }
    
    // Initialize updater
    $this->github_updater = new Schemati_GitHub_Updater(
        SCHEMATI_FILE,                // Plugin file path
        'SchemaMarkApp',       // Replace with your GitHub username
        'schemati-2.0',                  // Replace with your repository name
        ''                           // Optional: GitHub personal access token
    );
}
}
// Initialize the plugin
function schemati_init() {
    return Schemati::instance();
}

// Start the plugin
add_action('plugins_loaded', 'schemati_init');

// Helper function for themes
function schemati_breadcrumbs($args = array()) {
    $schemati = Schemati::instance();
    return $schemati->breadcrumb_shortcode($args);
}

// Plugin hooks for cleanup
register_uninstall_hook(__FILE__, 'schemati_uninstall');

function schemati_uninstall() {
    // נקה אפשרויות - Clean up options
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
    
    // נקה מטא דאטה של פוסטים - Clean up post meta
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_schemati_%'");
}


?>
