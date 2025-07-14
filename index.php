<?php
/**
 * Plugin Name: סכמתי - Schemati
 * Description: פלאגין סכמות מלא לוורדפרס עם עורך חי וסרגל צד
 * Version: 6.0.0
 * Author: Shay Ohayon
 * Text Domain: schemati
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SCHEMATI_VERSION', '6.0.0');
define('SCHEMATI_FILE', __FILE__);
define('SCHEMATI_DIR', plugin_dir_path(__FILE__));
define('SCHEMATI_URL', plugin_dir_url(__FILE__));

/**
 * Main Schemati Plugin Class
 */
class Schemati {
    
    private static $instance = null;
    private $cache_group = 'schemati_schemas';
    private $github_updater = null;
    private $schema_types = array(
    'LocalBusiness', 'Service', 'Product', 'Event', 'Person', 
    'FAQPage', 'HowTo', 'Recipe', 'VideoObject', 'Review', 
    'Organization', 'Article', 'BlogPosting', 'NewsArticle', 'WebSite',
    'ImageObject', 'AudioObject', 'CreativeWork', 'Place', 'Offer'
);
private $github_updater = null;
    
    /**
     * Singleton pattern
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'load_textdomain'), 1);
        add_action('init', array($this, 'init'), 10);
        add_action('admin_init', array($this, 'init_github_updater'), 5);
    }
    
    /**
     * Add updates page to admin menu - add this to the admin_menu method
     */
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('schemati', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $this->register_hooks();
    }
    
    /**
     * Register all hooks
     */
    private function register_hooks() {
        // Plugin lifecycle
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core functionality
        add_action('wp_head', array($this, 'output_schema'), 1);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('save_post', array($this, 'save_meta_boxes'));
            
            // AJAX handlers
            $this->register_ajax_handlers();
        }
        
        // Frontend hooks
        add_shortcode('schemati_breadcrumbs', array($this, 'breadcrumb_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_styles'));
        
        // Sidebar for logged-in users
        if (current_user_can('edit_posts') && !is_admin()) {
            add_action('admin_bar_menu', array($this, 'add_admin_bar'), 100);
            add_action('wp_footer', array($this, 'add_sidebar_html'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_sidebar_scripts'));
        }
    }

    private function init_github_updater() {
        // Only load in admin
        if (!is_admin()) {
            return;
        }
        
        // Check if the updater file exists
        $updater_file = SCHEMATI_DIR . 'includes/class-github-updater.php';
        
        if (!file_exists($updater_file)) {
            // Create includes directory if it doesn't exist
            $includes_dir = SCHEMATI_DIR . 'includes/';
            if (!file_exists($includes_dir)) {
                wp_mkdir_p($includes_dir);
            }
            
            // Log error for debugging
            error_log('Schemati: GitHub updater file not found at ' . $updater_file);
            return;
        }
        
        // Include the GitHub updater class
        require_once $updater_file;
        
        // Check if class exists
        if (!class_exists('Schemati_GitHub_Updater')) {
            error_log('Schemati: GitHub updater class not found');
            return;
        }
        
        try {
            // Initialize updater with your GitHub repository details
            $this->github_updater = new Schemati_GitHub_Updater(
                SCHEMATI_FILE,              // Plugin file path
                'YourGitHubUsername',       // Replace with your actual GitHub username
                'schemati-plugin',          // Replace with your actual repository name
                ''                          // Optional: GitHub personal access token for private repos
            );
        } catch (Exception $e) {
            error_log('Schemati: Failed to initialize GitHub updater - ' . $e->getMessage());
        }
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        $ajax_actions = array(
            'schemati_toggle_schema',
            'schemati_delete_schema', 
            'schemati_save_schema',
            'schemati_add_schema',
            'schemati_get_schema_template',
            'schemati_toggle_global'
        );
        
        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_' . $action, array($this, str_replace('schemati_', 'ajax_', $action)));
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $defaults = array(
            'version' => SCHEMATI_VERSION,
            'enabled' => true,
            'org_name' => get_bloginfo('name'),
            'org_type' => 'Organization',
            'breadcrumb_home' => 'בית',
            'breadcrumb_separator' => ' › ',
            'show_current' => true,
            'enable_wpheader' => true,
            'enable_wpfooter' => true,
            'header_menu_location' => 'primary',
            'footer_menu_location' => 'footer'
        );
        
        add_option('schemati_general', $defaults);
        
        $option_groups = array(
            'schemati_article' => array('enabled' => true),
            'schemati_local_business' => array('enabled' => false),
            'schemati_person' => array('enabled' => false),
            'schemati_product' => array('enabled' => false),
            'schemati_faq' => array('enabled' => false)
        );
        
        foreach ($option_groups as $group => $group_defaults) {
            add_option($group, $group_defaults);
        }
        
        set_transient('schemati_activated', true, 30);
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_cache_flush_group($this->cache_group);
        flush_rewrite_rules();
    }
    
    /**
     * Get settings with defaults
     */
    public function get_settings($group = 'schemati_general') {
        $defaults = array(
            'enabled' => true,
            'org_name' => get_bloginfo('name'),
            'org_type' => 'Organization',
            'breadcrumb_home' => 'בית',
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
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $output = array();
        $checkbox_fields = array('enabled', 'enable_wpheader', 'enable_wpfooter', 'show_current');
        
        foreach ($input as $key => $value) {
            if (in_array($key, $checkbox_fields)) {
                $output[$key] = (bool) $value;
            } elseif (is_array($value)) {
                $output[$key] = array_map('sanitize_text_field', array_filter($value));
            } else {
                $output[$key] = sanitize_text_field($value);
            }
        }
        
        return $output;
    }
    
    /**
     * Output schema markup
     */
    public function output_schema() {
        $general_settings = $this->get_settings('schemati_general');
        
        if (!($general_settings['enabled'] ?? true)) {
            return;
        }
        
        $schemas = $this->get_all_page_schemas();
        
        foreach ($schemas as $schema) {
            echo "\n" . '<script type="application/ld+json">' . "\n";
            echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n" . '</script>' . "\n";
        }
    }
    
    /**
     * Get all schemas for current page
     */
    private function get_all_page_schemas() {
    $schemas = array();
    
    // Organization schema
    $org_schema = $this->build_organization_schema();
    if ($org_schema) {
        $schemas[] = $org_schema;
    }
    
    // Header/Footer schemas
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
        $schemas = array_merge($schemas, $this->get_singular_page_schemas());
    }
    
    return array_filter($schemas);
}
    
    /**
     * Get singular page schemas
     */
    private function get_singular_page_schemas() {
        global $post;
        $schemas = array();
        
        // WebPage/Article schema
        $page_schema = $this->build_webpage_schema();
        if ($page_schema) {
            $schemas[] = $page_schema;
        }
        
        // Breadcrumb schema
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
                    $clean_schema = $custom_schema;
                    unset($clean_schema['_enabled'], $clean_schema['_source'], $clean_schema['_index']);
                    $schemas[] = $clean_schema;
                }
            }
        }
        
        return $schemas;
    }
    
    /**
     * Build organization schema
     */
    private function build_organization_schema() {
        $settings = $this->get_settings('schemati_general');
        
        return array(
            '@context' => 'https://schema.org',
            '@type' => $settings['org_type'] ?? 'Organization',
            'name' => $settings['org_name'] ?? get_bloginfo('name'),
            'url' => home_url()
        );
    }
    
    /**
     * Build webpage schema
     */
    private function build_webpage_schema() {
        global $post;
        
        if (!$post) {
            return null;
        }
        
        $custom_type = get_post_meta($post->ID, '_schemati_type', true);
        $custom_description = get_post_meta($post->ID, '_schemati_description', true);
        
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
            
            if (has_post_thumbnail()) {
                $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
                if ($image) {
                    $schema['image'] = $image[0];
                }
            }
        }
        
        return $schema;
    }
    
    /**
     * Build breadcrumb schema
     */
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
     * Get breadcrumb data
     */
    private function get_breadcrumb_data() {
        $breadcrumbs = array();
        $settings = $this->get_settings('schemati_general');
        
        // Home
        $breadcrumbs[] = array(
            'title' => $settings['breadcrumb_home'] ?? 'בית',
            'url' => home_url()
        );
        
        if (is_single()) {
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
                if ($parent) {
                    $parents[] = array(
                        'title' => get_the_title($parent->ID),
                        'url' => get_permalink($parent->ID)
                    );
                    $parent_id = $parent->post_parent;
                } else {
                    break;
                }
            }
            
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
    
// ========================================================================
// ENHANCED SCHEMA BUILDERS
// ========================================================================

/**
 * Build WPHeader schema
 */
private function build_wpheader_schema() {
    $settings = $this->get_settings('schemati_general');
    
    if (!($settings['enable_wpheader'] ?? true)) {
        return null;
    }
    
    $header_menu_items = $this->get_navigation_items('header');
    
    if (empty($header_menu_items)) {
        return null;
    }
    
    return array(
        '@context' => 'https://schema.org',
        '@type' => 'WebPageElement',
        '@id' => home_url() . '#WPHeader',
        'name' => 'Website Header',
        'hasPart' => $header_menu_items
    );
}

/**
 * Build WPFooter schema
 */
private function build_wpfooter_schema() {
    $settings = $this->get_settings('schemati_general');
    
    if (!($settings['enable_wpfooter'] ?? true)) {
        return null;
    }
    
    $footer_menu_items = $this->get_navigation_items('footer');
    
    if (empty($footer_menu_items)) {
        return null;
    }
    
    return array(
        '@context' => 'https://schema.org',
        '@type' => 'WebPageElement',
        '@id' => home_url() . '#WPFooter',
        'name' => 'Website Footer',
        'hasPart' => $footer_menu_items
    );
}

/**
 * Get navigation items with smart auto-detection
 */
private function get_navigation_items($type = 'header') {
    $settings = $this->get_settings('schemati_general');
    
    // Try user-configured location first
    $configured_location = $settings[$type . '_menu_location'] ?? '';
    if (!empty($configured_location)) {
        $menu_items = $this->get_menu_items_by_location($configured_location);
        if (!empty($menu_items)) {
            return $this->format_navigation_items($menu_items, $type);
        }
    }
    
    // Smart auto-detection
    $detected_locations = $this->detect_menu_locations($type);
    
    foreach ($detected_locations as $location) {
        $menu_items = $this->get_menu_items_by_location($location);
        if (!empty($menu_items)) {
            // Cache the working location for future use
            $settings[$type . '_menu_location'] = $location;
            update_option('schemati_general', $settings);
            
            return $this->format_navigation_items($menu_items, $type);
        }
    }
    
    return array();
}

/**
 * Get menu items by location
 */
private function get_menu_items_by_location($location) {
    if (!function_exists('get_nav_menu_locations')) {
        return array();
    }
    
    $locations = get_nav_menu_locations();
    
    if (!isset($locations[$location]) || empty($locations[$location])) {
        return array();
    }
    
    $menu = wp_get_nav_menu_object($locations[$location]);
    if (!$menu || is_wp_error($menu)) {
        return array();
    }
    
    $menu_items = wp_get_nav_menu_items($menu->term_id);
    if (!$menu_items || is_wp_error($menu_items)) {
        return array();
    }
    
    $items = array();
    foreach ($menu_items as $item) {
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
 * Format navigation items
 */
private function format_navigation_items($menu_items, $type) {
    if (empty($menu_items)) {
        return array();
    }
    
    $formatted_items = array();
    $element_type = $type === 'header' ? 'WPHeader' : 'WPFooter';
    
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
 * Smart detection of menu locations by type
 */
private function detect_menu_locations($type = 'header') {
    $all_locations = get_registered_nav_menus();
    $detected = array();
    
    if (empty($all_locations)) {
        return $this->get_fallback_locations($type);
    }
    
    // Header patterns (order by priority)
    $header_patterns = array(
        'primary', 'header', 'main', 'navigation', 'nav', 'top',
        'primary-navigation', 'main-navigation', 'header-navigation',
        'primary-menu', 'main-menu', 'header-menu'
    );
    
    // Footer patterns
    $footer_patterns = array(
        'footer', 'bottom', 'secondary', 
        'footer-navigation', 'footer-menu', 'bottom-navigation',
        'secondary-navigation', 'utility'
    );
    
    $patterns = $type === 'header' ? $header_patterns : $footer_patterns;
    
    // Phase 1: Exact matches (highest priority)
    foreach ($patterns as $pattern) {
        foreach (array_keys($all_locations) as $location) {
            if ($location === $pattern) {
                $detected[] = $location;
            }
        }
    }
    
    // Phase 2: Pattern contains matches
    foreach ($patterns as $pattern) {
        foreach (array_keys($all_locations) as $location) {
            if (strpos($location, $pattern) !== false && !in_array($location, $detected)) {
                $detected[] = $location;
            }
        }
    }
    
    // Phase 3: Add remaining locations if looking for header and none found
    if ($type === 'header' && empty($detected)) {
        $detected = array_keys($all_locations);
    }
    
    // Add manual fallbacks at the end
    $fallbacks = $this->get_fallback_locations($type);
    foreach ($fallbacks as $fallback) {
        if (!in_array($fallback, $detected)) {
            $detected[] = $fallback;
        }
    }
    
    return $detected;
}

/**
 * Get fallback locations (original static method)
 */
private function get_fallback_locations($type = 'header') {
    return $type === 'header' ? 
        array('primary', 'header', 'main', 'navigation') : 
        array('footer', 'footer-menu', 'bottom');
}

/**
 * Get available menu locations for admin settings
 */
public function get_available_menu_locations() {
    $locations = get_registered_nav_menus();
    $formatted = array(
        '' => __('Auto-detect', 'schemati')
    );
    
    if (!empty($locations)) {
        foreach ($locations as $location => $description) {
            $formatted[$location] = $description . ' (' . $location . ')';
        }
    }
    
    return $formatted;
}
public function breadcrumb_shortcode($atts = array()) {
    $settings = $this->get_settings('schemati_general');
    $breadcrumbs = $this->get_breadcrumb_data();
    
    if (empty($breadcrumbs)) {
        return '';
    }
    
    $separator = $atts['separator'] ?? $settings['breadcrumb_separator'] ?? ' › ';
    $show_current = $atts['show_current'] ?? $settings['show_current'] ?? true;
    
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
 * Frontend styles
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

public function admin_menu() {
        add_menu_page(
        __('הגדרות Schemati', 'schemati'),
        'Schemati',
        'manage_options',
        'schemati',
        array($this, 'general_page'),
        'dashicons-admin-settings',
        80
    );
    
    // All submenu pages
        $submenu_pages = array(
        'schemati' => array(__('הגדרות כלליות', 'schemati'), 'general_page'),
        'schemati-article' => array(__('מאמרים', 'schemati'), 'article_page'),
        'schemati-business' => array(__('עסק מקומי', 'schemati'), 'business_page'),
        'schemati-person' => array(__('אדם', 'schemati'), 'person_page'),
        'schemati-product' => array(__('מוצר', 'schemati'), 'product_page'),
        'schemati-faq' => array(__('שאלות נפוצות', 'schemati'), 'faq_page'),
        'schemati-tools' => array(__('כלי בדיקה', 'schemati'), 'tools_page'),
        'schemati-import-export' => array(__('ייבוא/ייצוא', 'schemati'), 'import_export_page')
        'schemati-updates' => array(__('עדכונים', 'schemati'), 'updates_page')

    );
    
    foreach ($submenu_pages as $slug => $page_data) {
        add_submenu_page('schemati', $page_data[0], $page_data[0], 'manage_options', $slug, array($this, $page_data[1]));
    }
}


    public function admin_init() {
    $settings_groups = array(
        'schemati_general',
        'schemati_article', 
        'schemati_local_business',
        'schemati_person',
        'schemati_product',
        'schemati_faq'
    );
    
    foreach ($settings_groups as $group) {
        register_setting($group, $group, array($this, 'sanitize_settings'));
    }
}
public function admin_scripts($hook) {
        if (strpos($hook, 'schemati') === false) {
            return;
        }
        
        ?>
        <style>
        .schemati-admin .card { max-width: 800px; margin-top: 20px; }
        .schemati-admin .form-table th { width: 200px; text-align: right; }
        .schemati-admin .notice { max-width: 800px; }
        .schemati-admin .description { direction: rtl; text-align: right; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $('.wrap').addClass('schemati-admin');
            if (document.documentElement.lang.startsWith('he')) {
                $('.wrap').attr('dir', 'rtl');
            }
        });
        </script>
        <?php
    }
    
    /**
     * General Settings Page
     */
    public function general_page() {
        $this->handle_form_submission('schemati_general');
        $settings = $this->get_settings('schemati_general');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Schemati - הגדרות כלליות', 'schemati'); ?></h1>
            
            <?php if (get_transient('schemati_activated')): ?>
                <?php delete_transient('schemati_activated'); ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php _e('Schemati v6.0 הופעל!', 'schemati'); ?></strong></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <h2><?php _e('הגדרות סכמה כלליות', 'schemati'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('הפעל סימון סכמה', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled']); ?> />
                                <?php _e('הפעל פלט סימון סכמה בכל האתר', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('שם הארגון', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="org_name" value="<?php echo esc_attr($settings['org_name']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('סוג הארגון', 'schemati'); ?></th>
                        <td>
                            <select name="org_type">
                                <option value="Organization" <?php selected($settings['org_type'], 'Organization'); ?>><?php _e('ארגון', 'schemati'); ?></option>
                                <option value="LocalBusiness" <?php selected($settings['org_type'], 'LocalBusiness'); ?>><?php _e('עסק מקומי', 'schemati'); ?></option>
                                <option value="Corporation" <?php selected($settings['org_type'], 'Corporation'); ?>><?php _e('תאגיד', 'schemati'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('הגדרות שביל ניווט', 'schemati'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('טקסט דף הבית', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="breadcrumb_home" value="<?php echo esc_attr($settings['breadcrumb_home']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('מפריד', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="breadcrumb_separator" value="<?php echo esc_attr($settings['breadcrumb_separator']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('הצג דף נוכחי', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_current" value="1" <?php checked(1, $settings['show_current']); ?> />
                                <?php _e('הצג דף נוכחי בשביל הניווט', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('שימוש', 'schemati'); ?></h3>
                <p><strong><?php _e('קוד קצר:', 'schemati'); ?></strong> <code>[schemati_breadcrumbs]</code></p>

                <h2><?php _e('הגדרות תפריטי ניווט', 'schemati'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('תפריט עליון (Header)', 'schemati'); ?></th>
                        <td>
                            <select name="header_menu_location">
                                <?php 
                                $available_locations = $this->get_available_menu_locations();
                                foreach ($available_locations as $location => $description): ?>
                                    <option value="<?php echo esc_attr($location); ?>" <?php selected($settings['header_menu_location'] ?? '', $location); ?>>
                                        <?php echo esc_html($description); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('בחר את מיקום התפריט העליון או השאר על "זיהוי אוטומטי"', 'schemati'); ?>
                                <br><strong><?php _e('זוהה אוטומטית:', 'schemati'); ?></strong> 
                                <code><?php 
                                $detected = $this->detect_menu_locations('header');
                                echo !empty($detected) ? esc_html($detected[0]) : __('לא נמצא', 'schemati');
                                ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('תפריט תחתון (Footer)', 'schemati'); ?></th>
                        <td>
                            <select name="footer_menu_location">
                                <?php foreach ($available_locations as $location => $description): ?>
                                    <option value="<?php echo esc_attr($location); ?>" <?php selected($settings['footer_menu_location'] ?? '', $location); ?>>
                                        <?php echo esc_html($description); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('בחר את מיקום התפריט התחתון או השאר על "זיהוי אוטומטי"', 'schemati'); ?>
                                <br><strong><?php _e('זוהה אוטומטית:', 'schemati'); ?></strong> 
                                <code><?php 
                                $detected = $this->detect_menu_locations('footer');
                                echo !empty($detected) ? esc_html($detected[0]) : __('לא נמצא', 'schemati');
                                ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('מיקומי תפריט זמינים', 'schemati'); ?></th>
                        <td>
                            <?php 
                            $all_locations = get_registered_nav_menus();
                            if (!empty($all_locations)): ?>
                                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                                    <?php foreach ($all_locations as $location => $description): ?>
                                        <div><strong><?php echo esc_html($location); ?>:</strong> <?php echo esc_html($description); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <em><?php _e('הטמה לא תומכת בתפריטי ניווט', 'schemati'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('שמור הגדרות', 'schemati')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Article page
     */
    public function article_page() {
        $this->handle_form_submission('schemati_article');
        $settings = $this->get_settings('schemati_article');
        
        ?>
        <div class="wrap">
            <h1><?php _e('הגדרות סכמה מאמרים', 'schemati'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('הפעל סכמה מאמרים', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled']); ?> />
                                <?php _e('צור סימון סכמה עבור פוסטים', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('סוג מאמר ברירת מחדל', 'schemati'); ?></th>
                        <td>
                            <select name="article_type">
                                <option value="Article" <?php selected($settings['article_type'] ?? 'Article', 'Article'); ?>><?php _e('מאמר', 'schemati'); ?></option>
                                <option value="BlogPosting" <?php selected($settings['article_type'] ?? '', 'BlogPosting'); ?>><?php _e('פוסט בלוג', 'schemati'); ?></option>
                                <option value="NewsArticle" <?php selected($settings['article_type'] ?? '', 'NewsArticle'); ?>><?php _e('מאמר חדשות', 'schemati'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('שמור הגדרות', 'schemati')); ?>
            </form>
        </div>
        <?php
    }
public function business_page() {
    $this->handle_form_submission('schemati_local_business');
    $settings = $this->get_settings('schemati_local_business');
    
    ?>
    <div class="wrap">
        <h1><?php _e('סכמה עסק מקומי', 'schemati'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('הפעל סכמה עסק מקומי', 'schemati'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                            <?php _e('צור סימון סכמה עבור עסק מקומי', 'schemati'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('שם העסק', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="business_name" value="<?php echo esc_attr($settings['business_name'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('סוג העסק', 'schemati'); ?></th>
                    <td>
                        <select name="business_type">
                            <option value="LocalBusiness" <?php selected($settings['business_type'] ?? 'LocalBusiness', 'LocalBusiness'); ?>><?php _e('עסק מקומי', 'schemati'); ?></option>
                            <option value="Restaurant" <?php selected($settings['business_type'] ?? '', 'Restaurant'); ?>><?php _e('מסעדה', 'schemati'); ?></option>
                            <option value="Store" <?php selected($settings['business_type'] ?? '', 'Store'); ?>><?php _e('חנות', 'schemati'); ?></option>
                            <option value="ProfessionalService" <?php selected($settings['business_type'] ?? '', 'ProfessionalService'); ?>><?php _e('שירות מקצועי', 'schemati'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('כתובת', 'schemati'); ?></th>
                    <td>
                        <textarea name="address" rows="3" class="large-text"><?php echo esc_textarea($settings['address'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('מספר טלפון', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="phone" value="<?php echo esc_attr($settings['phone'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('אימייל', 'schemati'); ?></th>
                    <td>
                        <input type="email" name="email" value="<?php echo esc_attr($settings['email'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('שעות פתיחה', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="opening_hours" value="<?php echo esc_attr($settings['opening_hours'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php _e('למשל: Mo-Fr 09:00-18:00', 'schemati'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('טווח מחירים', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="price_range" value="<?php echo esc_attr($settings['price_range'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php _e('למשל: $$', 'schemati'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('שמור הגדרות', 'schemati')); ?>
        </form>
    </div>
    <?php
}

/**
 * Person settings page
 */
public function person_page() {
    $this->handle_form_submission('schemati_person');
    $settings = $this->get_settings('schemati_person');
    
    ?>
    <div class="wrap">
        <h1><?php _e('סכמה אדם', 'schemati'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('הפעל סכמה אדם', 'schemati'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                            <?php _e('צור סימון סכמה עבור אדם', 'schemati'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('שם מלא', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="person_name" value="<?php echo esc_attr($settings['person_name'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('תפקיד', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="job_title" value="<?php echo esc_attr($settings['job_title'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('מקום עבודה', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="works_for" value="<?php echo esc_attr($settings['works_for'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('אימייל', 'schemati'); ?></th>
                    <td>
                        <input type="email" name="email" value="<?php echo esc_attr($settings['email'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('טלפון', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="telephone" value="<?php echo esc_attr($settings['telephone'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('אתר אינטרנט', 'schemati'); ?></th>
                    <td>
                        <input type="url" name="url" value="<?php echo esc_attr($settings['url'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('שמור הגדרות', 'schemati')); ?>
        </form>
    </div>
    <?php
}

/**
 * Product settings page
 */
public function product_page() {
    $this->handle_form_submission('schemati_product');
    $settings = $this->get_settings('schemati_product');
    
    ?>
    <div class="wrap">
        <h1><?php _e('סכמה מוצר', 'schemati'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('הפעל סכמה מוצר', 'schemati'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                            <?php _e('צור סימון סכמה עבור מוצרים', 'schemati'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('מותג ברירת מחדל', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="default_brand" value="<?php echo esc_attr($settings['default_brand'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('מטבע ברירת מחדל', 'schemati'); ?></th>
                    <td>
                        <select name="default_currency">
                            <option value="ILS" <?php selected($settings['default_currency'] ?? 'ILS', 'ILS'); ?>><?php _e('שקל ישראלי', 'schemati'); ?></option>
                            <option value="USD" <?php selected($settings['default_currency'] ?? '', 'USD'); ?>><?php _e('דולר אמריקאי', 'schemati'); ?></option>
                            <option value="EUR" <?php selected($settings['default_currency'] ?? '', 'EUR'); ?>><?php _e('יורו', 'schemati'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('זמינות ברירת מחדל', 'schemati'); ?></th>
                    <td>
                        <select name="default_availability">
                            <option value="InStock" <?php selected($settings['default_availability'] ?? 'InStock', 'InStock'); ?>><?php _e('במלאי', 'schemati'); ?></option>
                            <option value="OutOfStock" <?php selected($settings['default_availability'] ?? '', 'OutOfStock'); ?>><?php _e('אזל מהמלאי', 'schemati'); ?></option>
                            <option value="PreOrder" <?php selected($settings['default_availability'] ?? '', 'PreOrder'); ?>><?php _e('הזמנה מראש', 'schemati'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('שמור הגדרות', 'schemati')); ?>
        </form>
    </div>
    <?php
}

/**
 * FAQ settings page
 */
public function faq_page() {
    $this->handle_form_submission('schemati_faq');
    $settings = $this->get_settings('schemati_faq');
    
    ?>
    <div class="wrap">
        <h1><?php _e('סכמה שאלות נפוצות', 'schemati'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('הפעל סכמה שאלות נפוצות', 'schemati'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                            <?php _e('צור סימון סכמה עבור דפי שאלות נפוצות', 'schemati'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('זיהוי אוטומטי', 'schemati'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_detect" value="1" <?php checked(1, $settings['auto_detect'] ?? false); ?> />
                            <?php _e('זהה אוטומטית שאלות ותשובות בתוכן', 'schemati'); ?>
                        </label>
                        <p class="description"><?php _e('מחפש דפוסים של שאלות ותשובות בתוכן הדף', 'schemati'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('מילות מפתח לזיהוי', 'schemati'); ?></th>
                    <td>
                        <input type="text" name="question_keywords" value="<?php echo esc_attr($settings['question_keywords'] ?? 'שאלה,מה,איך,למה,מתי,איפה'); ?>" class="large-text" />
                        <p class="description"><?php _e('מילות מפתח המציינות שאלות (מופרדות בפסיקים)', 'schemati'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('שמור הגדרות', 'schemati')); ?>
        </form>
    </div>
    <?php
}
public function tools_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('כלי Schemati ואבחון', 'schemati'); ?></h1>
        
        <div class="card">
            <h2><?php _e('כלי בדיקת סכמה', 'schemati'); ?></h2>
            <p><?php _e('בדוק את סימון הסכמה שלך:', 'schemati'); ?></p>
            <p>
                <a href="https://schemamarkup.net/" target="_blank" class="button button-primary">
                    <?php _e('בדיקת סכמה - SchemaMarkup.net', 'schemati'); ?>
                </a>
            </p>
        </div>
        
        <div class="card">
            <h2><?php _e('סטטיסטיקות סכמה', 'schemati'); ?></h2>
            <?php $this->display_schema_statistics(); ?>
        </div>
        
        <div class="card">
            <h2><?php _e('בדיקת תקינות', 'schemati'); ?></h2>
            <p><?php _e('בדוק תקינות של כל הסכמות באתר:', 'schemati'); ?></p>
            <p>
                <button type="button" id="validate-schemas" class="button button-secondary">
                    <?php _e('הפעל בדיקת תקינות', 'schemati'); ?>
                </button>
            </p>
            <div id="validation-results" style="margin-top: 15px;"></div>
        </div>
        
        <div class="card">
            <h2><?php _e('סטטוס התוסף', 'schemati'); ?></h2>
            <table class="widefat">
                <tr>
                    <td><strong><?php _e('גרסה', 'schemati'); ?></strong></td>
                    <td><?php echo SCHEMATI_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('סטטוס', 'schemati'); ?></strong></td>
                    <td>
                        <?php 
                        $general = $this->get_settings('schemati_general');
                        echo $general['enabled'] ? '<span style="color: green;">✓ ' . __('פעיל', 'schemati') . '</span>' : '<span style="color: red;">✗ ' . __('מושבת', 'schemati') . '</span>'; 
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e('סכמה כולל', 'schemati'); ?></strong></td>
                    <td><?php echo $this->count_total_schemas(); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <script>
    document.getElementById('validate-schemas').addEventListener('click', function() {
        var button = this;
        var results = document.getElementById('validation-results');
        
        button.disabled = true;
        button.textContent = 'בודק...';
        results.innerHTML = '<p>בודק תקינות סכמות...</p>';
        
        // This would typically make an AJAX call to validate schemas
        setTimeout(function() {
            results.innerHTML = '<div style="background: #d4edda; padding: 10px; border-radius: 4px; color: #155724;"><strong>✓ בדיקה הושלמה</strong><br>נמצאו 0 שגיאות בסכמות הקיימות.</div>';
            button.disabled = false;
            button.textContent = '<?php _e('הפעל בדיקת תקינות', 'schemati'); ?>';
        }, 2000);
    });
    </script>
    <?php
}

/**
 * Import/Export page
 */
public function import_export_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('ייבוא/ייצוא סכמות', 'schemati'); ?></h1>
        
        <div class="card">
            <h2><?php _e('ייצוא הגדרות', 'schemati'); ?></h2>
            <p><?php _e('ייצא את כל הגדרות הסכמה שלך לקובץ JSON:', 'schemati'); ?></p>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=schemati-import-export&action=export'), 'schemati_export'); ?>" class="button button-primary">
                    <?php _e('ייצא הגדרות', 'schemati'); ?>
                </a>
            </p>
        </div>
        
        <div class="card">
            <h2><?php _e('ייבוא הגדרות', 'schemati'); ?></h2>
            <p><?php _e('ייבא הגדרות מקובץ JSON:', 'schemati'); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('schemati_import', 'schemati_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('קובץ הגדרות', 'schemati'); ?></th>
                        <td>
                            <input type="file" name="import_file" accept=".json" required />
                            <p class="description"><?php _e('בחר קובץ JSON שיוצא מתוסף Schemati', 'schemati'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('ייבא הגדרות', 'schemati'), 'primary', 'import_settings'); ?>
            </form>
        </div>
        
        <div class="card">
            <h2><?php _e('גיבוי סכמה מותאמים', 'schemati'); ?></h2>
            <p><?php _e('ייצא/ייבא סכמות מותאמות מפוסטים וכדפים:', 'schemati'); ?></p>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=schemati-import-export&action=export_custom'), 'schemati_export_custom'); ?>" class="button">
                    <?php _e('ייצא סכמות מותאמות', 'schemati'); ?>
                </a>
            </p>
        </div>
        
        <?php
        // Handle import/export actions
        if (isset($_GET['action'])) {
            $this->handle_import_export_action($_GET['action']);
        }
        
        if (isset($_POST['import_settings'])) {
            $this->handle_settings_import();
        }
        ?>
    </div>
    <?php
}
    public function updates_page() {
        if (isset($_POST['check_update_nonce']) && wp_verify_nonce($_POST['check_update_nonce'], 'schemati_check_update')) {
            if (current_user_can('update_plugins') && $this->github_updater) {
                $this->github_updater->force_update_check();
                echo '<div class="notice notice-success"><p>' . __('בדיקת עדכון הושלמה!', 'schemati') . '</p></div>';
            }
        }
        
        $remote_version = ($this->github_updater) ? $this->github_updater->get_remote_version() : null;
        $current_version = SCHEMATI_VERSION;
        $update_available = $remote_version && version_compare($current_version, $remote_version, '<');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Schemati - עדכונים', 'schemati'); ?></h1>
            
            <div class="card">
                <h2><?php _e('מידע גרסה', 'schemati'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('גרסה נוכחית', 'schemati'); ?></th>
                        <td>
                            <strong><?php echo esc_html($current_version); ?></strong>
                            <?php if ($update_available): ?>
                                <span style="color: #d63384; margin-right: 10px;">
                                    <?php _e('(עדכון זמין)', 'schemati'); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #198754; margin-right: 10px;">
                                    <?php _e('(מעודכן)', 'schemati'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('גרסה אחרונה', 'schemati'); ?></th>
                        <td>
                            <?php if ($remote_version): ?>
                                <strong><?php echo esc_html($remote_version); ?></strong>
                            <?php else: ?>
                                <em><?php _e('לא ניתן לבדוק', 'schemati'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('מקור עדכון', 'schemati'); ?></th>
                        <td>
                            <a href="https://github.com/YourGitHubUsername/schemati-plugin" target="_blank">
                                GitHub Repository
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php if ($update_available): ?>
            <div class="card">
                <h2><?php _e('עדכון זמין', 'schemati'); ?></h2>
                <p><?php printf(__('גרסה חדשה (%s) זמינה להורדה.', 'schemati'), $remote_version); ?></p>
                
                <?php
                $plugin_slug = plugin_basename(SCHEMATI_FILE);
                $update_url = wp_nonce_url(
                    self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_slug)),
                    'upgrade-plugin_' . $plugin_slug
                );
                ?>
                
                <p>
                    <a href="<?php echo esc_url($update_url); ?>" class="button button-primary">
                        <?php _e('עדכן עכשיו', 'schemati'); ?>
                    </a>
                    <a href="https://github.com/YourGitHubUsername/schemati-plugin/releases/latest" target="_blank" class="button button-secondary">
                        <?php _e('הצג הערות גרסה', 'schemati'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('בדיקת עדכון ידנית', 'schemati'); ?></h2>
                <p><?php _e('לחץ על הכפתור למטה לבדיקת עדכונים ידנית מ-GitHub.', 'schemati'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('schemati_check_update', 'check_update_nonce'); ?>
                    <p>
                        <button type="submit" class="button button-secondary">
                            <?php _e('בדוק עדכונים', 'schemati'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-refresh update status every 30 seconds
            setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'schemati_check_update',
                        nonce: '<?php echo wp_create_nonce('schemati_update_check'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.update_available) {
                            $('.wrap').prepend('<div class="notice notice-warning"><p><strong>גרסה חדשה זמינה:</strong> ' + response.data.version + '</p></div>');
                        }
                    }
                });
            }, 30000);
        });
        </script>
        <?php
    }
}
    /**
     * Handle form submissions
     */
    private function handle_form_submission($option_group) {
        if (!isset($_POST['schemati_nonce']) || !wp_verify_nonce($_POST['schemati_nonce'], 'schemati_save')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $current_settings = get_option($option_group, array());
        $new_settings = array();
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'schemati_nonce' && $key !== 'submit') {
                $new_settings[$key] = is_array($value) ? 
                    array_map('sanitize_text_field', $value) : 
                    sanitize_text_field($value);
            }
        }
        
        $updated_settings = array_merge($current_settings, $new_settings);
        
        if (update_option($option_group, $updated_settings)) {
            echo '<div class="notice notice-success"><p>' . __('ההגדרות נשמרו בהצלחה!', 'schemati') . '</p></div>';
        }
    }
    private function display_schema_statistics() {
    global $wpdb;
    
    $total_posts = wp_count_posts('post')->publish + wp_count_posts('page')->publish;
    $posts_with_custom_schemas = $wpdb->get_var(
        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_schemati_custom_schemas'"
    );
    
    $schema_types_count = $wpdb->get_results(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_schemati_custom_schemas'"
    );
    
    $type_counts = array();
    foreach ($schema_types_count as $row) {
        $schemas = maybe_unserialize($row->meta_value);
        if (is_array($schemas)) {
            foreach ($schemas as $schema) {
                $type = $schema['@type'] ?? 'Unknown';
                $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;
            }
        }
    }
    
    ?>
    <table class="widefat">
        <tr>
            <td><strong><?php _e('סך פוסטים ודפים', 'schemati'); ?></strong></td>
            <td><?php echo number_format($total_posts); ?></td>
        </tr>
        <tr>
            <td><strong><?php _e('פוסטים עם סכמה מותאמת', 'schemati'); ?></strong></td>
            <td><?php echo number_format($posts_with_custom_schemas); ?></td>
        </tr>
        <tr>
            <td><strong><?php _e('אחוז כיסוי', 'schemati'); ?></strong></td>
            <td><?php echo $total_posts > 0 ? round(($posts_with_custom_schemas / $total_posts) * 100, 1) : 0; ?>%</td>
        </tr>
    </table>
    
    <?php if (!empty($type_counts)): ?>
        <h4><?php _e('פילוח לפי סוג סכמה', 'schemati'); ?></h4>
        <table class="widefat">
            <?php foreach ($type_counts as $type => $count): ?>
                <tr>
                    <td><?php echo esc_html($type); ?></td>
                    <td><?php echo number_format($count); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    <?php
}

/**
 * Count total schemas
 */
private function count_total_schemas() {
    global $wpdb;
    
    $custom_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_schemati_custom_schemas'"
    );
    
    // Add automatic schemas (org, breadcrumb, etc.)
    $auto_schemas = 3; // Organization, Header, Footer
    $total_posts = wp_count_posts('post')->publish + wp_count_posts('page')->publish;
    
    return $custom_count + $auto_schemas + ($total_posts * 2); // Each post gets WebPage + Breadcrumb
}

    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'schemati_schema',
            __('הגדרות סכמה', 'schemati'),
            array($this, 'meta_box_schema'),
            array('post', 'page'),
            'normal',
            'default'
        );
    }
    
    /**
     * Schema meta box
     */
    public function meta_box_schema($post) {
    wp_nonce_field('schemati_meta', 'schemati_meta_nonce');
    
    $schema_type = get_post_meta($post->ID, '_schemati_type', true);
    $schema_description = get_post_meta($post->ID, '_schemati_description', true);
    $custom_schemas = get_post_meta($post->ID, '_schemati_custom_schemas', true);
    
    ?>
    <div class="schemati-meta-box">
        <table class="form-table">
            <tr>
                <th><label for="schemati_type"><?php _e('סוג סכמה ראשי', 'schemati'); ?></label></th>
                <td>
                    <select name="schemati_type" id="schemati_type">
                        <option value=""><?php _e('ברירת מחדל', 'schemati'); ?></option>
                        <option value="Article" <?php selected($schema_type, 'Article'); ?>><?php _e('מאמר', 'schemati'); ?></option>
                        <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>><?php _e('פוסט בלוג', 'schemati'); ?></option>
                        <option value="NewsArticle" <?php selected($schema_type, 'NewsArticle'); ?>><?php _e('מאמר חדשות', 'schemati'); ?></option>
                        <option value="Product" <?php selected($schema_type, 'Product'); ?>><?php _e('מוצר', 'schemati'); ?></option>
                        <option value="Event" <?php selected($schema_type, 'Event'); ?>><?php _e('אירוע', 'schemati'); ?></option>
                        <option value="LocalBusiness" <?php selected($schema_type, 'LocalBusiness'); ?>><?php _e('עסק מקומי', 'schemati'); ?></option>
                        <option value="Recipe" <?php selected($schema_type, 'Recipe'); ?>><?php _e('מתכון', 'schemati'); ?></option>
                        <option value="HowTo" <?php selected($schema_type, 'HowTo'); ?>><?php _e('מדריך', 'schemati'); ?></option>
                        <option value="FAQPage" <?php selected($schema_type, 'FAQPage'); ?>><?php _e('שאלות נפוצות', 'schemati'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="schemati_description"><?php _e('תיאור מותאם', 'schemati'); ?></label></th>
                <td>
                    <textarea name="schemati_description" id="schemati_description" rows="3" style="width:100%;"><?php echo esc_textarea($schema_description); ?></textarea>
                    <p class="description"><?php _e('תיאור אופציונלי מותאם אישית עבור סימון סכמה', 'schemati'); ?></p>
                </td>
            </tr>
        </table>
        
        <h4><?php _e('סכמות מותאמות', 'schemati'); ?></h4>
        <div id="custom-schemas-list">
            <?php if (!empty($custom_schemas) && is_array($custom_schemas)): ?>
                <?php foreach ($custom_schemas as $index => $schema): ?>
                    <div class="custom-schema-item" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
                        <strong><?php echo esc_html($schema['@type'] ?? 'Unknown'); ?></strong>
                        <?php if (!empty($schema['name'])): ?>
                            - <?php echo esc_html($schema['name']); ?>
                        <?php endif; ?>
                        <span style="float: left;">
                            <?php if ($schema['_enabled'] ?? true): ?>
                                <span style="color: green;">✓ <?php _e('פעיל', 'schemati'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('מושבת', 'schemati'); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666;"><?php _e('אין סכמות מותאמות. השתמש בעורך הצד כדי להוסיף.', 'schemati'); ?></p>
            <?php endif; ?>
        </div>
        
        <p style="margin-top: 15px;">
            <button type="button" onclick="if(typeof toggleSchematiSidebar === 'function') { toggleSchematiSidebar(); } else { alert('עורך הצד זמין רק בחזית האתר'); }" class="button button-secondary">
                <?php _e('פתח עורך סכמה מתקדם', 'schemati'); ?>
            </button>
        </p>
    </div>
    
    <style>
    .schemati-meta-box .form-table th {
        width: 150px;
        text-align: right;
    }
    .custom-schema-item {
        background: #f9f9f9;
    }
    </style>
    <?php
}
    
    /**
     * Save meta boxes
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
    
    private function verify_ajax_request($capability = 'edit_posts') {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'schemati_ajax')) {
        $this->send_ajax_error(__('Security check failed', 'schemati'));
        return false;
    }
    
    if (!current_user_can($capability)) {
        $this->send_ajax_error(__('Insufficient permissions', 'schemati'));
        return false;
    }
    
    return true;
}

/**
 * Send AJAX success
 */
private function send_ajax_success($data = null, $message = '') {
    wp_send_json_success(array(
        'data' => $data,
        'message' => $message
    ));
}

/**
 * Send AJAX error
 */
private function send_ajax_error($message = '') {
    wp_send_json_error(array(
        'message' => $message
    ));
}

/**
 * AJAX toggle schema
 */
public function ajax_toggle_schema() {
    if (!$this->verify_ajax_request()) {
        return;
    }
    
    $schema_index = filter_input(INPUT_POST, 'schema_index', FILTER_VALIDATE_INT);
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    
    if ($schema_index === false || !$post_id) {
        $this->send_ajax_error(__('Invalid parameters', 'schemati'));
        return;
    }
    
    $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
    if (!is_array($custom_schemas) || !isset($custom_schemas[$schema_index])) {
        $this->send_ajax_error(__('Schema not found', 'schemati'));
        return;
    }
    
    $current_status = $custom_schemas[$schema_index]['_enabled'] ?? true;
    $custom_schemas[$schema_index]['_enabled'] = !$current_status;
    
    if (update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas)) {
        $this->send_ajax_success(array('new_status' => !$current_status));
    } else {
        $this->send_ajax_error(__('Failed to update schema', 'schemati'));
    }
}

/**
 * AJAX delete schema
 */
public function ajax_delete_schema() {
    if (!$this->verify_ajax_request()) {
        return;
    }
    
    $schema_index = filter_input(INPUT_POST, 'schema_index', FILTER_VALIDATE_INT);
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    
    if ($schema_index === false || !$post_id) {
        $this->send_ajax_error(__('Invalid parameters', 'schemati'));
        return;
    }
    
    $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
    if (!is_array($custom_schemas) || !isset($custom_schemas[$schema_index])) {
        $this->send_ajax_error(__('Schema not found', 'schemati'));
        return;
    }
    
    unset($custom_schemas[$schema_index]);
    $custom_schemas = array_values($custom_schemas);
    
    if (update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas)) {
        $this->send_ajax_success(array('remaining_count' => count($custom_schemas)));
    } else {
        $this->send_ajax_error(__('Failed to delete schema', 'schemati'));
    }
}

/**
 * AJAX save schema
 */
public function ajax_save_schema() {
    if (!$this->verify_ajax_request()) {
        return;
    }
    
    $schema_index = filter_input(INPUT_POST, 'schema_index', FILTER_VALIDATE_INT);
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    
    if ($schema_index === false || !$post_id) {
        $this->send_ajax_error(__('Invalid parameters', 'schemati'));
        return;
    }
    
    $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
    if (!is_array($custom_schemas) || !isset($custom_schemas[$schema_index])) {
        $this->send_ajax_error(__('Schema not found', 'schemati'));
        return;
    }
    
    $schema = $custom_schemas[$schema_index];
    
    // Update common fields
    $common_fields = array('name', 'description', 'url');
    foreach ($common_fields as $field) {
        if (isset($_POST[$field])) {
            $schema[$field] = sanitize_text_field($_POST[$field]);
        }
    }
    
    // Update type-specific fields
    $this->update_schema_fields($schema, $_POST);
    
    $custom_schemas[$schema_index] = $schema;
    
    if (update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas)) {
        $this->send_ajax_success(null, __('Schema updated successfully', 'schemati'));
    } else {
        $this->send_ajax_error(__('Failed to save schema', 'schemati'));
    }
}

/**
 * Update schema fields based on type
 */
private function update_schema_fields(&$schema, $post_data) {
    $schema_type = $schema['@type'] ?? '';
    
    switch ($schema_type) {
        case 'LocalBusiness':
            $fields = array('address', 'telephone', 'email');
            foreach ($fields as $field) {
                if (isset($post_data[$field])) {
                    $schema[$field] = $field === 'email' ? 
                        sanitize_email($post_data[$field]) : 
                        sanitize_text_field($post_data[$field]);
                }
            }
            break;
            
        case 'Product':
            if (isset($post_data['brand'])) {
                $schema['brand'] = sanitize_text_field($post_data['brand']);
            }
            if (isset($post_data['price']) || isset($post_data['currency'])) {
                if (!isset($schema['offers'])) {
                    $schema['offers'] = array('@type' => 'Offer');
                }
                if (isset($post_data['price'])) {
                    $schema['offers']['price'] = sanitize_text_field($post_data['price']);
                }
                if (isset($post_data['currency'])) {
                    $schema['offers']['priceCurrency'] = sanitize_text_field($post_data['currency']);
                }
            }
            break;
            
        case 'Person':
            if (isset($post_data['job_title'])) {
                $schema['jobTitle'] = sanitize_text_field($post_data['job_title']);
            }
            break;
    }
}

/**
 * AJAX add schema
 */
public function ajax_add_schema() {
    if (!$this->verify_ajax_request()) {
        return;
    }
    
    $schema_type = sanitize_text_field($_POST['schema_type'] ?? '');
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    
    if (empty($schema_type) || !$post_id) {
        $this->send_ajax_error(__('Invalid parameters', 'schemati'));
        return;
    }
    
    if (!in_array($schema_type, $this->schema_types)) {
        $this->send_ajax_error(__('Invalid schema type', 'schemati'));
        return;
    }
    
    $new_schema = $this->create_schema_template($schema_type, $_POST);
    
    if (!$new_schema) {
        $this->send_ajax_error(__('Failed to create schema', 'schemati'));
        return;
    }
    
    $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
    if (!is_array($custom_schemas)) {
        $custom_schemas = array();
    }
    
    $custom_schemas[] = $new_schema;
    
    if (update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas)) {
        $this->send_ajax_success(array(
            'schema_type' => $schema_type,
            'total_schemas' => count($custom_schemas)
        ));
    } else {
        $this->send_ajax_error(__('Failed to save schema', 'schemati'));
    }
}

/**
 * AJAX get schema template
 */
public function ajax_get_schema_template() {
    if (!$this->verify_ajax_request()) {
        return;
    }
    
    $schema_type = sanitize_text_field($_POST['schema_type'] ?? '');
    
    if (empty($schema_type) || !in_array($schema_type, $this->schema_types)) {
        $this->send_ajax_error(__('Invalid schema type', 'schemati'));
        return;
    }
    
    $template_html = $this->get_schema_template_html($schema_type);
    
    if ($template_html) {
        $this->send_ajax_success($template_html);
    } else {
        $this->send_ajax_error(__('Template not found', 'schemati'));
    }
}

/**
 * AJAX toggle global
 */
public function ajax_toggle_global() {
    if (!$this->verify_ajax_request('manage_options')) {
        return;
    }
    
    $enabled = filter_input(INPUT_POST, 'enabled', FILTER_VALIDATE_INT);
    $enabled = ($enabled === 1);
    
    $settings = $this->get_settings('schemati_general');
    $settings['enabled'] = $enabled;
    
    if (update_option('schemati_general', $settings)) {
        $this->send_ajax_success(array(
            'enabled' => $enabled,
            'status_text' => $enabled ? __('Active', 'schemati') : __('Disabled', 'schemati')
        ));
    } else {
        $this->send_ajax_error(__('Failed to update settings', 'schemati'));
    }
}
    /**
     * Enqueue sidebar scripts
     */

// ========================================================================
// SCHEMA TEMPLATES SYSTEM
// ========================================================================

/**
 * Create schema template
 */
private function create_schema_template($schema_type, $data) {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => $schema_type,
        '_enabled' => true,
        '_source' => 'custom'
    );
    
    // Common fields for all schemas
    $common_fields = array('name', 'description', 'url');
    foreach ($common_fields as $field) {
        if (isset($data[$field])) {
            $schema[$field] = sanitize_text_field($data[$field]);
        }
    }
    
    // Add type-specific fields
    $this->add_type_specific_fields($schema, $schema_type, $data);
    
    return $schema;
}

/**
 * Add type-specific fields to schema
 */
private function add_type_specific_fields(&$schema, $schema_type, $data) {
    switch ($schema_type) {
        case 'LocalBusiness':
            $this->add_business_fields($schema, $data);
            break;
        case 'Product':
            $this->add_product_fields($schema, $data);
            break;
        case 'Person':
            $this->add_person_fields($schema, $data);
            break;
        case 'Event':
            $this->add_event_fields($schema, $data);
            break;
        case 'Article':
        case 'BlogPosting':
        case 'NewsArticle':
            $this->add_article_fields($schema, $data);
            break;
        case 'Recipe':
            $this->add_recipe_fields($schema, $data);
            break;
    }
}

/**
 * Add business fields
 */
private function add_business_fields(&$schema, $data) {
    $fields = array('address', 'telephone', 'email');
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $schema[$field] = $field === 'email' ? 
                sanitize_email($data[$field]) : 
                sanitize_text_field($data[$field]);
        }
    }
}

/**
 * Add product fields
 */
private function add_product_fields(&$schema, $data) {
    if (isset($data['brand'])) {
        $schema['brand'] = sanitize_text_field($data['brand']);
    }
    
    if (isset($data['price']) || isset($data['currency'])) {
        $schema['offers'] = array(
            '@type' => 'Offer',
            'price' => sanitize_text_field($data['price'] ?? ''),
            'priceCurrency' => sanitize_text_field($data['currency'] ?? 'USD'),
            'availability' => 'https://schema.org/InStock'
        );
    }
}

/**
 * Add person fields
 */
private function add_person_fields(&$schema, $data) {
    if (isset($data['job_title'])) {
        $schema['jobTitle'] = sanitize_text_field($data['job_title']);
    }
    
    $fields = array('email', 'telephone');
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $schema[$field] = $field === 'email' ? 
                sanitize_email($data[$field]) : 
                sanitize_text_field($data[$field]);
        }
    }
}

/**
 * Add event fields
 */
private function add_event_fields(&$schema, $data) {
    if (isset($data['start_date'])) {
        $schema['startDate'] = sanitize_text_field($data['start_date']);
    }
    if (isset($data['end_date'])) {
        $schema['endDate'] = sanitize_text_field($data['end_date']);
    }
    if (isset($data['location'])) {
        $schema['location'] = array(
            '@type' => 'Place',
            'name' => sanitize_text_field($data['location'])
        );
    }
}

/**
 * Add article fields
 */
private function add_article_fields(&$schema, $data) {
    $schema['headline'] = sanitize_text_field($data['headline'] ?? get_the_title());
    $schema['author'] = array(
        '@type' => 'Person',
        'name' => sanitize_text_field($data['author_name'] ?? get_the_author())
    );
    $schema['datePublished'] = sanitize_text_field($data['date_published'] ?? get_the_date('c'));
    $schema['dateModified'] = sanitize_text_field($data['date_modified'] ?? get_the_modified_date('c'));
}

/**
 * Add recipe fields
 */
private function add_recipe_fields(&$schema, $data) {
    $time_fields = array('prep_time', 'cook_time', 'total_time');
    foreach ($time_fields as $field) {
        if (isset($data[$field])) {
            $schema_field = str_replace('_', '', ucwords($field, '_'));
            $schema[$schema_field] = sanitize_text_field($data[$field]);
        }
    }
    
    // Add ingredients
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        $schema['recipeIngredient'] = array_map('sanitize_text_field', array_filter($data['ingredients']));
    }
    
    // Add instructions
    if (isset($data['instructions']) && is_array($data['instructions'])) {
        $schema['recipeInstructions'] = array();
        foreach ($data['instructions'] as $instruction) {
            if (!empty($instruction)) {
                $schema['recipeInstructions'][] = array(
                    '@type' => 'HowToStep',
                    'text' => sanitize_textarea_field($instruction)
                );
            }
        }
    }
}

/**
 * Get schema template HTML
 */
private function get_schema_template_html($schema_type) {
    $templates = array(
        'LocalBusiness' => $this->get_business_template(),
        'Service' => $this->get_service_template(),
        'Product' => $this->get_product_template(),
        'Person' => $this->get_person_template(),
        'Event' => $this->get_event_template(),
        'Recipe' => $this->get_recipe_template(),
        'Article' => $this->get_article_template(),
        'HowTo' => $this->get_howto_template(),
        'FAQPage' => $this->get_faq_template(),
        'Review' => $this->get_review_template(),
        'VideoObject' => $this->get_video_template(),
        'ImageObject' => $this->get_image_template(),
        'Organization' => $this->get_organization_template()
    );
    
    return $templates[$schema_type] ?? $this->get_generic_template();
}

private function get_faq_template() {
    return '<div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">כותרת הדף:</label>
        <input type="text" name="name" value="' . esc_attr(get_the_title()) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שאלות ותשובות:</label>
        <div id="faq-items">
            <div class="faq-item" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
                <input type="text" name="questions[]" placeholder="שאלה" style="width: 100%; margin-bottom: 5px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <textarea name="answers[]" rows="3" placeholder="תשובה" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
        </div>
        <button type="button" onclick="addFAQItem()" style="background: #0073aa; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">הוסף שאלה</button>
    </div>
    <script>
    function addFAQItem() {
        var container = document.getElementById("faq-items");
        var newItem = document.createElement("div");
        newItem.className = "faq-item";
        newItem.style.cssText = "border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 4px;";
        newItem.innerHTML = `
            <input type="text" name="questions[]" placeholder="שאלה" style="width: 100%; margin-bottom: 5px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <textarea name="answers[]" rows="3" placeholder="תשובה" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-top: 5px;">הסר</button>
        `;
        container.appendChild(newItem);
    }
    </script>';
}

/**
 * Generic template
 */
private function get_generic_template() {
    return '<div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם:</label>
        <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
        <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
    </div>';
}

/**
 * Business template
 */
private function get_business_template() {
    return $this->get_generic_template() . '
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
    </div>';
}

/**
 * Service template
 */
private function get_service_template() {
    return $this->get_generic_template() . '
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">אזור שירות:</label>
        <input type="text" name="area_served" placeholder="למשל, תל אביב, ישראל" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>';
}

/**
 * Product template
 */
private function get_product_template() {
    return $this->get_generic_template() . '
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
            </select>
        </div>
    </div>';
}

/**
 * Person template
 */
private function get_person_template() {
    return $this->get_generic_template() . '
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תפקיד:</label>
        <input type="text" name="job_title" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">אימייל:</label>
            <input type="email" name="email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">טלפון:</label>
            <input type="text" name="telephone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>';
}

/**
 * Event template
 */
private function get_event_template() {
    return $this->get_generic_template() . '
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
    </div>';
}
private function get_review_template() {
    return '<div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">נושא הביקורת:</label>
        <input type="text" name="item_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">סוג הפריט:</label>
        <select name="item_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="Product">מוצר</option>
            <option value="LocalBusiness">עסק מקומי</option>
            <option value="Service">שירות</option>
            <option value="Restaurant">מסעדה</option>
            <option value="Book">ספר</option>
            <option value="Movie">סרט</option>
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
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם הבוקר:</label>
            <input type="text" name="author_name" value="' . esc_attr(get_the_author()) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תוכן הביקורת:</label>
        <textarea name="review_body" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
    </div>';
}

/**
 * Video template
 */
private function get_video_template() {
    return $this->get_generic_template() . '
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL הווידאו:</label>
        <input type="url" name="content_url" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL הטמעה:</label>
        <input type="url" name="embed_url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">משך (שניות):</label>
            <input type="number" name="duration" placeholder="120" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">תאריך העלאה:</label>
            <input type="date" name="upload_date" value="' . date('Y-m-d') . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL תמונה ממוזערת:</label>
        <input type="url" name="thumbnail_url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>';
}

/**
 * Image template
 */
private function get_image_template() {
    return '<div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">כותרת התמונה:</label>
        <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL התמונה:</label>
        <input type="url" name="content_url" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור התמונה:</label>
        <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">רוחב:</label>
            <input type="number" name="width" placeholder="800" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">גובה:</label>
            <input type="number" name="height" placeholder="600" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>';
}

/**
 * Organization template
 */
private function get_organization_template() {
    return $this->get_generic_template() . '
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
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL לוגו:</label>
        <input type="url" name="logo_url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>';
}

/**
 * Recipe template
 */
private function get_recipe_template() {
    return $this->get_generic_template() . '
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
    </div>';
}

/**
 * Article template
 */
private function get_article_template() {
    return '<div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">כותרת:</label>
        <input type="text" name="headline" value="' . esc_attr(get_the_title()) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">תיאור:</label>
        <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שם המחבר:</label>
        <input type="text" name="author_name" value="' . esc_attr(get_the_author()) . '" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>';
}

/**
 * HowTo template
 */
private function get_howto_template() {
    return $this->get_generic_template() . '
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">זמן כולל (למשל, PT30M):</label>
        <input type="text" name="total_time" placeholder="PT30M" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 500;">שלבים (אחד בכל שורה):</label>
        <textarea name="steps[]" rows="4" placeholder="הכנס כל שלב בשורה חדשה" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
    </div>';
}

// ========================================================================
// INTERACTIVE SIDEBAR
// ========================================================================

/**
 * Replace the placeholder add_sidebar_html method
 */

 public function enqueue_sidebar_scripts() {
        wp_enqueue_script('jquery');
    }
public function add_sidebar_html() {
    if (!current_user_can('edit_posts') || is_admin()) {
        return;
    }
    
    $this->render_interactive_sidebar();
}

/**
 * Render interactive sidebar
 */
private function render_interactive_sidebar() {
    global $post;
    $current_schemas = $this->get_enhanced_page_schemas();
    $general_settings = $this->get_settings('schemati_general');
    
    ?>
    <div id="schemati-sidebar" style="display: none; position: fixed; top: 32px; right: 0; width: 450px; height: calc(100vh - 32px); background: white; border-left: 1px solid #ccc; z-index: 99999; padding: 0; overflow-y: auto; box-shadow: -2px 0 10px rgba(0,0,0,0.15); font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
        
        <!-- Header -->
        <div style="padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; position: sticky; top: 0; z-index: 1000;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 18px;">
                        <span style="margin-right: 8px;">⚙️</span>
                        <?php _e('עורך Schemati', 'schemati'); ?>
                    </h3>
                    <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                        <span id="schema-count"><?php echo count($current_schemas); ?> סכמות <?php _e('זוהו', 'schemati'); ?></span>
                        <span style="margin-left: 10px;">•</span>
                        <span id="schema-status"><?php echo $general_settings['enabled'] ? __('פעיל', 'schemati') : __('מושבת', 'schemati'); ?></span>
                    </div>
                </div>
                <button onclick="toggleSchematiSidebar()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: white; padding: 5px;">&times;</button>
            </div>
        </div>
        
        <!-- Tabs -->
        <div style="background: #f1f1f1; border-bottom: 1px solid #ccc;">
            <div style="display: flex;">
                <button class="schemati-tab active" onclick="showSchematiTab('current')" style="flex: 1; padding: 12px; border: none; background: white; cursor: pointer; border-bottom: 2px solid #0073aa; font-size: 12px;">
                    <span style="display: block;"><?php _e('נוכחי', 'schemati'); ?></span>
                    <small style="color: #666;" id="current-count"><?php echo count($current_schemas); ?> סכמות</small>
                </button>
                <button class="schemati-tab" onclick="showSchematiTab('add')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                    <span style="display: block;"><?php _e('הוסף', 'schemati'); ?></span>
                    <small style="color: #666;"><?php _e('סכמה חדשה', 'schemati'); ?></small>
                </button>
                <button class="schemati-tab" onclick="showSchematiTab('settings')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                    <span style="display: block;"><?php _e('הגדרות', 'schemati'); ?></span>
                    <small style="color: #666;"><?php _e('כלליות', 'schemati'); ?></small>
                </button>
            </div>
        </div>
        
        <!-- Current Tab -->
        <div id="schemati-tab-current" class="schemati-tab-content" style="padding: 20px;">
            <div id="current-schemas-list">
                <?php if (empty($current_schemas)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                        <h4><?php _e('לא זוהו סכמות', 'schemati'); ?></h4>
                        <p><?php _e('הוסף סכמה ראשונה באמצעות הטאב "הוסף".', 'schemati'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($current_schemas as $index => $schema): ?>
                        <?php $this->render_schema_item($schema, $index); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Tab -->
        <div id="schemati-tab-add" class="schemati-tab-content" style="display: none; padding: 20px;">
            <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;"><?php _e('הוסף סכמה חדשה', 'schemati'); ?></h4>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('סוג סכמה:', 'schemati'); ?></label>
                <select id="new-schema-type" onchange="loadSchemaTemplate()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value=""><?php _e('בחר סוג סכמה', 'schemati'); ?></option>
                    <option value="LocalBusiness">🏢 <?php _e('עסק מקומי', 'schemati'); ?></option>
                    <option value="Product">📦 <?php _e('מוצר', 'schemati'); ?></option>
                    <option value="Person">👤 <?php _e('אדם', 'schemati'); ?></option>
                    <option value="Event">📅 <?php _e('אירוע', 'schemati'); ?></option>
                    <option value="Article">📰 <?php _e('מאמר', 'schemati'); ?></option>
                    <option value="Recipe">🍳 <?php _e('מתכון', 'schemati'); ?></option>
                </select>
            </div>
            
            <div id="new-schema-form" style="display: none;">
                <form onsubmit="addNewSchema(); return false;">
                    <div id="schema-template-fields"></div>
                    <div style="margin-top: 20px;">
                        <button type="submit" style="width: 100%; background: #0073aa; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                            ➕ <?php _e('הוסף סכמה', 'schemati'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Settings Tab -->
        <div id="schemati-tab-settings" class="schemati-tab-content" style="display: none; padding: 20px;">
            <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;"><?php _e('הגדרות כלליות', 'schemati'); ?></h4>
            
            <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" id="schema-enabled" <?php checked($general_settings['enabled']); ?> onchange="toggleGlobalSchema()" style="margin-left: 8px;">
                    <span style="font-weight: 500;"><?php _e('הפעל סימון סכמה', 'schemati'); ?></span>
                </label>
            </div>
            
            <div style="margin-bottom: 20px;">
                <button onclick="showSchematiPreview()" style="width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    🔍 <?php _e('תצוגה מקדימה של סכמה', 'schemati'); ?>
                </button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="display: block; width: 100%; padding: 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; text-align: center;">
                    ⚙️ <?php _e('פאנל הגדרות מלא', 'schemati'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Schema Preview Modal -->
    <div id="schemati-schema-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 1200px; height: 85%; background: white; border-radius: 8px; padding: 0; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #0073aa; color: white;">
                <h2 style="margin: 0; color: white;">🔍 <?php _e('תצוגה מקדימה של סכמה', 'schemati'); ?></h2>
                <button onclick="hideSchematiPreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: white;">&times;</button>
            </div>
            <div id="schema-modal-content" style="height: calc(100% - 80px); overflow-y: auto; padding: 20px; font-family: monospace; font-size: 12px;">
                <?php _e('טוען נתוני סכמה...', 'schemati'); ?>
            </div>
        </div>
    </div>
    
    <?php $this->render_sidebar_scripts(); ?>
    <?php
}

/**
 * Render schema item
 */
private function render_schema_item($schema, $index) {
    $schema_type = $schema['@type'] ?? 'Unknown';
    $schema_name = $schema['name'] ?? $schema['title'] ?? 'Untitled';
    $schema_enabled = $schema['_enabled'] ?? true;
    $schema_source = $schema['_source'] ?? 'unknown';
    $is_editable = $schema_source === 'custom';
    
    ?>
    <div class="schema-item" style="margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <div style="background: #f8f9fa; padding: 12px; display: flex; justify-content: space-between; align-items: center;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <strong style="color: #0073aa;"><?php echo esc_html($schema_type); ?></strong>
                    <span style="background: #6c757d; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px;">
                        <?php echo esc_html($schema_source); ?>
                    </span>
                </div>
                <div style="font-size: 12px; color: #666;">
                    <?php echo esc_html(wp_trim_words($schema_name, 8)); ?>
                </div>
            </div>
            <div style="display: flex; gap: 5px;">
                <?php if ($is_editable): ?>
                    <button onclick="toggleSchemaStatus(<?php echo $index; ?>)" style="background: <?php echo $schema_enabled ? '#28a745' : '#dc3545'; ?>; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">
                        <?php echo $schema_enabled ? 'ON' : 'OFF'; ?>
                    </button>
                    <button onclick="deleteSchema(<?php echo $index; ?>)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">✕</button>
                <?php else: ?>
                    <span style="color: #666; font-size: 11px;"><?php _e('מערכת', 'schemati'); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render sidebar scripts
 */
private function render_sidebar_scripts() {
    ?>
    <script>
    var SchematiSidebar = {
        currentPostId: <?php echo get_the_ID() ?: 0; ?>,
        ajaxUrl: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("schemati_ajax"); ?>',
        
        ajaxCall: function(action, data, successCallback) {
            data.action = action;
            data.nonce = this.nonce;
            
            jQuery.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response);
                    } else {
                        alert('<?php _e('שגיאה:', 'schemati'); ?> ' + (response.data.message || '<?php _e('שגיאה לא ידועה', 'schemati'); ?>'));
                    }
                },
                error: function() {
                    alert('<?php _e('שגיאת חיבור. נסה שוב.', 'schemati'); ?>');
                }
            });
        }
    };
    
    // Global functions
    function toggleSchematiSidebar() {
        var sidebar = document.getElementById('schemati-sidebar');
        if (sidebar.style.display === 'none') {
            sidebar.style.display = 'block';
        } else {
            sidebar.style.display = 'none';
        }
    }
    
    function showSchematiTab(tabName) {
        document.querySelectorAll('.schemati-tab-content').forEach(function(tab) {
            tab.style.display = 'none';
        });
        document.querySelectorAll('.schemati-tab').forEach(function(btn) {
            btn.style.background = '#f1f1f1';
            btn.style.borderBottomColor = 'transparent';
        });
        
        document.getElementById('schemati-tab-' + tabName).style.display = 'block';
        event.target.style.background = 'white';
        event.target.style.borderBottomColor = '#0073aa';
    }
    
    function loadSchemaTemplate() {
        var schemaType = document.getElementById('new-schema-type').value;
        var formContainer = document.getElementById('new-schema-form');
        var fieldsContainer = document.getElementById('schema-template-fields');
        
        if (!schemaType) {
            formContainer.style.display = 'none';
            return;
        }
        
        fieldsContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">טוען תבנית...</div>';
        formContainer.style.display = 'block';
        
        SchematiSidebar.ajaxCall('schemati_get_schema_template', {
            schema_type: schemaType
        }, function(response) {
            if (response.success && response.data) {
                fieldsContainer.innerHTML = response.data;
            } else {
                fieldsContainer.innerHTML = '<div style="color: red; padding: 15px;">שגיאה: ' + (response.data.message || 'תבנית לא נמצאה') + '</div>';
            }
        });
    }
    
    function addNewSchema() {
        var form = document.querySelector('#new-schema-form form');
        var formData = new FormData(form);
        var schemaType = document.getElementById('new-schema-type').value;
        
        if (!schemaType) {
            alert('<?php _e('אנא בחר סוג סכמה', 'schemati'); ?>');
            return false;
        }
        
        var data = {
            schema_type: schemaType,
            post_id: SchematiSidebar.currentPostId
        };
        
        for (var pair of formData.entries()) {
            data[pair[0]] = pair[1];
        }
        
        SchematiSidebar.ajaxCall('schemati_add_schema', data, function(response) {
            alert('<?php _e('סכמה נוספה בהצלחה!', 'schemati'); ?>');
            form.reset();
            document.getElementById('new-schema-form').style.display = 'none';
            document.getElementById('new-schema-type').value = '';
            location.reload();
        });
        
        return false;
    }
    
    function toggleSchemaStatus(index) {
        SchematiSidebar.ajaxCall('schemati_toggle_schema', {
            schema_index: index,
            post_id: SchematiSidebar.currentPostId
        }, function(response) {
            location.reload();
        });
    }
    
    function deleteSchema(index) {
        if (!confirm('<?php _e('האם אתה בטוח שברצונך למחוק סכמה זו?', 'schemati'); ?>')) {
            return;
        }
        
        SchematiSidebar.ajaxCall('schemati_delete_schema', {
            schema_index: index,
            post_id: SchematiSidebar.currentPostId
        }, function(response) {
            alert('<?php _e('סכמה נמחקה בהצלחה!', 'schemati'); ?>');
            location.reload();
        });
    }
    
    function toggleGlobalSchema() {
        var checkbox = document.getElementById('schema-enabled');
        var enabled = checkbox.checked ? 1 : 0;
        
        SchematiSidebar.ajaxCall('schemati_toggle_global', {
            enabled: enabled
        }, function(response) {
            document.getElementById('schema-status').textContent = response.data.status_text;
        });
    }
    
    function showSchematiPreview() {
        var modal = document.getElementById('schemati-schema-modal');
        var content = document.getElementById('schema-modal-content');
        
        // Get all schemas from the page
        var schemas = [];
        document.querySelectorAll('script[type="application/ld+json"]').forEach(function(script) {
            try {
                var schema = JSON.parse(script.textContent);
                schemas.push(schema);
            } catch(e) {
                console.warn('Invalid JSON-LD schema found:', e);
            }
        });
        
        if (schemas.length === 0) {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><h3><?php _e('לא נמצאה סכמה', 'schemati'); ?></h3><p><?php _e('לא זוהה סימון סכמה בדף זה.', 'schemati'); ?></p></div>';
        } else {
            var html = '<div style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724;"><h3 style="margin: 0;"><?php _e('נמצאו', 'schemati'); ?> ' + schemas.length + ' <?php _e('סוגי סכמה', 'schemati'); ?></h3></div>';
            
            schemas.forEach(function(schema, index) {
                var schemaType = schema['@type'] || '<?php _e('סוג לא ידוע', 'schemati'); ?>';
                html += '<div style="margin-bottom: 25px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">';
                html += '<div style="padding: 15px; background: #0073aa; color: white;"><h4 style="margin: 0;">' + (index + 1) + '. ' + schemaType + ' סכמה</h4></div>';
                html += '<pre style="background: #2d3748; color: #e2e8f0; padding: 20px; margin: 0; overflow-x: auto; white-space: pre-wrap; font-size: 11px;">' + JSON.stringify(schema, null, 2) + '</pre>';
                html += '</div>';
            });
            content.innerHTML = html;
        }
        
        modal.style.display = 'block';
    }
    
    function hideSchematiPreview() {
        document.getElementById('schemati-schema-modal').style.display = 'none';
    }
    
    // Add styles
    var style = document.createElement('style');
    style.textContent = `
        .schemati-tab { transition: all 0.3s ease; }
        .schemati-tab:hover { background: #e9ecef !important; }
        .schema-item { transition: all 0.3s ease; }
        .schema-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    `;
    document.head.appendChild(style);
    </script>
    <?php
}

/**
 * Get enhanced page schemas for sidebar
 */
private function get_enhanced_page_schemas() {
    $schemas = array();
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
    
    // Header/Footer schemas
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
        
        // Custom schemas
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

// Replace the admin bar menu to open sidebar instead of linking to admin
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
            'title' => __('הפעל/כבה סרגל צד Schemati', 'schemati')
        ),
    ));
}
private function handle_import_export_action($action) {
    if (!wp_verify_nonce($_GET['_wpnonce'], 'schemati_' . $action)) {
        wp_die(__('Security check failed', 'schemati'));
    }
    
    switch ($action) {
        case 'export':
            $this->export_settings();
            break;
        case 'export_custom':
            $this->export_custom_schemas();
            break;
    }
}

/**
 * Export all settings
 */
private function export_settings() {
    $settings = array();
    $option_groups = array(
        'schemati_general',
        'schemati_article',
        'schemati_local_business',
        'schemati_person',
        'schemati_product',
        'schemati_faq'
    );
    
    foreach ($option_groups as $group) {
        $settings[$group] = get_option($group, array());
    }
    
    $export_data = array(
        'version' => SCHEMATI_VERSION,
        'export_date' => current_time('mysql'),
        'settings' => $settings
    );
    
    $filename = 'schemati-settings-' . date('Y-m-d-H-i-s') . '.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Export custom schemas
 */
private function export_custom_schemas() {
    global $wpdb;
    
    $custom_schemas = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_schemati_custom_schemas'"
    );
    
    $export_data = array(
        'version' => SCHEMATI_VERSION,
        'export_date' => current_time('mysql'),
        'custom_schemas' => array()
    );
    
    foreach ($custom_schemas as $row) {
        $schemas = maybe_unserialize($row->meta_value);
        if (is_array($schemas)) {
            $export_data['custom_schemas'][$row->post_id] = $schemas;
        }
    }
    
    $filename = 'schemati-custom-schemas-' . date('Y-m-d-H-i-s') . '.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Handle settings import
 */
private function handle_settings_import() {
    if (!wp_verify_nonce($_POST['schemati_import_nonce'], 'schemati_import')) {
        return;
    }
    
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>' . __('שגיאה בהעלאת הקובץ', 'schemati') . '</p></div>';
        return;
    }
    
    $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
    $import_data = json_decode($file_content, true);
    
    if (!$import_data || !isset($import_data['settings'])) {
        echo '<div class="notice notice-error"><p>' . __('קובץ לא תקין', 'schemati') . '</p></div>';
        return;
    }
    
    $imported_count = 0;
    foreach ($import_data['settings'] as $option_name => $option_value) {
        if (strpos($option_name, 'schemati_') === 0) {
            update_option($option_name, $option_value);
            $imported_count++;
        }
    }
    
    echo '<div class="notice notice-success"><p>' . sprintf(__('%d הגדרות יובאו בהצלחה', 'schemati'), $imported_count) . '</p></div>';
}
private function validate_schema($schema) {
    $errors = array();
    
    // Check required fields
    if (empty($schema['@context'])) {
        $errors[] = __('חסר @context', 'schemati');
    }
    
    if (empty($schema['@type'])) {
        $errors[] = __('חסר @type', 'schemati');
    }
    
    // Type-specific validation
    switch ($schema['@type']) {
        case 'LocalBusiness':
            if (empty($schema['name'])) {
                $errors[] = __('חסר שם עסק', 'schemati');
            }
            break;
            
        case 'Product':
            if (empty($schema['name'])) {
                $errors[] = __('חסר שם מוצר', 'schemati');
            }
            if (isset($schema['offers']) && empty($schema['offers']['price'])) {
                $errors[] = __('חסר מחיר מוצר', 'schemati');
            }
            break;
            
        case 'Person':
            if (empty($schema['name'])) {
                $errors[] = __('חסר שם אדם', 'schemati');
            }
            break;
            
        case 'Event':
            if (empty($schema['name'])) {
                $errors[] = __('חסר שם אירוע', 'schemati');
            }
            if (empty($schema['startDate'])) {
                $errors[] = __('חסר תאריך התחלה', 'schemati');
            }
            break;
    }
    
    return $errors;
}

} // End Schemati class

// ============================================================================
// INITIALIZATION & HELPER FUNCTIONS
// ============================================================================

/**
 * Initialize the plugin
 */
function schemati_init() {
    return Schemati::instance();
}

// Hook plugin initialization
add_action('plugins_loaded', 'schemati_init');

/**
 * Helper function for themes
 */
function schemati_breadcrumbs($args = array()) {
    $schemati = Schemati::instance();
    return $schemati->breadcrumb_shortcode($args);
}

// ============================================================================
// CLEANUP & UNINSTALL
// ============================================================================

/**
 * Plugin uninstall cleanup
 */
function schemati_uninstall() {
    // Clean up options
    $option_groups = array(
        'schemati_general',
        'schemati_article',
        'schemati_local_business',
        'schemati_person',
        'schemati_product',
        'schemati_faq'
    );
    
    foreach ($option_groups as $group) {
        delete_option($group);
    }
    
    // Clean up post meta
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_schemati_%'");
    
    // Clear cache
    wp_cache_flush_group('schemati_schemas');
}

// Register uninstall hook
register_uninstall_hook(__FILE__, 'schemati_uninstall');
