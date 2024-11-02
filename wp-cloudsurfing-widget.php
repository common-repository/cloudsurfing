<?php
/**
 * Plugin Name: CloudSurfing Widget
 * Plugin URI: http://www.cloudsurfing.com/widgets
 * Description: A plugin that holds CloudSurfing widgets on wordpress.
 * Version: 0.1.0
 * Author: Abhishek Kumar Srivastava
 * Mail: abhishek.odesk@gmail.com
 */
/* Version check */
global $wp_version;
$exit_msg='CloudSurfing requires WordPress 2.9 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please update!</a>';

if (version_compare($wp_version,"2.9","<")) {
    exit ($exit_msg);
}

define('CLOUDSURFING_VERSION', '0.1.0');

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

function cloudsurfing_init() {
	add_action('admin_menu', 'cloudsurfing_config_page');
	cloudsurfing_admin_warnings();
}
add_action('init', 'cloudsurfing_init');

if ( !function_exists('number_format_i18n') ) {
	function number_format_i18n( $number, $decimals = null ) { return number_format( $number, $decimals ); }
}

function cloudsurfing_config_page() {
	if ( function_exists('add_submenu_page') )
		add_submenu_page('plugins.php', __('CloudSurfing Configuration'), __('CloudSurfing Configuration'), 'manage_options', 'cloudsurfing-key-config', 'cloudsurfing_conf');
}

function cloudsurfing_admin_warnings() {
    function cloudsurfing_warning() {
        $options = get_option('widget_cloudsurfing');
        if(!isset($options['url']) || $options['url'] == '') {
            echo "
            <div id='cloudsurfing-warning' class='updated fade'><p><strong>".__('CloudSurfing is almost ready.')."</strong> ".__('You must have <a href="http://www.cloudsurfing.com/widgets">widget url</a> for it to work.')."</p></div>
            ";
        }
    }
    add_action('admin_notices', 'cloudsurfing_warning');
    return;
}

function cloudsurfing_get_base_from_url($url) {
    $chars = preg_split('//', $url, -1, PREG_SPLIT_NO_EMPTY);
    $slash = 3; // 3rd slash
    $i = 0;
    foreach($chars as $key => $char) {
        if($char == '/') {
           $j = $i++;
        }

        if($i == 3) {
           $pos = $key; break;
        }
    }

    $main_base = substr($url, 0, $pos);

    return $main_base.'/';
}


add_action('wp_print_scripts','wp_load_widget_js');
function wp_load_widget_js(){
	if (is_admin()) return; // probably don't want this on admin pages
	wp_enqueue_script('jquery');
}

// Widget stuff
function widget_cloudsurfing_register() {
	if ( function_exists('register_sidebar_widget') ) :
	function widget_cloudsurfing($args) {
		extract($args);
		$options = get_option('widget_cloudsurfing');
		?>
			<?php echo $before_widget; ?>
                <script type='text/javascript'>
                    var cloudsurfing_url = '<?php echo $options['url'];?>';
                </script>
				<div id="cloudsurfing-widget">
                <?php echo widget_cloudsurfing_fetch($options['url']);?>
                </div>
			<?php echo $after_widget; ?>
	<?php
	}
	function widget_cloudsurfing_style() {
		$plugin_dir = '/wp-content/plugins';
		if ( defined( 'PLUGINDIR' ) )
			$plugin_dir = '/' . PLUGINDIR;

		?>
		<?php
	}

	function widget_cloudsurfing_control() {
		$options = $newoptions = get_option('widget_cloudsurfing');
		if ( isset( $_POST['cloudsurfing-submit'] ) && $_POST["cloudsurfing-submit"] ) {
			$newoptions['url'] = strip_tags(stripslashes($_POST["cloudsurfing-url"]));
			if ( empty($newoptions['url']) ) $newoptions['url'] = __('CloudSurfing widget Blocked');
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_cloudsurfing', $options);
		}
		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		$url = htmlspecialchars($options['url'], ENT_QUOTES);
        $type = $options['type'];
	?>
				<p><label for="cloudsurfing-url"><?php _e('URL:'); ?> <input style="width: 250px;" id="cloudsurfing-url" name="cloudsurfing-url" type="text" value="<?php echo $url; ?>" /></label></p>
				<input type="hidden" id="cloudsurfing-submit" name="cloudsurfing-submit" value="1" />
	<?php
	}

	if ( function_exists( 'wp_register_sidebar_widget' ) ) {
		wp_register_sidebar_widget( 'cloudsurfing', 'CloudSurfing', 'widget_cloudsurfing', null, 'cloudsurfing');
		wp_register_widget_control( 'cloudsurfing', 'CloudSurfing', 'widget_cloudsurfing_control', null, 75, 'cloudsurfing');
	} else {
		register_sidebar_widget('CloudSurfing', 'widget_cloudsurfing', null, 'cloudsurfing');
		register_widget_control('CloudSurfing', 'widget_cloudsurfing_control', null, 75, 'cloudsurfing');
	}
	if ( is_active_widget('widget_cloudsurfing') )
		add_action('wp_head', 'widget_cloudsurfing_style');
	endif;
}

function widget_cloudsurfing_fetch($url) {
    $response = wp_remote_request($url, array('headers' => array('user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)')));
    if ( is_wp_error( $response ) )
        return '';
    if ( 200 != $response['response']['code'] )
        return '';
    return $response['body'];
}  

add_action('init', 'widget_cloudsurfing_register');
// AJAXify the next and previous link
add_action('wp_head', 'widget_cloudsurfing_js_header' );

function widget_cloudsurfing_js_header() {
  // use JavaScript SACK library for Ajax
  wp_print_scripts( array( 'sack' ));
  // Define custom JavaScript function
?>
<script type="text/javascript">
//<![CDATA[
var cloudsurfing_selected = 1
function cloudsurfing_goto_next() {
    var mysack = new sack( "<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );    
    cloudsurfing_selected = cloudsurfing_selected + 1;

    mysack.execute = 1;
    mysack.method = 'POST';
    mysack.setVar( "action", "widget_cloudsurfing_goto_next" );
    mysack.setVar( "url", cloudsurfing_url );
    mysack.setVar( "selected", cloudsurfing_selected );
    mysack.onError = function() { alert('Ajax error in next' )};
    mysack.runAJAX();
    return true;
}
function cloudsurfing_goto_previous() {
    var mysack = new sack( "<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );    
    cloudsurfing_selected = cloudsurfing_selected - 1;

    mysack.execute = 1;
    mysack.method = 'POST';
    mysack.setVar( "action", "widget_cloudsurfing_goto_previous" );
    mysack.setVar( "url", cloudsurfing_url );
    mysack.setVar( "selected", cloudsurfing_selected );
    mysack.onError = function() { alert('Ajax error in next' )};
    mysack.runAJAX();
    return true;
}
//]]>
</script>
<?php
} // end of PHP function myplugin_js_header
add_action('wp_ajax_widget_cloudsurfing_goto_next', 'cloudsurfing_goto_selection');
add_action('wp_ajax_nopriv_widget_cloudsurfing_goto_next', 'cloudsurfing_goto_selection');
add_action('wp_ajax_widget_cloudsurfing_goto_previous', 'cloudsurfing_goto_selection');
add_action('wp_ajax_nopriv_widget_cloudsurfing_goto_previous', 'cloudsurfing_goto_selection');

function cloudsurfing_goto_selection() {
    $url = $_POST['url'];
    $selected = $_POST['selected'];
    if(strpos($url, 'selected=')) {
        $url = preg_replace('/selected=[0-9]*/','selected='. $selected, $url);
    }
    else {
        $url .= '&selected' . $selected;
    }
    $results = widget_cloudsurfing_fetch($url);

    die( "jQuery('#cloudsurfing-widget').html('" . addslashes(preg_replace('/[\n\r]*/','', $results)) . "');");
}
?>

