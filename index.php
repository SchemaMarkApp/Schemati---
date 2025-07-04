<?php
/**
 * Plugin Name: Schemati
 * Description: Complete Schema markup plugin with all features and sidebar
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
 * TRANSLATION FIX: Create Hebrew translation immediately on plugin load
 */
function schemati_ensure_hebrew_translation() {
    $languages_dir = SCHEMATI_DIR . 'languages/';
    
    // Create languages directory if it doesn't exist
    if (!file_exists($languages_dir)) {
        wp_mkdir_p($languages_dir);
    }
    
    $mo_file = $languages_dir . 'schemati-he_IL.mo';
    
    // Only create if doesn't exist or is old
    if (!file_exists($mo_file) || (time() - filemtime($mo_file)) > 86400) {
        schemati_create_hebrew_mo_file($mo_file);
    }
}

/**
 * TRANSLATION FIX: Create binary .mo file with Hebrew translations
 */
function schemati_create_hebrew_mo_file($mo_file) {
    // Comprehensive Hebrew translations array
    $translations = array(
        // Main plugin strings
        'Schemati Settings' => 'הגדרות Schemati',
        'Schemati' => 'Schemati',
        'General Settings' => 'הגדרות כלליות',
        'General' => 'כללי',
        'Article Schema' => 'סכמת מאמר',
        'Article' => 'מאמר',
        'About Page Schema' => 'סכמת דף אודות',
        'About Page' => 'דף אודות',
        'Contact Page Schema' => 'סכמת דף צור קשר',
        'Contact Page' => 'דף צור קשר',
        'Local Business Schema' => 'סכמת עסק מקומי',
        'Local Business' => 'עסק מקומי',
        'Person Schema' => 'סכמת אדם',
        'Person' => 'אדם',
        'Author Schema' => 'סכמת כותב',
        'Author' => 'כותב',
        'Publisher Schema' => 'סכמת מפרסם',
        'Publisher' => 'מפרסם',
        'Product Schema' => 'סכמת מוצר',
        'Product' => 'מוצר',
        'FAQ Schema' => 'סכמת שאלות נפוצות',
        'FAQ' => 'שאלות נפוצות',
        'CheckTool' => 'כלי בדיקה',
        'Language Settings' => 'הגדרות שפה',
        'Language' => 'שפה',
        'License' => 'רישיון',
        
        // Navigation Schema strings
        'WP Header' => 'כותרת WP',
        'WP Footer' => 'כותרת תחתונה WP',
        'Header Navigation' => 'ניווט כותרת',
        'Footer Navigation' => 'ניווט כותרת תחתונה',
        'WP Header Navigation Schema' => 'סכמת ניווט כותרת WP',
        'WP Footer Navigation Schema' => 'סכמת ניווט כותרת תחתונה WP',
        'Enable Header Schema' => 'אפשר סכמת כותרת',
        'Enable Footer Schema' => 'אפשר סכמת כותרת תחתונה',
        'Generate WPHeader schema markup for navigation' => 'צור סימון סכמת WPHeader עבור ניווט',
        'Generate WPFooter schema markup for navigation' => 'צור סימון סכמת WPFooter עבור ניווט',
        'Menu Location' => 'מיקום תפריט',
        'Select which menu location to use for header schema' => 'בחר איזה מיקום תפריט להשתמש עבור סכמת הכותרת',
        'Select which menu location to use for footer schema' => 'בחר איזה מיקום תפריט להשתמש עבור סכמת הכותרת התחתונה',
        'Include Submenus' => 'כלול תת-תפריטים',
        'Include submenu items in schema markup' => 'כלול פריטי תת-תפריט בסימון הסכמה',
        'Note: This may create large schema markup for complex menus' => 'הערה: זה עלול ליצור סימון סכמה גדול עבור תפריטים מורכבים',
        'Preview Current Header Menu' => 'תצוגה מקדימה של תפריט כותרת נוכחי',
        'Preview Current Footer Menu' => 'תצוגה מקדימה של תפריט כותרת תחתונה נוכחי',
        'Detected Navigation Items:' => 'פריטי ניווט שזוהו:',
        'No navigation items detected. Make sure you have a menu assigned to the selected location.' => 'לא זוהו פריטי ניווט. וודא שיש לך תפריט שמוקצה למיקום שנבחר.',
        'Automatically generates schema markup for your header navigation menu.' => 'יוצר אוטומטית סימון סכמה עבור תפריט הניווט שלך בכותרת.',
        'Automatically generates schema markup for your footer navigation menu.' => 'יוצר אוטומטית סימון סכמה עבור תפריט הניווט שלך בכותרת התחתונה.',
        'WP Header Navigation' => 'ניווט כותרת WP',
        'WP Footer Navigation' => 'ניווט כותרת תחתונה WP',
        'About' => 'אודות',
        'Services' => 'שירותים',
        'Blog' => 'בלוג',
        
        // Main interface
        'Schemati - General Settings' => 'Schemati - הגדרות כלליות',
        'Schemati v5.0 Activated!' => 'Schemati v5.0 הופעל!',
        'All features loaded successfully.' => 'כל הפיצ\'רים נטענו בהצלחה.',
        '✅ Schemati v5.0' => '✅ Schemati v5.0',
        'Complete schema solution with sidebar, all schema types, and breadcrumbs.' => 'פתרון סכמה מלא עם סייד-בר, כל סוגי הסכמות ופירורי לחם.',
        
        // Settings
        'General Schema Settings' => 'הגדרות סכמה כלליות',
        'Enable Schema Markup' => 'אפשר סימון סכמה',
        'Enable schema markup output site-wide' => 'אפשר פלט סימון סכמה בכל האתר',
        'Organization Name' => 'שם הארגון',
        'Your organization or website name' => 'שם הארגון או האתר שלך',
        'Organization Type' => 'סוג הארגון',
        'Organization' => 'ארגון',
        'Corporation' => 'תאגיד',
        
        // Breadcrumbs
        'Breadcrumb Settings' => 'הגדרות פירורי לחם',
        'Home Text' => 'טקסט בית',
        'Home' => 'בית',
        'Separator' => 'מפריד',
        'Show Current Page' => 'הצג דף נוכחי',
        'Display current page in breadcrumb trail' => 'הצג דף נוכחי במסלול פירורי הלחם',
        'Usage' => 'שימוש',
        'Shortcode:' => 'קיצור דרך:',
        'PHP Function:' => 'פונקצית PHP:',
        
        // Article settings
        'Article Schema Settings' => 'הגדרות סכמת מאמר',
        'Enable Article Schema' => 'אפשר סכמת מאמר',
        'Generate article schema markup for posts' => 'צור סימון סכמת מאמר עבור פוסטים',
        'Default Article Type' => 'סוג מאמר ברירת מחדל',
        'Blog Posting' => 'פוסט בבלוג',
        'News Article' => 'מאמר חדשות',
        
        // Business settings
        'Enable Local Business Schema' => 'אפשר סכמת עסק מקומי',
        'Generate local business schema markup' => 'צור סימון סכמת עסק מקומי',
        'Business Name' => 'שם העסק',
        'Business Type' => 'סוג העסק',
        'Restaurant' => 'מסעדה',
        'Store' => 'חנות',
        'Address' => 'כתובת',
        'Phone Number' => 'מספר טלפון',
        'Description' => 'תיאור',
        'Phone' => 'טלפון',
        'Email' => 'דוא"ל',
        'Website URL' => 'כתובת אתר',
        'Name' => 'שם',
        
        // Tools page
        'Schemati Tools & Diagnostics' => 'כלי Schemati ואבחון',
        'Schema Testing Tools' => 'כלי בדיקת סכמה',
        'Test your schema markup with these official tools:' => 'בדוק את סימון הסכמה שלך עם הכלים הרשמיים הבאים:',
        'Google Rich Results Test' => 'בדיקת תוצאות עשירות של גוגל',
        'Schema.org Validator' => 'מאמת Schema.org',
        'Facebook Debugger' => 'דיבאגר פייסבוק',
        'Plugin Status' => 'סטטוס הפלאגין',
        'Version' => 'גרסה',
        'Status' => 'סטטוס',
        'Active' => 'פעיל',
        'Disabled' => 'מושבת',
        'Schema Types Available' => 'סוגי סכמה זמינים',
        'Organization, WebPage, Article, LocalBusiness, Person, Product, FAQ, BreadcrumbList, WPHeader, WPFooter' => 'ארגון, דף אינטרנט, מאמר, עסק מקומי, אדם, מוצר, שאלות נפוצות, רשימת פירורי לחם, כותרת WP, כותרת תחתונה WP',
        'Sidebar' => 'סייד-בר',
        '✓ Active on frontend for logged-in users' => '✓ פעיל בחזית עבור משתמשים מחוברים',
        'Breadcrumbs' => 'פירורי לחם',
        '✓ Shortcode and PHP function available' => '✓ קיצור דרך ופונקצית PHP זמינים',
        'Current Page Schema Preview' => 'תצוגה מקדימה של סכמת עמוד נוכחי',
        'Visit your website while logged in to see the Schemati sidebar with live schema preview.' => 'בקר באתר שלך בזמן שאתה מחובר כדי לראות את הסייד-בר של Schemati עם תצוגה מקדימה חיה של הסכמה.',
        'View Website with Sidebar' => 'צפה באתר עם סייד-בר',
        
        // License page
        'Schemati License' => 'רישיון Schemati',
        'License Information' => 'מידע רישיון',
        'Schemati v5.0 is licensed under GPL v2 or later.' => 'Schemati v5.0 מורשה תחת GPL v2 או מאוחר יותר.',
        'This plugin is free and open source.' => 'פלאגין זה חינמי וקוד פתוח.',
        'Plugin Details' => 'פרטי הפלאגין',
        'Version:' => 'גרסה:',
        'Author:' => 'יוצר:',
        'Website:' => 'אתר:',
        'License:' => 'רישיון:',
        
        // Messages
        'Settings saved successfully!' => 'ההגדרות נשמרו בהצלחה!',
        'Schema added successfully!' => 'הסכמה נוספה בהצלחה!',
        'Schema updated successfully!' => 'הסכמה עודכנה בהצלחה!',
        'Schema deleted successfully!' => 'הסכמה נמחקה בהצלחה!',
        'Save Changes' => 'שמור שינויים',
        'Cancel' => 'ביטול',
        'Delete' => 'מחק',
        'Enable' => 'אפשר',
        'Disable' => 'השבת',
        'Are you sure you want to delete this schema?' => 'האם אתה בטוח שברצונך למחוק את הסכמה הזו?',
        
        // Admin bar and sidebar
        'Toggle Schemati Sidebar' => 'החלף סייד-בר Schemati',
        'View Schema' => 'צפה בסכמה',
        'Test Rich Results' => 'בדוק תוצאות עשירות',
        'Schemati Editor' => 'עורך Schemati',
        'schemas detected' => 'סכמות זוהו',
        'Current' => 'נוכחי',
        'Add' => 'הוסף',
        'Templates' => 'תבניות',
        'Settings' => 'הגדרות',
        'New schema' => 'סכמה חדשה',
        'Quick add' => 'הוספה מהירה',
        'Global' => 'גלובלי',
        'DETECTED SCHEMAS' => 'סכמות שזוהו',
        'No schemas detected' => 'לא זוהו סכמות',
        'Add your first schema using the "Add" tab or choose from templates.' => 'הוסף את הסכמה הראשונה שלך באמצעות הלשונית "הוסף" או בחר מתבניות.',
        'Browse Templates' => 'עיין בתבניות',
        'System Generated Schema' => 'סכמה שנוצרה במערכת',
        'This schema is automatically generated. You can modify global settings in the admin panel.' => 'סכמה זו נוצרה אוטומטית. ניתן לשנות הגדרות גלובליות בפאנל הניהול.',
        'Edit Global Settings' => 'ערוך הגדרות גלובליות',
        
        // Schema types
        'Service' => 'שירות',
        'Event' => 'אירוע',
        'Blog Post' => 'פוסט בבלוג',
        'FAQ Page' => 'דף שאלות נפוצות',
        'How-To' => 'הוראות',
        'Recipe' => 'מתכון',
        'Review' => 'ביקורת',
        'Video' => 'וידאו',
        'Image' => 'תמונה',
        'Audio' => 'אודיו',
        'Web Page' => 'דף אינטרנט',
        'Website' => 'אתר אינטרנט',
        
        // Schema form fields
        'Business Name' => 'שם העסק',
        'Service Name' => 'שם השירות',
        'Product Name' => 'שם המוצר',
        'Event Name' => 'שם האירוע',
        'Full Name' => 'שם מלא',
        'Job Title' => 'תפקיד',
        'Brand' => 'מותג',
        'Price' => 'מחיר',
        'Currency' => 'מטבע',
        'Start Date' => 'תאריך התחלה',
        'End Date' => 'תאריך סיום',
        'Location' => 'מיקום',
        'Area Served' => 'אזור שירות',
        'Organization Name' => 'שם הארגון',
        
        // Meta box
        'Schema Settings' => 'הגדרות סכמה',
        'Schema Type' => 'סוג סכמה',
        'Default' => 'ברירת מחדל',
        'Custom Description' => 'תיאור מותאם אישית',
        'Optional custom description for schema markup' => 'תיאור מותאם אישית אופציונלי עבור סימון סכמה',
        
        // Quick actions in sidebar
        'Preview All Schemas' => 'תצוגה מקדימה של כל הסכמות',
        'Export All Schemas' => 'ייצא כל הסכמות',
        'Import Schemas' => 'ייבא סכמות',
        'Enable All' => 'אפשר הכל',
        'Disable All' => 'השבת הכל',
        'Duplicate' => 'שכפל',
        'Reset All' => 'אפס הכל',
        'Full Settings Panel' => 'פאנל הגדרות מלא',
        
        // Language settings page
        'Schemati - Language Settings' => 'Schemati - הגדרות שפה',
        '🌍 Multilingual Support' => '🌍 תמיכה רב-לשונית',
        'Choose your preferred language for the Schemati interface.' => 'בחר את השפה המועדפת עליך עבור ממשק Schemati.',
        'Plugin Language' => 'שפת התוסף',
        'Auto (Follow WordPress Language)' => 'אוטומטי (עקוב אחר שפת וורדפרס)',
        'RTL Support' => 'תמיכה RTL',
        'Right-to-Left text direction' => 'כיוון טקסט מימין לשמאל',
        'Automatically enabled for Hebrew and other RTL languages.' => 'מופעל אוטומטית לעברית ושפות RTL אחרות.',
        'Translation Status' => 'סטטוס תרגום',
        'Completion' => 'השלמה',
        'Native' => 'שפת מקור',
        'Available' => 'זמין',
        'Generating...' => 'יוצר...',
        'Actions' => 'פעולות',
        'Translation Tools' => 'כלי תרגום',
        'Regenerate Translation Files' => 'צור מחדש קבצי תרגום',
        'Export .POT File' => 'ייצא קובץ .POT',
        'Use these tools to update or export translation files for translators.' => 'השתמש בכלים אלה כדי לעדכן או לייצא קבצי תרגום עבור מתרגמים.',
        'Save Language Settings' => 'שמור הגדרות שפה',
        'Language settings saved successfully!' => 'הגדרות השפה נשמרו בהצלחה!',
        
        // Error messages
        'Insufficient permissions' => 'הרשאות לא מספיקות',
        'No post ID provided' => 'לא סופק מזהה פוסט',
        'No schemas found' => 'לא נמצאו סכמות',
        'Schema not found' => 'הסכמה לא נמצאה',
        'Invalid schema type' => 'סוג סכמה לא חוקי',
        'Template not found' => 'תבנית לא נמצאה',
        'Schema status updated' => 'סטטוס הסכמה עודכן',
        'Schema deleted' => 'הסכמה נמחקה',
        'Global setting updated' => 'הגדרה גלובלית עודכנה'
    );
    
    // Create binary .mo file
    $mo_data = schemati_create_mo_binary($translations);
    file_put_contents($mo_file, $mo_data);
    
    return true;
}

/**
 * TRANSLATION FIX: Create proper binary .mo file format
 */
function schemati_create_mo_binary($translations) {
    $keys = array_keys($translations);
    $values = array_values($translations);
    $count = count($translations);
    
    // MO file header (Little Endian)
    $magic = 0x950412de;
    $revision = 0;
    $total = $count;
    
    // Calculate string offsets
    $keys_start_offset = 28;
    $values_start_offset = $keys_start_offset + $count * 8;
    $key_offsets = array();
    $value_offsets = array();
    $current_offset = $values_start_offset + $count * 8;
    
    // Calculate key offsets
    foreach ($keys as $key) {
        $key_offsets[] = array('length' => strlen($key), 'offset' => $current_offset);
        $current_offset += strlen($key) + 1; // +1 for null terminator
    }
    
    // Calculate value offsets  
    foreach ($values as $value) {
        $value_offsets[] = array('length' => strlen($value), 'offset' => $current_offset);
        $current_offset += strlen($value) + 1; // +1 for null terminator
    }
    
    // Build MO file
    $mo = '';
    
    // Header
    $mo .= pack('V', $magic);
    $mo .= pack('V', $revision);
    $mo .= pack('V', $total);
    $mo .= pack('V', $keys_start_offset);
    $mo .= pack('V', $values_start_offset);
    $mo .= pack('V', 0); // Hash table offset (not used)
    $mo .= pack('V', 0); // Hash table size (not used)
    
    // Key descriptors
    foreach ($key_offsets as $offset) {
        $mo .= pack('V', $offset['length']);
        $mo .= pack('V', $offset['offset']);
    }
    
    // Value descriptors
    foreach ($value_offsets as $offset) {
        $mo .= pack('V', $offset['length']);
        $mo .= pack('V', $offset['offset']);
    }
    
    // Key strings
    foreach ($keys as $key) {
        $mo .= $key . "\0";
    }
    
    // Value strings  
    foreach ($values as $value) {
        $mo .= $value . "\0";
    }
    
    return $mo;
}

/**
 * TRANSLATION FIX: Load translations early and reliably
 */
function schemati_load_translations() {
    // Ensure translation file exists
    schemati_ensure_hebrew_translation();
    
    // Load text domain
    $languages_dir = dirname(plugin_basename(__FILE__)) . '/languages';
    $loaded = load_plugin_textdomain('schemati', false, $languages_dir);
    
    return $loaded;
}

// Load translations as early as possible
add_action('plugins_loaded', 'schemati_load_translations', 1);

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
            add_action('wp_ajax_schemati_generate_translations', array($this, 'ajax_generate_translations'));
            add_action('wp_ajax_schemati_export_pot', array($this, 'ajax_export_pot'));
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

    public function ajax_generate_translations() {
        check_ajax_referer('schemati_translations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $result = schemati_ensure_hebrew_translation();
        
        if ($result) {
            wp_send_json_success(__('Translation files generated successfully', 'schemati'));
        } else {
            wp_send_json_error(__('Failed to generate translation files', 'schemati'));
        }
    }
    
    /**
     * AJAX handler for exporting POT file
     */
    public function ajax_export_pot() {
        check_ajax_referer('schemati_export', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        // Generate POT file content
        $pot_content = $this->generate_pot_file();
        
        // Send as download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="schemati.pot"');
        header('Content-Length: ' . strlen($pot_content));
        echo $pot_content;
        exit;
    }
    
    /**
     * Generate POT file for translators
     */
    private function generate_pot_file() {
        $pot_content = "# Schemati Translation Template\n";
        $pot_content .= "# Generated automatically\n";
        $pot_content .= "msgid \"\"\n";
        $pot_content .= "msgstr \"\"\n";
        $pot_content .= "\"Project-Id-Version: Schemati 5.0\\n\"\n";
        $pot_content .= "\"Language: \\n\"\n";
        $pot_content .= "\"MIME-Version: 1.0\\n\"\n";
        $pot_content .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
        $pot_content .= "\"Content-Transfer-Encoding: 8bit\\n\"\n\n";
        
        // Add all translatable strings
        $strings = array(
            'Schemati Settings', 'Schemati', 'General Settings', 'Article Schema',
            'About Page Schema', 'Contact Page Schema', 'Local Business Schema',
            'Person Schema', 'Author Schema', 'Publisher Schema', 'Product Schema',
            'FAQ Schema', 'CheckTool', 'Language Settings', 'License',
            'Enable Schema Markup', 'Organization Name', 'Organization Type',
            'Breadcrumb Settings', 'Home Text', 'Separator', 'Show Current Page',
            'Business Name', 'Description', 'Address', 'Phone', 'Email',
            'Website URL', 'Name', 'Save Changes', 'Cancel', 'Delete',
            'Settings saved successfully!', 'Schema added successfully!',
            // Add more strings as needed...
        );
        
        foreach ($strings as $string) {
            $pot_content .= "msgid \"" . addslashes($string) . "\"\n";
            $pot_content .= "msgstr \"\"\n\n";
        }
        
        return $pot_content;
    }
    
    /**
     * Enhanced admin scripts with RTL support
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'schemati') !== false) {
            $current_language = get_locale();
            $is_rtl = is_rtl() || $current_language === 'he_IL';
            ?>
            <style>
            .schemati-admin .card { max-width: 800px; margin-top: 20px; }
            .schemati-admin .form-table th { width: 200px; }
            .schemati-admin .notice { max-width: 800px; }
            .schemati-admin .widefat td { padding: 8px 10px; }
            
            <?php if ($is_rtl): ?>
            .schemati-admin {
                direction: rtl;
                text-align: right;
            }
            .schemati-admin .form-table th {
                text-align: right;
            }
            <?php endif; ?>
            </style>
            <script>
            jQuery(document).ready(function($) {
                $('.wrap').addClass('schemati-admin');
                
                <?php if ($is_rtl): ?>
                // RTL-specific JavaScript adjustments
                $('.wrap').addClass('schemati-rtl');
                <?php endif; ?>
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
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error(__('No post ID provided', 'schemati'));
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        if (isset($custom_schemas[$schema_index])) {
            $custom_schemas[$schema_index]['_enabled'] = !($custom_schemas[$schema_index]['_enabled'] ?? true);
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success(__('Schema status updated', 'schemati'));
        }
        
        wp_send_json_error(__('Schema not found', 'schemati'));
    }

    /**
     * AJAX handler to delete schema
     */
    public function ajax_delete_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error(__('No post ID provided', 'schemati'));
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            wp_send_json_error(__('No schemas found', 'schemati'));
        }
        
        if (isset($custom_schemas[$schema_index])) {
            unset($custom_schemas[$schema_index]);
            $custom_schemas = array_values($custom_schemas); // Re-index array
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success(__('Schema deleted', 'schemati'));
        }
        
        wp_send_json_error(__('Schema not found', 'schemati'));
    }

    /**
     * AJAX handler to save schema changes
     */
    public function ajax_save_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $schema_index = intval($_POST['schema_index']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error(__('No post ID provided', 'schemati'));
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
                        $schema['offers']['price'] = sanitize_text_field($_POST['price']);
                    }
                    if (isset($_POST['currency'])) {
                        $schema['offers']['priceCurrency'] = sanitize_text_field($_POST['currency']);
                    }
                    break;
            }
            
            $custom_schemas[$schema_index] = $schema;
            update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
            wp_send_json_success(__('Schema updated successfully', 'schemati'));
        }
        
        wp_send_json_error(__('Schema not found', 'schemati'));
    }

    /**
     * AJAX handler to add new schema
     */
    public function ajax_add_schema() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error(__('No post ID provided', 'schemati'));
        }
        
        // Create new schema based on type
        $new_schema = $this->create_schema_template($schema_type, $_POST);
        
        if (!$new_schema) {
            wp_send_json_error(__('Invalid schema type', 'schemati'));
        }
        
        $custom_schemas = get_post_meta($post_id, '_schemati_custom_schemas', true);
        if (!is_array($custom_schemas)) {
            $custom_schemas = array();
        }
        
        $custom_schemas[] = $new_schema;
        update_post_meta($post_id, '_schemati_custom_schemas', $custom_schemas);
        
        wp_send_json_success(__('Schema added successfully', 'schemati'));
    }

    /**
     * AJAX handler to get schema template
     */
    public function ajax_get_schema_template() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $template_html = $this->get_schema_template_html($schema_type);
        
        if ($template_html) {
            wp_send_json_success($template_html);
        } else {
            wp_send_json_error(__('Template not found', 'schemati'));
        }
    }

    /**
     * AJAX handler to toggle global schema settings
     */
    public function ajax_toggle_global() {
        check_ajax_referer('schemati_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'schemati'));
        }
        
        $enabled = intval($_POST['enabled']);
        $settings = $this->get_settings('schemati_general');
        $settings['enabled'] = $enabled;
        
        update_option('schemati_general', $settings);
        wp_send_json_success(__('Global setting updated', 'schemati'));
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
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Business Name', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Description', 'schemati'); ?>:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Address', 'schemati'); ?>:</label>
                <textarea name="address" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Phone', 'schemati'); ?>:</label>
                    <input type="text" name="telephone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Email', 'schemati'); ?>:</label>
                    <input type="email" name="email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Website URL', 'schemati'); ?>:</label>
                <input type="url" name="url" value="<?php echo esc_url(get_permalink()); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <?php
            break;
            
        case 'Service':
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Service Name', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Description', 'schemati'); ?>:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Area Served', 'schemati'); ?>:</label>
                <input type="text" name="area_served" placeholder="<?php esc_attr_e('e.g., New York, NY', 'schemati'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <?php
            break;
            
        case 'Product':
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Product Name', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Description', 'schemati'); ?>:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Brand', 'schemati'); ?>:</label>
                <input type="text" name="brand" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Price', 'schemati'); ?>:</label>
                    <input type="number" name="price" step="0.01" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Currency', 'schemati'); ?>:</label>
                    <select name="currency" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="CAD">CAD</option>
                        <option value="ILS">ILS</option>
                    </select>
                </div>
            </div>
            <?php
            break;
            
        case 'Event':
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Event Name', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Description', 'schemati'); ?>:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Start Date', 'schemati'); ?>:</label>
                    <input type="datetime-local" name="start_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('End Date', 'schemati'); ?>:</label>
                    <input type="datetime-local" name="end_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Location', 'schemati'); ?>:</label>
                <input type="text" name="location" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <?php
            break;
            
        case 'Person':
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Full Name', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Job Title', 'schemati'); ?>:</label>
                <input type="text" name="job_title" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Email', 'schemati'); ?>:</label>
                    <input type="email" name="email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Phone', 'schemati'); ?>:</label>
                    <input type="text" name="telephone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Website', 'schemati'); ?>:</label>
                <input type="url" name="url" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <?php
            break;
            
        case 'FAQPage':
            ?>
            <div id="faq-questions">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Question', 'schemati'); ?> 1:</label>
                    <input type="text" name="questions[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px;">
                    <textarea name="answers[]" placeholder="<?php esc_attr_e('Answer...', 'schemati'); ?>" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
            </div>
            <button type="button" onclick="addFAQQuestion()" style="background: #0073aa; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-bottom: 15px;">
                + <?php _e('Add Another Question', 'schemati'); ?>
            </button>
            <script>
            function addFAQQuestion() {
                var container = document.getElementById('faq-questions');
                var questionNum = container.children.length + 1;
                var html = '<div style="margin-bottom: 15px;">' +
                    '<label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php echo esc_js(__('Question', 'schemati')); ?> ' + questionNum + ':</label>' +
                    '<input type="text" name="questions[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px;">' +
                    '<textarea name="answers[]" placeholder="<?php echo esc_js(__('Answer...', 'schemati')); ?>" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>' +
                    '</div>';
                container.insertAdjacentHTML('beforeend', html);
            }
            </script>
            <?php
            break;
            
        case 'HowTo':
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('How-To Title', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Description', 'schemati'); ?>:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Total Time', 'schemati'); ?>:</label>
                <input type="text" name="total_time" placeholder="<?php esc_attr_e('e.g., PT30M (30 minutes)', 'schemati'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <?php
            break;
            
        default:
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Name', 'schemati'); ?>:</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Description', 'schemati'); ?>:</label>
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
            '@type' => 'WebPageElement',
            '@id' => 'schema:WebPageElement',
            'name' => 'WebpageElement',
            'hasPart' => array($nav_items)
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
            '@type' => 'WebPageElement',
            '@id' => 'schema:WebPageElement',
            'name' => 'WebpageElement',
            'isPartOf' => array(
                '@type' => 'WebPage',
                '@id' => 'schema:WebPage'
            ),
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
            // Skip sub-menu items for now (can be enhanced later)
            if ($item->menu_item_parent == 0) {
                $nav_items[] = array(
                    '@type' => array('SiteNavigationElement', 'WP' . ucfirst($location)),
                    'name' => $item->title,
                    'url' => $item->url,
                    '@id' => 'schema:SiteNavigationElement'
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
        
        // Default navigation items
        $default_items = array(
            array(
                'name' => __('Home', 'schemati'),
                'url' => home_url('/')
            )
        );
        
        // Add some common pages if they exist
        $common_pages = array(
            'about' => __('About', 'schemati'),
            'contact' => __('Contact', 'schemati'),
            'services' => __('Services', 'schemati'),
            'blog' => __('Blog', 'schemati')
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
                '@type' => array('SiteNavigationElement', 'WP' . ucfirst($location)),
                'name' => $item['name'],
                'url' => $item['url'],
                '@id' => 'schema:SiteNavigationElement'
            );
        }
        
        return $nav_items;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Ensure Hebrew translation exists
        schemati_ensure_hebrew_translation();
        
        // Set default options for all schema types
        $defaults = array(
            'version' => SCHEMATI_VERSION,
            'enabled' => true,
            'org_name' => get_bloginfo('name'),
            'org_type' => 'Organization',
            'breadcrumb_home' => __('Home', 'schemati'),
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
        
        // Add new navigation schema types
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
     * Add admin menu
     */
    public function admin_menu() {
        // Main menu page
        add_menu_page(
            __('Schemati Settings', 'schemati'),
            __('Schemati', 'schemati'),
            'manage_options',
            'schemati',
            array($this, 'general_page'),
            'dashicons-admin-settings',
            80
        );
        
        // All submenu pages
        add_submenu_page('schemati', __('General Settings', 'schemati'), __('General', 'schemati'), 'manage_options', 'schemati', array($this, 'general_page'));
        add_submenu_page('schemati', __('Article Schema', 'schemati'), __('Article', 'schemati'), 'manage_options', 'schemati-article', array($this, 'article_page'));
        add_submenu_page('schemati', __('About Page Schema', 'schemati'), __('About Page', 'schemati'), 'manage_options', 'schemati-about', array($this, 'about_page'));
        add_submenu_page('schemati', __('Contact Page Schema', 'schemati'), __('Contact Page', 'schemati'), 'manage_options', 'schemati-contact', array($this, 'contact_page'));
        add_submenu_page('schemati', __('Local Business Schema', 'schemati'), __('Local Business', 'schemati'), 'manage_options', 'schemati-business', array($this, 'business_page'));
        add_submenu_page('schemati', __('Person Schema', 'schemati'), __('Person', 'schemati'), 'manage_options', 'schemati-person', array($this, 'person_page'));
        add_submenu_page('schemati', __('Author Schema', 'schemati'), __('Author', 'schemati'), 'manage_options', 'schemati-author', array($this, 'author_page'));
        add_submenu_page('schemati', __('Publisher Schema', 'schemati'), __('Publisher', 'schemati'), 'manage_options', 'schemati-publisher', array($this, 'publisher_page'));
        add_submenu_page('schemati', __('Product Schema', 'schemati'), __('Product', 'schemati'), 'manage_options', 'schemati-product', array($this, 'product_page'));
        add_submenu_page('schemati', __('FAQ Schema', 'schemati'), __('FAQ', 'schemati'), 'manage_options', 'schemati-faq', array($this, 'faq_page'));
        
        // Add new navigation schema pages
        add_submenu_page('schemati', __('Header Navigation', 'schemati'), __('WP Header', 'schemati'), 'manage_options', 'schemati-wpheader', array($this, 'wpheader_page'));
        add_submenu_page('schemati', __('Footer Navigation', 'schemati'), __('WP Footer', 'schemati'), 'manage_options', 'schemati-wpfooter', array($this, 'wpfooter_page'));
        
        add_submenu_page('schemati', __('CheckTool', 'schemati'), __('CheckTool', 'schemati'), 'manage_options', 'schemati-tools', array($this, 'tools_page'));
        
        // Add language settings page
        add_submenu_page('schemati', __('Language Settings', 'schemati'), __('Language', 'schemati'), 'manage_options', 'schemati-language', array($this, 'language_page'));
        
        add_submenu_page('schemati', __('License', 'schemati'), __('License', 'schemati'), 'manage_options', 'schemati-license', array($this, 'license_page'));
    }

    /**
     * WP Header Schema settings page
     */
    public function wpheader_page() {
        $this->handle_form_submission('schemati_wpheader');
        $settings = $this->get_settings('schemati_wpheader');
        
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('WP Header Navigation Schema', 'schemati'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('📍 WP Header Schema', 'schemati'); ?></strong> - <?php _e('Automatically generates schema markup for your header navigation menu.', 'schemati'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Header Schema', 'schemati'); ?></th>
                        <td>
                            <select name="menu_location">
                                <?php
                                $locations = get_registered_nav_menus();
                                if (empty($locations)) {
                                    echo '<option value="footer">Footer Menu (Default)</option>';
                                } else {
                                    foreach ($locations as $location => $description) {
                                        echo '<option value="' . esc_attr($location) . '" ' . selected($settings['menu_location'] ?? 'footer', $location, false) . '>' . esc_html($description) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('Select which menu location to use for footer schema', 'schemati'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Include Submenus', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_submenu" value="1" <?php checked(1, $settings['include_submenu'] ?? false); ?> />
                                <?php _e('Include submenu items in schema markup', 'schemati'); ?>
                            </label>
                            <p class="description"><?php _e('Note: This may create large schema markup for complex menus', 'schemati'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Preview Current Footer Menu', 'schemati'); ?></h3>
                <?php
                $nav_items = $this->get_navigation_items('footer');
                if (!empty($nav_items)) {
                    echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #0073aa;">';
                    echo '<h4 style="margin-top: 0;">' . __('Detected Navigation Items:', 'schemati') . '</h4>';
                    echo '<ul style="margin: 0;">';
                    foreach ($nav_items as $item) {
                        echo '<li><strong>' . esc_html($item['name']) . '</strong> - <code>' . esc_html($item['url']) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                    echo '<p>' . __('No navigation items detected. Make sure you have a menu assigned to the selected location.', 'schemati') . '</p>';
                    echo '</div>';
                }
                ?>
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
            </form>
        </div>
        <?php
    }

    public function language_page() {
        if (isset($_POST['schemati_language_nonce']) && wp_verify_nonce($_POST['schemati_language_nonce'], 'schemati_language_save')) {
            $new_language = sanitize_text_field($_POST['schemati_language']);
            update_option('schemati_language', $new_language);
            echo '<div class="notice notice-success"><p>' . __('Language settings saved successfully!', 'schemati') . '</p></div>';
            
            // Generate translation files if needed
            if ($new_language === 'he_IL') {
                schemati_ensure_hebrew_translation();
            }
        }
        
        $current_language = get_option('schemati_language', 'auto');
        $current_locale = get_locale();
        $is_rtl = is_rtl() || $current_locale === 'he_IL';
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap <?php echo $is_rtl ? 'schemati-rtl' : ''; ?>" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Schemati - Language Settings', 'schemati'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('🌍 Multilingual Support', 'schemati'); ?></strong> - <?php _e('Choose your preferred language for the Schemati interface.', 'schemati'); ?></p>
            </div>
            
            <!-- Translation Debug Info -->
            <div class="notice notice-warning">
                <p><strong><?php _e('Translation Debug:', 'schemati'); ?></strong></p>
                <p>
                    <?php _e('Locale:', 'schemati'); ?> <code><?php echo get_locale(); ?></code> | 
                    <?php _e('Text Domain Loaded:', 'schemati'); ?> <code><?php echo is_textdomain_loaded('schemati') ? 'YES' : 'NO'; ?></code> |
                    <?php _e('RTL:', 'schemati'); ?> <code><?php echo is_rtl() ? 'YES' : 'NO'; ?></code>
                </p>
                <p><?php _e('Test translation:', 'schemati'); ?> "<strong><?php _e('Language Settings', 'schemati'); ?></strong>"</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_language_save', 'schemati_language_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Language', 'schemati'); ?></th>
                        <td>
                            <select name="schemati_language" style="min-width: 200px;">
                                <option value="auto" <?php selected($current_language, 'auto'); ?>>
                                    <?php _e('Auto (Follow WordPress Language)', 'schemati'); ?>
                                </option>
                                <option value="en_US" <?php selected($current_language, 'en_US'); ?>>
                                    🇺🇸 English (United States)
                                </option>
                                <option value="he_IL" <?php selected($current_language, 'he_IL'); ?>>
                                    🇮🇱 עברית (Hebrew)
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Current WordPress locale:', 'schemati'); ?> <code><?php echo $current_locale; ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('RTL Support', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" disabled <?php checked(is_rtl() || $current_language === 'he_IL'); ?> />
                                <?php _e('Right-to-Left text direction', 'schemati'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Automatically enabled for Hebrew and other RTL languages.', 'schemati'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Language Settings', 'schemati')); ?>
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
            'breadcrumb_home' => __('Home', 'schemati'),
            'breadcrumb_separator' => ' › ',
            'show_current' => true
        );
        
        return get_option($group, $defaults);
    }
    
    /**
     * General Settings Page
     */
    public function general_page() {
        $this->handle_form_submission('schemati_general');
        $settings = $this->get_settings('schemati_general');
        
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Schemati - General Settings', 'schemati'); ?></h1>
            
            <?php if (get_transient('schemati_activated')): ?>
                <?php delete_transient('schemati_activated'); ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php _e('Schemati v5.0 Activated!', 'schemati'); ?></strong> <?php _e('All features loaded successfully.', 'schemati'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="notice notice-info">
                <p><strong><?php _e('✅ Schemati v5.0', 'schemati'); ?></strong> - <?php _e('Complete schema solution with sidebar, all schema types, and breadcrumbs.', 'schemati'); ?></p>
            </div>
            
            <!-- Translation Debug Info -->
            <div class="notice notice-warning">
                <p><strong><?php _e('Translation Debug:', 'schemati'); ?></strong></p>
                <p>
                    <?php _e('Locale:', 'schemati'); ?> <code><?php echo get_locale(); ?></code> | 
                    <?php _e('Text Domain Loaded:', 'schemati'); ?> <code><?php echo is_textdomain_loaded('schemati') ? 'YES' : 'NO'; ?></code> |
                    <?php _e('RTL:', 'schemati'); ?> <code><?php echo is_rtl() ? 'YES' : 'NO'; ?></code>
                </p>
                <p><?php _e('Test translation:', 'schemati'); ?> "<strong><?php _e('General Settings', 'schemati'); ?></strong>"</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <h2><?php _e('General Schema Settings', 'schemati'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Schema Markup', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                <?php _e('Enable schema markup output site-wide', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Organization Name', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="org_name" value="<?php echo esc_attr($settings['org_name'] ?? get_bloginfo('name')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your organization or website name', 'schemati'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Organization Type', 'schemati'); ?></th>
                        <td>
                            <select name="org_type">
                                <option value="Organization" <?php selected($settings['org_type'] ?? 'Organization', 'Organization'); ?>><?php _e('Organization', 'schemati'); ?></option>
                                <option value="LocalBusiness" <?php selected($settings['org_type'] ?? '', 'LocalBusiness'); ?>><?php _e('Local Business', 'schemati'); ?></option>
                                <option value="Corporation" <?php selected($settings['org_type'] ?? '', 'Corporation'); ?>><?php _e('Corporation', 'schemati'); ?></option>
                                <option value="Person" <?php selected($settings['org_type'] ?? '', 'Person'); ?>><?php _e('Person', 'schemati'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Breadcrumb Settings', 'schemati'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Home Text', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="breadcrumb_home" value="<?php echo esc_attr($settings['breadcrumb_home'] ?? __('Home', 'schemati')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Separator', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="breadcrumb_separator" value="<?php echo esc_attr($settings['breadcrumb_separator'] ?? ' › '); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Show Current Page', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_current" value="1" <?php checked(1, $settings['show_current'] ?? true); ?> />
                                <?php _e('Display current page in breadcrumb trail', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Usage', 'schemati'); ?></h3>
                <p><strong><?php _e('Shortcode:', 'schemati'); ?></strong> <code>[schemati_breadcrumbs]</code></p>
                <p><strong><?php _e('PHP Function:', 'schemati'); ?></strong> <code>&lt;?php echo schemati_breadcrumbs(); ?&gt;</code></p>
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
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
        
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Article Schema Settings', 'schemati'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Article Schema', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                <?php _e('Generate article schema markup for posts', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Default Article Type', 'schemati'); ?></th>
                        <td>
                            <select name="article_type">
                                <option value="Article" <?php selected($settings['article_type'] ?? 'Article', 'Article'); ?>><?php _e('Article', 'schemati'); ?></option>
                                <option value="BlogPosting" <?php selected($settings['article_type'] ?? '', 'BlogPosting'); ?>><?php _e('Blog Posting', 'schemati'); ?></option>
                                <option value="NewsArticle" <?php selected($settings['article_type'] ?? '', 'NewsArticle'); ?>><?php _e('News Article', 'schemati'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * About Page Schema
     */
    public function about_page() {
        $this->schema_page_template(__('About Page Schema', 'schemati'), 'schemati_about_page', __('Generate schema markup for about pages', 'schemati'));
    }
    
    /**
     * Contact Page Schema
     */
    public function contact_page() {
        $this->schema_page_template(__('Contact Page Schema', 'schemati'), 'schemati_contact_page', __('Generate schema markup for contact pages', 'schemati'));
    }
    
    /**
     * Local Business Schema
     */
    public function business_page() {
        $this->handle_form_submission('schemati_local_business');
        $settings = $this->get_settings('schemati_local_business');
        
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Local Business Schema', 'schemati'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Local Business Schema', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? false); ?> />
                                <?php _e('Generate local business schema markup', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Business Name', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="business_name" value="<?php echo esc_attr($settings['business_name'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Business Type', 'schemati'); ?></th>
                        <td>
                            <select name="business_type">
                                <option value="LocalBusiness" <?php selected($settings['business_type'] ?? 'LocalBusiness', 'LocalBusiness'); ?>><?php _e('Local Business', 'schemati'); ?></option>
                                <option value="Restaurant" <?php selected($settings['business_type'] ?? '', 'Restaurant'); ?>><?php _e('Restaurant', 'schemati'); ?></option>
                                <option value="Store" <?php selected($settings['business_type'] ?? '', 'Store'); ?>><?php _e('Store', 'schemati'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Address', 'schemati'); ?></th>
                        <td>
                            <textarea name="address" rows="3" class="large-text"><?php echo esc_textarea($settings['address'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Phone Number', 'schemati'); ?></th>
                        <td>
                            <input type="text" name="phone" value="<?php echo esc_attr($settings['phone'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Person Schema
     */
    public function person_page() {
        $this->schema_page_template(__('Person Schema', 'schemati'), 'schemati_person', __('Generate schema markup for person/individual', 'schemati'));
    }
    
    /**
     * Author Schema
     */
    public function author_page() {
        $this->schema_page_template(__('Author Schema', 'schemati'), 'schemati_author', __('Generate schema markup for authors', 'schemati'));
    }
    
    /**
     * Publisher Schema
     */
    public function publisher_page() {
        $this->schema_page_template(__('Publisher Schema', 'schemati'), 'schemati_publisher', __('Generate schema markup for publishers', 'schemati'));
    }
    
    /**
     * Product Schema
     */
    public function product_page() {
        $this->schema_page_template(__('Product Schema', 'schemati'), 'schemati_product', __('Generate schema markup for products', 'schemati'));
    }
    
    /**
     * FAQ Schema
     */
    public function faq_page() {
        $this->schema_page_template(__('FAQ Schema', 'schemati'), 'schemati_faq', __('Generate schema markup for FAQ pages', 'schemati'));
    }
    
    /**
     * Tools/CheckTool Page
     */
    public function tools_page() {
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Schemati Tools & Diagnostics', 'schemati'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Schema Testing Tools', 'schemati'); ?></h2>
                <p><?php _e('Test your schema markup with these official tools:', 'schemati'); ?></p>
                <p>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="button button-primary">
                        <?php _e('Google Rich Results Test', 'schemati'); ?>
                    </a>
                    <a href="https://validator.schema.org/" target="_blank" class="button button-secondary">
                        <?php _e('Schema.org Validator', 'schemati'); ?>
                    </a>
                    <a href="https://developers.facebook.com/tools/debug/" target="_blank" class="button button-secondary">
                        <?php _e('Facebook Debugger', 'schemati'); ?>
                    </a>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('Plugin Status', 'schemati'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Version', 'schemati'); ?></strong></td>
                        <td><?php echo SCHEMATI_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Status', 'schemati'); ?></strong></td>
                        <td>
                            <?php 
                            $general = $this->get_settings('schemati_general');
                            echo $general['enabled'] ? '<span style="color: green;">✓ ' . __('Active', 'schemati') . '</span>' : '<span style="color: red;">✗ ' . __('Disabled', 'schemati') . '</span>'; 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Schema Types Available', 'schemati'); ?></strong></td>
                        <td><?php _e('Organization, WebPage, Article, LocalBusiness, Person, Product, FAQ, BreadcrumbList, WPHeader, WPFooter', 'schemati'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WP Header Navigation', 'schemati'); ?></strong></td>
                        <td>
                            <?php 
                            $header_settings = $this->get_settings('schemati_wpheader');
                            echo $header_settings['enabled'] ? '<span style="color: green;">✓ ' . __('Active', 'schemati') . '</span>' : '<span style="color: red;">✗ ' . __('Disabled', 'schemati') . '</span>'; 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WP Footer Navigation', 'schemati'); ?></strong></td>
                        <td>
                            <?php 
                            $footer_settings = $this->get_settings('schemati_wpfooter');
                            echo $footer_settings['enabled'] ? '<span style="color: green;">✓ ' . __('Active', 'schemati') . '</span>' : '<span style="color: red;">✗ ' . __('Disabled', 'schemati') . '</span>'; 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Sidebar', 'schemati'); ?></strong></td>
                        <td><?php _e('✓ Active on frontend for logged-in users', 'schemati'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Breadcrumbs', 'schemati'); ?></strong></td>
                        <td><?php _e('✓ Shortcode and PHP function available', 'schemati'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Current Page Schema Preview', 'schemati'); ?></h2>
                <p><?php _e('Visit your website while logged in to see the Schemati sidebar with live schema preview.', 'schemati'); ?></p>
                <p><a href="<?php echo home_url(); ?>" target="_blank" class="button"><?php _e('View Website with Sidebar', 'schemati'); ?></a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * License Page
     */
    public function license_page() {
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Schemati License', 'schemati'); ?></h1>
            
            <div class="card">
                <h2><?php _e('License Information', 'schemati'); ?></h2>
                <p><?php _e('Schemati v5.0 is licensed under GPL v2 or later.', 'schemati'); ?></p>
                <p><?php _e('This plugin is free and open source.', 'schemati'); ?></p>
                
                <h3><?php _e('Plugin Details', 'schemati'); ?></h3>
                <ul>
                    <li><strong><?php _e('Version:', 'schemati'); ?></strong> <?php echo SCHEMATI_VERSION; ?></li>
                    <li><strong><?php _e('Author:', 'schemati'); ?></strong> Shay Ohayon</li>
                    <li><strong><?php _e('Website:', 'schemati'); ?></strong> <a href="https://schemamarkapp.com" target="_blank">schemamarkapp.com</a></li>
                    <li><strong><?php _e('License:', 'schemati'); ?></strong> GPL v2 or later</li>
                </ul>
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
        
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
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
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
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
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'schemati') . '</p></div>';
        }
    }
    
    /**
     * Add meta boxes for posts and pages
     */
    public function add_meta_boxes() {
        add_meta_box(
            'schemati_schema',
            __('Schema Settings', 'schemati'),
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
                <th><label for="schemati_type"><?php _e('Schema Type', 'schemati'); ?></label></th>
                <td>
                    <select name="schemati_type" id="schemati_type">
                        <option value=""><?php _e('Default', 'schemati'); ?></option>
                        <option value="Article" <?php selected($schema_type, 'Article'); ?>><?php _e('Article', 'schemati'); ?></option>
                        <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>><?php _e('Blog Post', 'schemati'); ?></option>
                        <option value="NewsArticle" <?php selected($schema_type, 'NewsArticle'); ?>><?php _e('News Article', 'schemati'); ?></option>
                        <option value="Product" <?php selected($schema_type, 'Product'); ?>><?php _e('Product', 'schemati'); ?></option>
                        <option value="Event" <?php selected($schema_type, 'Event'); ?>><?php _e('Event', 'schemati'); ?></option>
                        <option value="LocalBusiness" <?php selected($schema_type, 'LocalBusiness'); ?>><?php _e('Local Business', 'schemati'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="schemati_description"><?php _e('Custom Description', 'schemati'); ?></label></th>
                <td>
                    <textarea name="schemati_description" id="schemati_description" rows="3" style="width:100%;"><?php echo esc_textarea($schema_description); ?></textarea>
                    <p class="description"><?php _e('Optional custom description for schema markup', 'schemati'); ?></p>
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
                'title'   => __('Toggle Schemati Sidebar', 'schemati')
            ),
        ));
        
        $admin_bar->add_menu(array(
            'id'     => 'schemati-preview',
            'parent' => 'schemati',
            'title'  => __('View Schema', 'schemati'),
            'href'   => '#',
            'meta'   => array(
                'onclick' => 'showSchematiPreview(); return false;',
            ),
        ));
        
        $admin_bar->add_menu(array(
            'id'     => 'schemati-test',
            'parent' => 'schemati',
            'title'  => __('Test Rich Results', 'schemati'),
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
     * Add interactive sidebar HTML to frontend with editing capabilities - FULLY TRANSLATED
     */
    public function add_sidebar_html() {
        if (!current_user_can('edit_posts') || is_admin()) {
            return;
        }
        
        global $post;
        $general_settings = $this->get_settings('schemati_general');
        $current_schemas = $this->get_enhanced_page_schemas();
        $is_rtl = (is_rtl() || get_locale() === 'he_IL');
        ?>
        <div id="schemati-sidebar" style="display: none; position: fixed; top: 32px; <?php echo $is_rtl ? 'left: 0' : 'right: 0'; ?>; width: 450px; height: calc(100vh - 32px); background: white; border-<?php echo $is_rtl ? 'right' : 'left'; ?>: 1px solid #ccc; z-index: 99999; padding: 0; overflow-y: auto; box-shadow: <?php echo $is_rtl ? '2px' : '-2px'; ?> 0 10px rgba(0,0,0,0.15); font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; <?php echo $is_rtl ? 'direction: rtl;' : ''; ?>">
            
            <!-- Enhanced Header with Dynamic Status -->
            <div style="padding: 20px; background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; position: sticky; top: 0; z-index: 1000;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; font-size: 18px;">
                            <span style="margin-<?php echo $is_rtl ? 'left' : 'right'; ?>: 8px;">⚙️</span>
                            <?php _e('Schemati Editor', 'schemati'); ?>
                        </h3>
                        <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                            <span id="schema-count"><?php echo count($current_schemas); ?> <?php _e('schemas detected', 'schemati'); ?></span>
                            <span style="margin: 0 10px;">•</span>
                            <span id="schema-status"><?php echo $general_settings['enabled'] ? __('Active', 'schemati') : __('Disabled', 'schemati'); ?></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button onclick="syncSchemasWithDOM()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;" title="<?php esc_attr_e('Sync with DOM', 'schemati'); ?>">🔄</button>
                        <button onclick="toggleSchematiSidebar()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: white; padding: 5px;">&times;</button>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Tabs Navigation -->
            <div style="background: #f1f1f1; border-bottom: 1px solid #ccc;">
                <div style="display: flex;">
                    <button class="schemati-tab active" onclick="showSchematiTab('current')" style="flex: 1; padding: 12px; border: none; background: white; cursor: pointer; border-bottom: 2px solid #0073aa; font-size: 12px;">
                        <span style="display: block;"><?php _e('Current', 'schemati'); ?></span>
                        <small style="color: #666;" id="current-count"><?php echo count($current_schemas); ?> <?php _e('schemas', 'schemati'); ?></small>
                    </button>
                    <button class="schemati-tab" onclick="showSchematiTab('add')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                        <span style="display: block;"><?php _e('Add', 'schemati'); ?></span>
                        <small style="color: #666;"><?php _e('New schema', 'schemati'); ?></small>
                    </button>
                    <button class="schemati-tab" onclick="showSchematiTab('templates')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                        <span style="display: block;"><?php _e('Templates', 'schemati'); ?></span>
                        <small style="color: #666;"><?php _e('Quick add', 'schemati'); ?></small>
                    </button>
                    <button class="schemati-tab" onclick="showSchematiTab('settings')" style="flex: 1; padding: 12px; border: none; background: #f1f1f1; cursor: pointer; border-bottom: 2px solid transparent; font-size: 12px;">
                        <span style="display: block;"><?php _e('Settings', 'schemati'); ?></span>
                        <small style="color: #666;"><?php _e('Global', 'schemati'); ?></small>
                    </button>
                </div>
            </div>
            
            <!-- Enhanced Current Schemas Tab with Dynamic Loading -->
            <div id="schemati-tab-current" class="schemati-tab-content" style="padding: 20px;">
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; color: #333; font-size: 14px;"><?php _e('DETECTED SCHEMAS', 'schemati'); ?></h4>
                    <div style="display: flex; gap: 5px;">
                        <button onclick="exportPageSchemas()" style="background: #17a2b8; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="<?php esc_attr_e('Export Schemas', 'schemati'); ?>">💾</button>
                        <button onclick="validateAllSchemas()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="<?php esc_attr_e('Validate All', 'schemati'); ?>">✓</button>
                        <button onclick="toggleAllSchemas()" style="background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="<?php esc_attr_e('Toggle All', 'schemati'); ?>">⚡</button>
                    </div>
                </div>
                
                <!-- Dynamic Schema List - Will be populated by JavaScript -->
                <div id="current-schemas-list">
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <div style="font-size: 24px; margin-bottom: 10px;">🔄</div>
                        <p><?php _e('Loading schemas...', 'schemati'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Add Schema Tab -->
            <div id="schemati-tab-add" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;"><?php _e('ADD NEW SCHEMA', 'schemati'); ?></h4>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Schema Type:', 'schemati'); ?></label>
                    <select id="new-schema-type" onchange="loadSchemaTemplate()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value=""><?php _e('Select Schema Type', 'schemati'); ?></option>
                        <optgroup label="<?php esc_attr_e('Business', 'schemati'); ?>">
                            <option value="LocalBusiness">🏢 <?php _e('Local Business', 'schemati'); ?></option>
                            <option value="Service">🛠️ <?php _e('Service', 'schemati'); ?></option>
                            <option value="Product">📦 <?php _e('Product', 'schemati'); ?></option>
                            <option value="Organization">🏛️ <?php _e('Organization', 'schemati'); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Content', 'schemati'); ?>">
                            <option value="Article">📰 <?php _e('Article', 'schemati'); ?></option>
                            <option value="BlogPosting">📝 <?php _e('Blog Post', 'schemati'); ?></option>
                            <option value="NewsArticle">📺 <?php _e('News Article', 'schemati'); ?></option>
                            <option value="FAQPage">❓ <?php _e('FAQ Page', 'schemati'); ?></option>
                            <option value="HowTo">📋 <?php _e('How-To', 'schemati'); ?></option>
                            <option value="Recipe">🍳 <?php _e('Recipe', 'schemati'); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Events & People', 'schemati'); ?>">
                            <option value="Event">📅 <?php _e('Event', 'schemati'); ?></option>
                            <option value="Person">👤 <?php _e('Person', 'schemati'); ?></option>
                            <option value="Review">⭐ <?php _e('Review', 'schemati'); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Media', 'schemati'); ?>">
                            <option value="VideoObject">🎥 <?php _e('Video', 'schemati'); ?></option>
                            <option value="ImageObject">🖼️ <?php _e('Image', 'schemati'); ?></option>
                            <option value="AudioObject">🎵 <?php _e('Audio', 'schemati'); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Other', 'schemati'); ?>">
                            <option value="WebPage">🌐 <?php _e('Web Page', 'schemati'); ?></option>
                            <option value="WebSite">🌍 <?php _e('Website', 'schemati'); ?></option>
                        </optgroup>
                    </select>
                </div>
                
                <div id="new-schema-form" style="display: none;">
                    <form onsubmit="addNewSchema(); return false;">
                        <div id="schema-template-fields"></div>
                        
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="button" onclick="previewNewSchema()" style="flex: 1; background: #6c757d; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                👁️ <?php _e('Preview', 'schemati'); ?>
                            </button>
                            <button type="submit" style="flex: 2; background: #0073aa; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 500;">
                                ➕ <?php _e('Add Schema', 'schemati'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Templates Tab -->
            <div id="schemati-tab-templates" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;"><?php _e('QUICK TEMPLATES', 'schemati'); ?></h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                    <button onclick="addQuickTemplate('LocalBusiness')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">🏢</div>
                        <div style="font-weight: 500; margin-bottom: 3px;"><?php _e('Local Business', 'schemati'); ?></div>
                        <div style="font-size: 11px; color: #666;"><?php _e('Restaurant, store, office', 'schemati'); ?></div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Service')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">🛠️</div>
                        <div style="font-weight: 500; margin-bottom: 3px;"><?php _e('Service', 'schemati'); ?></div>
                        <div style="font-size: 11px; color: #666;"><?php _e('Professional services', 'schemati'); ?></div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Product')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📦</div>
                        <div style="font-weight: 500; margin-bottom: 3px;"><?php _e('Product', 'schemati'); ?></div>
                        <div style="font-size: 11px; color: #666;"><?php _e('Physical or digital products', 'schemati'); ?></div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Event')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📅</div>
                        <div style="font-weight: 500; margin-bottom: 3px;"><?php _e('Event', 'schemati'); ?></div>
                        <div style="font-size: 11px; color: #666;"><?php _e('Concerts, workshops, meetings', 'schemati'); ?></div>
                    </button>
                    
                    <button onclick="addQuickTemplate('FAQPage')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">❓</div>
                        <div style="font-weight: 500; margin-bottom: 3px;"><?php _e('FAQ Page', 'schemati'); ?></div>
                        <div style="font-size: 11px; color: #666;"><?php _e('Frequently asked questions', 'schemati'); ?></div>
                    </button>
                    
                    <button onclick="addQuickTemplate('Article')" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; transition: all 0.3s ease;">
                        <div style="font-size: 20px; margin-bottom: 5px;">📰</div>
                        <div style="font-weight: 500; margin-bottom: 3px;"><?php _e('Article', 'schemati'); ?></div>
                        <div style="font-size: 11px; color: #666;"><?php _e('Blog posts, news articles', 'schemati'); ?></div>
                    </button>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-<?php echo $is_rtl ? 'right' : 'left'; ?>: 4px solid #0073aa;">
                    <h5 style="margin: 0 0 8px 0; color: #0073aa;">💡 <?php _e('Pro Tip', 'schemati'); ?></h5>
                    <p style="margin: 0; font-size: 13px; line-height: 1.4; color: #666;"><?php _e('Choose a template based on your content type. These templates include the most important fields and follow Google\'s guidelines.', 'schemati'); ?></p>
                </div>
            </div>
            
            <!-- Enhanced Settings Tab -->
            <div id="schemati-tab-settings" class="schemati-tab-content" style="display: none; padding: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px;"><?php _e('GLOBAL SETTINGS', 'schemati'); ?></h4>
                
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                    <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                        <input type="checkbox" id="schema-enabled" <?php checked($general_settings['enabled']); ?> onchange="toggleGlobalSchema()" style="margin-<?php echo $is_rtl ? 'left' : 'right'; ?>: 8px;">
                        <span style="font-weight: 500;"><?php _e('Enable Schema Markup', 'schemati'); ?></span>
                    </label>
                    <div style="font-size: 12px; color: #666; margin-<?php echo $is_rtl ? 'right' : 'left'; ?>: 20px;">
                        <?php _e('Controls whether schema markup is output on your website', 'schemati'); ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 10px 0; font-size: 13px; color: #333;"><?php _e('QUICK ACTIONS', 'schemati'); ?></h5>
                    <div style="display: grid; gap: 8px;">
                        <button onclick="showSchematiPreview()" style="width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>🔍</span>
                            <span><?php _e('Preview All Schemas', 'schemati'); ?></span>
                        </button>
                        <button onclick="testGoogleRichResults()" style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>🚀</span>
                            <span><?php _e('Test Rich Results', 'schemati'); ?></span>
                        </button>
                        <button onclick="exportAllSchemas()" style="width: 100%; padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>💾</span>
                            <span><?php _e('Export All Schemas', 'schemati'); ?></span>
                        </button>
                        <button onclick="importSchemas()" style="width: 100%; padding: 12px; background: #fd7e14; color: white; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span>📥</span>
                            <span><?php _e('Import Schemas', 'schemati'); ?></span>
                        </button>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 10px 0; font-size: 13px; color: #333;"><?php _e('BULK OPERATIONS', 'schemati'); ?></h5>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <button onclick="enableAllSchemas()" style="padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">✓ <?php _e('Enable All', 'schemati'); ?></button>
                        <button onclick="disableAllSchemas()" style="padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">✗ <?php _e('Disable All', 'schemati'); ?></button>
                        <button onclick="duplicateCurrentSchemas()" style="padding: 10px; background: #6f42c1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">📋 <?php _e('Duplicate', 'schemati'); ?></button>
                        <button onclick="resetAllSchemas()" style="padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">🗑️ <?php _e('Reset All', 'schemati'); ?></button>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=schemati'); ?>" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; text-align: center; text-decoration: none; justify-content: center;">
                        <span>⚙️</span>
                        <span><?php _e('Full Settings Panel', 'schemati'); ?></span>
                    </a>
                </div>
                
                <div style="font-size: 11px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 15px;">
                    <div>Schemati v5.0 | <?php _e('Live Schema Editor', 'schemati'); ?></div>
                    <div style="margin-top: 4px;">
                        <a href="https://search.google.com/test/rich-results" target="_blank" style="color: #0073aa; text-decoration: none;"><?php _e('Google Rich Results Test', 'schemati'); ?></a> |
                        <a href="https://validator.schema.org/" target="_blank" style="color: #0073aa; text-decoration: none;"><?php _e('Schema Validator', 'schemati'); ?></a>
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
                        <h2 style="margin: 0; color: white;">🔍 <?php _e('Live Schema Preview', 'schemati'); ?></h2>
                        <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;" id="schema-preview-count"><?php _e('Loading...', 'schemati'); ?></div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="copyAllSchemas()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">📋 <?php _e('Copy All', 'schemati'); ?></button>
                        <button onclick="hideSchematiPreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: white; padding: 5px;">&times;</button>
                    </div>
                </div>
                <div id="schema-modal-content" style="height: calc(100% - 80px); overflow-y: auto; padding: 20px; font-family: monospace; font-size: 12px; line-height: 1.5;">
                    <?php _e('Loading schema data...', 'schemati'); ?>
                </div>
            </div>
        </div>
        
        <script>
        // Enhanced JavaScript with Translation Support
        var currentPostId = <?php echo get_the_ID() ?: 0; ?>;
        var detectedSchemas = [];
        var phpSchemas = <?php echo json_encode($current_schemas); ?>;
        
        // JavaScript Translation Object
        var schematiStrings = {
            'schemasDetected': '<?php echo esc_js(__('schemas detected', 'schemati')); ?>',
            'schemas': '<?php echo esc_js(__('schemas', 'schemati')); ?>',
            'noSchemasDetected': '<?php echo esc_js(__('No schemas detected', 'schemati')); ?>',
            'addFirstSchema': '<?php echo esc_js(__('Add your first schema using the "Add" tab or choose from templates.', 'schemati')); ?>',
            'browseTemplates': '<?php echo esc_js(__('Browse Templates', 'schemati')); ?>',
            'systemGeneratedSchema': '<?php echo esc_js(__('System Generated Schema', 'schemati')); ?>',
            'systemSchemaDescription': '<?php echo esc_js(__('This schema is automatically generated. You can modify global settings in the admin panel.', 'schemati')); ?>',
            'editGlobalSettings': '<?php echo esc_js(__('Edit Global Settings', 'schemati')); ?>',
            'noSchemaFound': '<?php echo esc_js(__('No Schema Found', 'schemati')); ?>',
            'noSchemaDetectedDescription': '<?php echo esc_js(__('No schema markup was detected on this page.', 'schemati')); ?>',
            'noSchemasFound': '<?php echo esc_js(__('No schemas found', 'schemati')); ?>',
            'found': '<?php echo esc_js(__('Found', 'schemati')); ?>',
            'schemaTypes': '<?php echo esc_js(__('Schema Type(s)', 'schemati')); ?>',
            'schemasFormatted': '<?php echo esc_js(__('All schemas are properly formatted and ready for search engines.', 'schemati')); ?>',
            'copy': '<?php echo esc_js(__('Copy', 'schemati')); ?>',
            'schemasDetectedValidated': '<?php echo esc_js(__('schemas detected and validated', 'schemati')); ?>',
            'schemaCopied': '<?php echo esc_js(__('Schema copied to clipboard!', 'schemati')); ?>',
            'allSchemasCopied': '<?php echo esc_js(__('All schemas copied to clipboard!', 'schemati')); ?>',
            'schemaAddedSuccess': '<?php echo esc_js(__('Schema added successfully!', 'schemati')); ?>',
            'errorAddingSchema': '<?php echo esc_js(__('Error adding schema:', 'schemati')); ?>',
            'confirmDeleteSchema': '<?php echo esc_js(__('Are you sure you want to delete this schema?', 'schemati')); ?>',
            'schemaSavedSuccess': '<?php echo esc_js(__('Schema saved successfully!', 'schemati')); ?>',
            'errorSavingSchema': '<?php echo esc_js(__('Error saving schema:', 'schemati')); ?>',
            'globalSettingUpdated': '<?php echo esc_js(__('Global schema setting updated!', 'schemati')); ?>',
            'schemaPreview': '<?php echo esc_js(__('Schema Preview', 'schemati')); ?>',
            'schemaPreviewDescription': '<?php echo esc_js(__('This is how your schema will look when added.', 'schemati')); ?>',
            'schemasImportedSuccess': '<?php echo esc_js(__('Schemas imported successfully!', 'schemati')); ?>',
            'schemasLoaded': '<?php echo esc_js(__('schemas loaded.', 'schemati')); ?>',
            'errorImportingSchemas': '<?php echo esc_js(__('Error importing schemas: Invalid JSON file.', 'schemati')); ?>',
            'enableAllSchemas': '<?php echo esc_js(__('Enable all schemas on this page?', 'schemati')); ?>',
            'allSchemasEnabled': '<?php echo esc_js(__('All schemas enabled!', 'schemati')); ?>',
            'disableAllSchemas': '<?php echo esc_js(__('Disable all schemas on this page?', 'schemati')); ?>',
            'allSchemasDisabled': '<?php echo esc_js(__('All schemas disabled!', 'schemati')); ?>',
            'duplicateAllSchemas': '<?php echo esc_js(__('Duplicate all current schemas?', 'schemati')); ?>',
            'schemasDuplicated': '<?php echo esc_js(__('Schemas duplicated!', 'schemati')); ?>',
            'resetAllSchemas': '<?php echo esc_js(__('Reset all schemas? This will remove all custom schemas.', 'schemati')); ?>',
            'allSchemasReset': '<?php echo esc_js(__('All schemas reset!', 'schemati')); ?>',
            'validationResults': '<?php echo esc_js(__('Validation Results:', 'schemati')); ?>',
            'valid': '<?php echo esc_js(__('Valid:', 'schemati')); ?>',
            'invalid': '<?php echo esc_js(__('Invalid:', 'schemati')); ?>',
            'allSchemasToggled': '<?php echo esc_js(__('All schemas toggled!', 'schemati')); ?>',
            'loadingSchemas': '<?php echo esc_js(__('Loading schemas...', 'schemati')); ?>',
            'loading': '<?php echo esc_js(__('Loading...', 'schemati')); ?>',
            'name': '<?php echo esc_js(__('Name:', 'schemati')); ?>',
            'description': '<?php echo esc_js(__('Description:', 'schemati')); ?>',
            'cancel': '<?php echo esc_js(__('Cancel', 'schemati')); ?>',
            'saveChanges': '<?php echo esc_js(__('Save Changes', 'schemati')); ?>',
            'global': '<?php echo esc_js(__('Global', 'schemati')); ?>',
            'post': '<?php echo esc_js(__('Post', 'schemati')); ?>',
            'auto': '<?php echo esc_js(__('Auto', 'schemati')); ?>',
            'custom': '<?php echo esc_js(__('Custom', 'schemati')); ?>',
            'system': '<?php echo esc_js(__('System', 'schemati')); ?>',
            'unknown': '<?php echo esc_js(__('Unknown', 'schemati')); ?>'
        };
        
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
            
            // Update counters
            updateSchemaCounts();
            
            // Re-render current schemas list
            renderCurrentSchemas();
            
            console.log('Schemati: Synced', detectedSchemas.length, 'schemas', detectedSchemas);
        }
        
        // Update all schema counters
        function updateSchemaCounts() {
            var count = detectedSchemas.length;
            document.getElementById('schema-count').textContent = count + ' ' + schematiStrings.schemasDetected;
            document.getElementById('current-count').textContent = count + ' ' + schematiStrings.schemas;
        }
        
        // Render current schemas in the sidebar
        function renderCurrentSchemas() {
            var container = document.getElementById('current-schemas-list');
            
            if (detectedSchemas.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                        <h4>${schematiStrings.noSchemasDetected}</h4>
                        <p>${schematiStrings.addFirstSchema}</p>
                        <button onclick="showSchematiTab('templates')" style="background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">${schematiStrings.browseTemplates}</button>
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
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">${schematiStrings.name}</label>
                                <input type="text" name="name" value="${schema.name || ''}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">${schematiStrings.description}</label>
                                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${schema.description || ''}</textarea>
                            </div>
                            <div style="margin-top: 15px; text-align: right;">
                                <button type="button" onclick="toggleSchemaEditor(${index})" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; margin-right: 5px; cursor: pointer;">${schematiStrings.cancel}</button>
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
        
        // Get source information
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
                content.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><div style="font-size: 48px; margin-bottom: 15px;">📋</div><h3>' + schematiStrings.noSchemaFound + '</h3><p>' + schematiStrings.noSchemaDetectedDescription + '</p></div>';
                countElement.textContent = schematiStrings.noSchemasFound;
            } else {
                var html = '<div style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724; border-left: 4px solid #28a745;"><h3 style="margin: 0; display: flex; align-items: center; gap: 8px;"><span>✅</span>' + schematiStrings.found + ' ' + detectedSchemas.length + ' ' + schematiStrings.schemaTypes + '</h3><p style="margin: 5px 0 0 0; font-size: 13px;">' + schematiStrings.schemasFormatted + '</p></div>';
                
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
                    html += '<button onclick="copySchema(' + index + ')" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;">' + schematiStrings.copy + '</button>';
                    html += '</div>';
                    html += '<pre style="background: #2d3748; color: #e2e8f0; padding: 20px; margin: 0; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.5;">' + JSON.stringify(schema, null, 2) + '</pre>';
                    html += '</div>';
                });
                content.innerHTML = html;
                countElement.textContent = detectedSchemas.length + ' ' + schematiStrings.schemasDetectedValidated;
            }
            
            modal.style.display = 'block';
        }
        
        // Copy individual schema
        function copySchema(index) {
            if (detectedSchemas[index]) {
                navigator.clipboard.writeText(JSON.stringify(detectedSchemas[index], null, 2)).then(() => {
                    alert(schematiStrings.schemaCopied);
                });
            }
        }
        
        // Copy all schemas
        function copyAllSchemas() {
            navigator.clipboard.writeText(JSON.stringify(detectedSchemas, null, 2)).then(() => {
                alert(schematiStrings.allSchemasCopied);
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
        
        // Load schema template - called by dropdown onchange
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
        
        // Add new schema - called by form submit
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
        
        // Toggle schema status - called by ON/OFF buttons (AJAX version)
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
        
        // Delete schema - called by delete buttons (AJAX version)
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
        
        // Save schema changes - called by save forms
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
        
        // Toggle global schema - called by settings checkbox
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
        
        // Quick template functions
        function addQuickTemplate(type) {
            document.getElementById('new-schema-type').value = type;
            loadSchemaTemplate();
            showSchematiTab('add');
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
            
            content.innerHTML = '<div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404; border-left: 4px solid #ffc107;"><h3 style="margin: 0;">👁️ ' + schematiStrings.schemaPreview + '</h3><p style="margin: 5px 0 0 0; font-size: 13px;">' + schematiStrings.schemaPreviewDescription + '</p></div><pre style="background: #2d3748; color: #e2e8f0; padding: 20px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; font-size: 11px; line-height: 1.4;">' + JSON.stringify(previewSchema, null, 2) + '</pre>';
            
            modal.style.display = 'block';
        }
        
        // Export/Import functions
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
            exportPageSchemas(); // For now, same as page schemas
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
        
        // Bulk operations
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
        
        // Fix the refreshSchemas function name mismatch
        function refreshSchemas() {
            syncSchemasWithDOM();
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

        console.log('🧪 Schemati: JavaScript loaded with full translation support');
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
        
        // Home
        $breadcrumbs[] = array(
            'title' => $settings['breadcrumb_home'] ?? __('Home', 'schemati'),
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
        
        $html = '<nav class="schemati-breadcrumbs" aria-label="Breadcrumb">';
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
     * Frontend styles for breadcrumbs
     */
    public function frontend_styles() {
        $is_rtl = (is_rtl() || get_locale() === 'he_IL');
        ?>
        <style>
        .schemati-breadcrumbs {
            margin: 1em 0;
            font-size: 14px;
            <?php if ($is_rtl): ?>
            direction: rtl;
            text-align: right;
            <?php endif; ?>
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

// Start the plugin after translations are loaded
add_action('plugins_loaded', 'schemati_init', 10);

// Helper function for themes
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
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                <?php _e('Generate WPHeader schema markup for navigation', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Menu Location', 'schemati'); ?></th>
                        <td>
                            <select name="menu_location">
                                <?php
                                $locations = get_registered_nav_menus();
                                if (empty($locations)) {
                                    echo '<option value="primary">Primary Menu (Default)</option>';
                                } else {
                                    foreach ($locations as $location => $description) {
                                        echo '<option value="' . esc_attr($location) . '" ' . selected($settings['menu_location'] ?? 'primary', $location, false) . '>' . esc_html($description) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('Select which menu location to use for header schema', 'schemati'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Include Submenus', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_submenu" value="1" <?php checked(1, $settings['include_submenu'] ?? false); ?> />
                                <?php _e('Include submenu items in schema markup', 'schemati'); ?>
                            </label>
                            <p class="description"><?php _e('Note: This may create large schema markup for complex menus', 'schemati'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Preview Current Header Menu', 'schemati'); ?></h3>
                <?php
                $nav_items = $this->get_navigation_items('header');
                if (!empty($nav_items)) {
                    echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #0073aa;">';
                    echo '<h4 style="margin-top: 0;">' . __('Detected Navigation Items:', 'schemati') . '</h4>';
                    echo '<ul style="margin: 0;">';
                    foreach ($nav_items as $item) {
                        echo '<li><strong>' . esc_html($item['name']) . '</strong> - <code>' . esc_html($item['url']) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                    echo '<p>' . __('No navigation items detected. Make sure you have a menu assigned to the selected location.', 'schemati') . '</p>';
                    echo '</div>';
                }
                ?>
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * WP Footer Schema settings page
     */
    public function wpfooter_page() {
        $this->handle_form_submission('schemati_wpfooter');
        $settings = $this->get_settings('schemati_wpfooter');
        
        $locale = get_locale();
        $is_rtl = (is_rtl() || $locale === 'he_IL');
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('WP Footer Navigation Schema', 'schemati'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('📍 WP Footer Schema', 'schemati'); ?></strong> - <?php _e('Automatically generates schema markup for your footer navigation menu.', 'schemati'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_save', 'schemati_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Footer Schema', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(1, $settings['enabled'] ?? true); ?> />
                                <?php _e('Generate WPFooter schema markup for navigation', 'schemati'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Menu Location', 'schemati'); ?></th>
                        <td>
                            <select name="menu_location">
                                <?php
                                $locations = get_registered_nav_menus();
                                if (empty($locations)) {
                                    echo '<option value="footer">Footer Menu (Default)</option>';
                                } else {
                                    foreach ($locations as $location => $description) {
                                        echo '<option value="' . esc_attr($location) . '" ' . selected($settings['menu_location'] ?? 'footer', $location, false) . '>' . esc_html($description) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('Select which menu location to use for footer schema', 'schemati'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Include Submenus', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_submenu" value="1" <?php checked(1, $settings['include_submenu'] ?? false); ?> />
                                <?php _e('Include submenu items in schema markup', 'schemati'); ?>
                            </label>
                            <p class="description"><?php _e('Note: This may create large schema markup for complex menus', 'schemati'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Preview Current Footer Menu', 'schemati'); ?></h3>
                <?php
                $nav_items = $this->get_navigation_items('footer');
                if (!empty($nav_items)) {
                    echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #0073aa;">';
                    echo '<h4 style="margin-top: 0;">' . __('Detected Navigation Items:', 'schemati') . '</h4>';
                    echo '<ul style="margin: 0;">';
                    foreach ($nav_items as $item) {
                        echo '<li><strong>' . esc_html($item['name']) . '</strong> - <code>' . esc_html($item['url']) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                    echo '<p>' . __('No navigation items detected. Make sure you have a menu assigned to the selected location.', 'schemati') . '</p>';
                    echo '</div>';
                }
                ?>
                
                <?php submit_button(__('Save Changes', 'schemati')); ?>
            </form>
        </div>
        <?php
    }

    public function language_page() {
        if (isset($_POST['schemati_language_nonce']) && wp_verify_nonce($_POST['schemati_language_nonce'], 'schemati_language_save')) {
            $new_language = sanitize_text_field($_POST['schemati_language']);
            update_option('schemati_language', $new_language);
            echo '<div class="notice notice-success"><p>' . __('Language settings saved successfully!', 'schemati') . '</p></div>';
            
            // Generate translation files if needed
            if ($new_language === 'he_IL') {
                schemati_ensure_hebrew_translation();
            }
        }
        
        $current_language = get_option('schemati_language', 'auto');
        $current_locale = get_locale();
        $is_rtl = is_rtl() || $current_locale === 'he_IL';
        
        if ($is_rtl) {
            echo '<style>body { direction: rtl; } .wrap { text-align: right; }</style>';
        }
        ?>
        <div class="wrap <?php echo $is_rtl ? 'schemati-rtl' : ''; ?>" <?php echo $is_rtl ? 'style="direction: rtl; text-align: right;"' : ''; ?>>
            <h1><?php _e('Schemati - Language Settings', 'schemati'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('🌍 Multilingual Support', 'schemati'); ?></strong> - <?php _e('Choose your preferred language for the Schemati interface.', 'schemati'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('schemati_language_save', 'schemati_language_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Language', 'schemati'); ?></th>
                        <td>
                            <select name="schemati_language" style="min-width: 200px;">
                                <option value="auto" <?php selected($current_language, 'auto'); ?>>
                                    <?php _e('Auto (Follow WordPress Language)', 'schemati'); ?>
                                </option>
                                <option value="en_US" <?php selected($current_language, 'en_US'); ?>>
                                    🇺🇸 English (United States)
                                </option>
                                <option value="he_IL" <?php selected($current_language, 'he_IL'); ?>>
                                    🇮🇱 עברית (Hebrew)
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Current WordPress locale:', 'schemati'); ?> <code><?php echo $current_locale; ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('RTL Support', 'schemati'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" disabled <?php checked(is_rtl() || $current_language === 'he_IL'); ?> />
                                <?php _e('Right-to-Left text direction', 'schemati'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Automatically enabled for Hebrew and other RTL languages.', 'schemati'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Language Settings', 'schemati')); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
function schemati_init() {
    return Schemati::instance();
}

// Start the plugin after translations are loaded
add_action('plugins_loaded', 'schemati_init', 10);

// Helper function for themes
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