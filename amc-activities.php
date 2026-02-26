<?php
/**
 * Plugin Name: AMC Activities
 * Description: Display AMC activities by chapter using shortcodes and a Gutenberg block.
 * Version: 1.11
 * Author: Bill Nesheim
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * $Id: amc-activities.php,v 1.12 2026/02/26 16:57:32 bnesheim Exp $
 */
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/* ---- CONFIG ---- */
define('AMC_AURA_URL', 'https://activities.outdoors.org/s/sfsites/aura');
define('AMC_BASE_URL', 'https://activities.outdoors.org');
/* -- Salesforce goo, supposedly necessary but changing it doesn't seem to cause problems.... */
define('AMC_FWUID', 'REdtNUF5ejJUNWxpdVllUjQtUzV4UTFLcUUxeUY3ZVB6dE9hR0VheDVpb2cxMy4zMzU1NDQzMi41MDMzMTY0OA');

define('AMC_CACHE_TTL_DEFAULT', 21600);  /* 6 hrs */
define('AMC_DEFAULT_CHAPTER', "AMC New Hampshire Chapter");

/* ======================================================
   Chapter Map (name => Salesforce ID)
   ====================================================== */
function amc_get_chapter_map() {
    static $map = null;
    if ($map !== null) return $map;

    $map = [
	    "All Chapters" => "--all--",
	    "AMC Adventure Travel" => "0015000001TGzFBAA1",
	    "AMC Boston Chapter" => "0015000001Sg061AAB",
	    "AMC Connecticut Chapter" => "0015000001Sg05zAAB",
	    "AMC Delaware Valley Chapter" => "0015000001Sg06BAAR",
	    "AMC Knubble Bay" => "001UN00000Wr5vBYAR",
	    "AMC Maine Chapter" => "0015000001Sg065AAB",
	    "AMC Managed Programs" => "001UN00000KhlIGYAZ",
	    "AMC Narragansett Chapter" => "0015000001Sg066AAB",
	    "AMC New Hampshire Chapter" => "0015000001Sg060AAB",
	    "AMC New York-North Jersey Chapter" => "0015000001Sg064AAB",
	    "AMC Potomac Chapter" => "0015000001Sg06EAAR",
	    "AMC Southeastern Massachusetts Chapter" => "0015000001Sg06DAAR",
	    "AMC Staff Activities" => "001UN00000bpnMdYAI",
	    "AMC Unaffiliated Chapter" => "0015000001Sg0VRAAZ",
	    "AMC Western Massachusetts Chapter" => "0015000001Sg05xAAB",
	    "AMC Worcester Chapter" => "0015000001Sg069AAB",
	    "VCC August Camp" => "001VX00000pWIioYAG",
	    "VCC Cold River Camp" => "001VX00000pW41XYAS",
	    "VCC Echo Lake" => "001VX00000pWMZVYA4",
	    "VCC Harvard Cabin" => "001VX00000pWLlVYAW",
	    "VCC Ponkapoag" => "001VX00000pVfHeYAK",
	    "VCC Three Mile Island" => "001VX00000pWDhWYAW"
	    ];

    return $map;
}

/* -- Activity list -- */
function amc_get_activity_list() {
  static $alist = null;
  if ($alist !== null) return $alist;

  $alist = [
	    "All Activities",
	    "Adventure Travel",
	    "Backpacking",
	    "Biking",
	    "Camping",
	    "Climbing & Mountaineering",
	    "Conservation",
	    "Fishing",
	    "Hiking, Local Walks, & Trail Running",
	    "Nature & Arts",
	    "Outdoor Leadership Training",
	    "Outdoor Learning Experience",
	    "Paddling",
	    "Sailing",
	    "Skiing",
	    "Snowshoeing",
	    "Social",
	    "Trail Work",
	    "Volunteer Opportunities",
	    "Wilderness First Aid",
	    "Windsurfing",
	    "Yoga"
	    ];

  return $alist;
}

/* Valid audiences */
function amc_get_audience_list() {
  static $alist = null;
  if ($alist !== null) return $alist;

  $alist = [
	    "20’s & 30’s",
	    "55+",
	    "Adults",
	    "BIPOC",
	    "Educators",
	    "Families",
	    "LGBTQ+",
	    "Teens",
	    "Women+"
	    ];
	    
  return $alist;
}


	     
function amc_get_chapter_name(string $ChapterID) {
  $chapters=amc_get_chapter_map();
  $key = array_search($ChapterID, $chapters);
  return $key;
}

/* ---- DEBUG ---- */
function amc_debug($msg) {
    if (!get_option('amc_debug_mode')) return;
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[AMC DEBUG] ' . $msg);
    }
    /*echo "\n<!-- AMC DEBUG: " . esc_html($msg) . " -->\n";*/
}

/* ---- FETCH ---- */
function amc_fetch_events_cached(string $chapter_id, array $activity_types, string $keywords, string $audiences) {

  if (empty($activity_types) or in_array("All Activities", $activity_types)) {
    $activity_types = ["All Activities"];
    $activity_filter = [];
    /* Don't allow all chapters, all activities requests, too big    if ($chapter_id == "--all--") {
      return ['<p style="color: red;">Please specify at least one Chapter or Activity Type</p>'];
    }
    */
  } else {
    $activity_filter = array_map(fn($t)=>["type"=>trim($t)], $activity_types);
  }

  $cache_key = 'amc_events_' . md5($chapter_id . implode('|', $activity_types) . $keywords . $audiences);
  $ttl = intval(get_option('amc_cache_ttl', AMC_CACHE_TTL_DEFAULT));

  if (($cached = get_transient($cache_key)) !== false) {
    return $cached;
  }
  
  if (!empty($audiences)) {
    // replace ASCII apostrophe with Unicode right single quote
      $audiences = str_replace("'", "’", $audiences);  /* Replace ASCII apostrophe with Unicode right single quote */
  } 

  $filters = [
	      "additionalFilters" => [
				      "audiences" => $audiences,
				      "programTypes" => "--all--",
				      "openForRegistration" => false,
				      "noCostTrips" => false
				      ]
	      ];
  if ($chapter_id !== "--all--") {
    $filters ["additionalFilters"]["chapters"] = $chapter_id;
  }
  if (!empty($activity_filter)) {
    $filters["activityTypeSubTypes"] = $activity_filter;
  }
  if (!empty($keywords)) {
    $filters["keywords"] = $keywords;
  }



  $payload = [
	      "message" => wp_json_encode([
					   "actions" => [[
							  "id" => "1;a",
							  "descriptor" => 'aura://ApexActionController/ACTION$execute',
							  "callingDescriptor" => "UNKNOWN",
							  "params" => [
								       "namespace" => "",
								       "classname" => "OC_ActivitySearchController",
								       "method" => "searchForActivitiesApplyFilters",
								       "params" => ["filtersJsonSpecs" => wp_json_encode($filters)],
								       "cacheable"=> false,
								       "isContinuation"=> false
								       ],
							  ]]
					   ]),
	      "aura.context" => wp_json_encode([
						"mode" => "PROD",
						"app" => "siteforce:communityApp",
						"fwuid" => AMC_FWUID,
						"dn"=> []
						]),
	      "aura.pageURI" => "/s/",
	      "aura.token" => "null"
	      ];
  amc_debug("post request: " . print_r($payload, true));
  $resp = wp_remote_post(AMC_AURA_URL, [
					'headers'=>['Content-Type'=>'application/x-www-form-urlencoded'],
					'body'=>$payload,
					'timeout' => 30
					]);

  if (is_wp_error($resp)) {
    $error_string = $resp->get_error_message();
    amc_debug("wp_remote_post error: " . $error_string);
    $acts = ['<p style="color: red;">Error retrieving activities from Salesforce:<br>' . $error_string . '</p>'];
    return $acts;
  }

  $body = json_decode(wp_remote_retrieve_body($resp), true);
  $acts = $body['actions'][0]['returnValue']['returnValue'] ?? [];
  amc_debug(sprintf("fetched %d events for chapter \"%s\" activities \"%s\"",
		    count($acts), array_search($chapter_id, amc_get_chapter_map()), wp_json_encode($activity_types)));
  set_transient($cache_key, $acts, $ttl);
  $keys = get_option('amc_cache_keys', array());
  if (!is_array($keys)) {
    amc_debug("keys is not an array?: " . print_r($keys));
    $keys = array();
  }
  $keys[] = $cache_key;
  update_option('amc_cache_keys', $keys);
  return $acts;
}

function amc_cache_flush() {
  $keys = get_option('amc_cache_keys', array());
  if (is_array($keys)) {
    foreach ($keys as $key) {
      delete_transient($key);
    }
  }
  update_option('amc_cache_keys', array());
}
/* Flush cache on deactivation */
register_deactivation_hook( __FILE__, 'amc_cache_flush' );

/* ---- Extract a reasonable amount of initial text from the description obtained from activities.outdoors.org ---- */
function amc_extract_paragraphs(?string $html, int $words) {
  if (!$html or ($words == 0)) return '';
    @$d=new DOMDocument(); @$d->loadHTML($html);
    $out='';
    foreach ($d->getElementsByTagName('p') as $p) {
        if (str_word_count($out) > $words) break;
        $t=trim(preg_replace('/\s+/', ' ', $p->textContent));
        if ($t and strlen($t) > 1) $out.=esc_html($t).'<p>';
    }
    return preg_replace('/[^\x20-\x7E]/','',$out);
}

/* ---- Format the activity information nicely, with activity name (and associated link to activities.outdoors.org), date and desciption ---- */
function amc_generate_html(string $chapter_name, array $atypes, array $acts, int $limit, ?string $keywords, ?string $audience, int $words) {
    $acts=array_slice($acts,0,$limit);
    $r='<th style="text-align: left;">AMC Outdoor Connector activities</th>';
    $r.='<tr><td style="font-size: smaller;">';
    $r.= $chapter_name;
    $r.= !empty($audience) ? ' / ' . $audience : '';
    $header_atype =  (!empty($atypes) ? implode(', ', $atypes) : 'All Activity Types');
    $r.= ' / ' . $header_atype;
    $r.= !empty($keywords) ? ' / ' . $keywords : '';
    $r.='<tr><td><hr><td></tr>';
    foreach($acts as $a){
      if (is_array($a)) {
	amc_debug(print_r($a, true));

        $r.='<tr><td><a href="'  .esc_url(AMC_BASE_URL.'/s/oc-activity/'.$a['Id']).'">'.
	  '<b>'. $a['Activity_Name__c'].'</a></b><br>';
	$r.=  '<div style="font-size: smaller;">' .
	  $a['Start_Concatenation_Formula_Unconverted__c'] . ', ';
	$r .= $a['Start_Location__c']['city'] . ' ' .  $a['Start_Location__c']['state'] . '<br>';
      
	$atype_list = array();
	if (!empty($a['Secondary_Activity_Type__c'])) 
	  $atype_list = explode(';', $a['Secondary_Activity_Type__c']);
	$atype_list[] =  $a['Main_Activity_Sub_Type__c'];
	$atype_list[] = $a['Main_Activity_Type__c'];
	$atype = "";
	foreach($atype_list as $at) {
	  if (! in_array($at, $atypes)) $atype .= $at . ", ";
	}
	if (!empty($atype)) $r .=  substr($atype, 0, -2) . '<br>';
	$r .= (amc_get_chapter_name($a['Account__c']) == $chapter_name) ? "" : amc_get_chapter_name($a['Account__c']) . " - " ;
	$r .= 'Leader: '. $a['OC_Trip_Leaders__r'][0]['Contact__r']['Name']. '<p></div>';
	$r .= '<div style="white-space: normal;"> ' .
	  amc_extract_paragraphs($a['Description__c']??'', $words).
	  '</div></td></tr>';
      } else {
	$r .= '<tr><td>' . $a . '</td></tr>';
      }
    }
    return '<table>'.$r.'</table>';
}



/* Resolve chapter name or raw ID */
function amc_resolve_chapter_id(string $chapter) {
  if (!$chapter) return '';

    // Already an ID
    if (preg_match('/^001[A-Za-z0-9]{12,15}$/', $chapter)) {
        return $chapter;
    }

    // Check for closest match
    $all_chapters = array_keys(amc_get_chapter_map());
    if (!in_array($chapter, $all_chapters)) {
      foreach($all_chapters as $ch) {
	if (stripos($ch, $chapter) !== FALSE) {
	  $chapter = $ch;
	  break;
	}
      }
    }
    
    $map = amc_get_chapter_map();
    return $map[$chapter] ?? '';
}

/* Turn array of activity types into string of form used in shortcode */
function amc_stringify_activities(array $activity_array) {
  if (!is_array($activity_array)) {
      return "";
    }
  $lastKey = array_key_last($activity_array);
  $astring = "";
  foreach ($activity_array as $key => $val ) {
    $astring = $astring . $val ;
    if ($key != $lastKey) {
      $astring = $astring . "|";
    }
  }
  return $astring;
}

/* ======================================================
   Shortcode
   ====================================================== */
function amc_do_activities_shortcode(?string $chapter_name,  array $atypes, int $limit, ?string $keywords, ?string $audience, int $length)
{
  $chapter_name = empty($chapter_name) ? get_option('amc_default_chapter') : $chapter_name;
  if (empty($chapter_name)) $chapter_name = 'All Chapters';
  $chapter_id = amc_resolve_chapter_id($chapter_name);
  if (!$chapter_id) {
    return '<p style="color: red;">amc_activities: Invalid Chapter "' . $chapter_name . '"</p>' ;
  }
  /*
  amc_debug(sprintf('amc_do_activities_shortcode: Chapter: "%s", Activities: "%s", limit: "%s", keywords: "%s" audience: "%s"',
		    $chapter_name, json_encode($atypes), $limit, $keywords, $audience));
  */
  $all_activities = amc_get_activity_list();
  if (!is_array($atypes) ) {
    amc_debug("atypes not an array???: -->" . $atypes  . "<--");
    $atypes = array();
  }
  if (!empty($atypes)) {
    foreach ($atypes as $a) {
      if (!empty($a) and !in_array($a, $all_activities)) {
	return '<p style="color:red;">amc_activities: Unknown activity type "' . $a . '"</p>';
      }
    }
  }
  $atypes = empty($atypes) ? get_option('amc_default_activity_types') : $atypes;
  $limit = empty($limit) ? intval(get_option('amc_default_limit')) : intval($limit);

  /*
  amc_debug(sprintf(
        'do_shortcode: Chapter: "%s", ID: "%s", Activities: "%s", Limit: %d, keywords: "%s"',
        $chapter_name,
	$chapter_id,
	json_encode($atypes),
        $limit, $keywords));
  */

  $acts=amc_fetch_events_cached($chapter_id,$atypes, $keywords, $audience);
  return amc_generate_html($chapter_name, $atypes, $acts, $limit, $keywords, $audience, $length);
}

add_shortcode('amc_activities', function ($atts) {
    $atts=shortcode_atts([
			  'chapter'=> '',
			  'activities'=> '',
			  'events'=> '',
			  'keywords'=> '',
			  'audience' => '',
			  'length' => ''
			  ],$atts);
    /*amc_debug("add_shortcode: " . json_encode($atts)); */
    if (empty($atts['activities'])) {
      $activities = get_option('amc_default_activity_types', array());
    } else {
      /* Allow for shorthand activities */
      $all_activities = amc_get_activity_list();
      $alist = explode('|', html_entity_decode($atts['activities']), 5);
      $activities = array();
      foreach ($alist as $a) {
	$activities[] = array_values(array_filter($all_activities, function ($element) use ($a) {
	      return strpos(strtolower($element), strtolower($a)) !== false;
	      }));
      }
      $activities = array_merge([], ...$activities);
    }
    $limit = intval(empty($atts['events']) ?  get_option('amc_default_limit') : $atts['events']);
    $length = intval(empty($atts['length']) ? 30 : $atts['length']);
    return(amc_do_activities_shortcode($atts['chapter'], $activities, $limit,
				       html_entity_decode($atts['keywords']), html_entity_decode($atts['audience']), $length));    
});


/* For Gutenberg dropdown */
function amc_get_chapter_options() {
    $out = [];
    foreach (amc_get_chapter_map() as $name => $id) {
        $out[] = [
            'label' => $name,
            'value' => $name   // store name, not ID
        ];
    }
    return $out;
}
function amc_get_activity_options() {
  $out = [];
  foreach(amc_get_activity_list() as $name ) {
    $out[] = [
	      'label' => $name,
	      'value' => $name
	      ];
  }
  return $out;
}
function amc_get_audience_options() {
  $out = [];
  foreach(amc_get_audience_list() as $name ) {
    $out[] = [
	      'label' => $name,
	      'value' => $name
	      ];
  }
  return $out;
}


/* ======================================================
   Gutenberg Block
   ====================================================== */
add_action('init', function () {

    wp_register_script(
        'amc-activities-block',
        plugins_url('block.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
        '1.3.0',
        true
    );

   wp_localize_script(
        'amc-activities-block',
        'AMC_BLOCK_DATA',
        [
	 'chapters' => amc_get_chapter_options(),
	 'activities' => amc_get_activity_options(),
	 'default_chapter' => get_option('amc_default_chapter'),
	 'default_activities' => get_option('amc_default_activity_types'),
	 'default_limit' => get_option('amc_default_limit'),
	 'keywords' => '',
	 'audiences' => amc_get_audience_options()
        ]
    );
 
    register_block_type('amc/activities',
			[
			 'description'   => 'Retrieve a list of activities from activities.outdoors.org',
			 'api_version'   => 1,				   
			 'editor_script' => 'amc-activities-block',
			 'render_callback' => function ($attrs) {

			   wp_localize_script(
					      'amc-activities-block',
					      'AMC_BLOCK_DATA',
					      [
					       'chapters' => amc_get_chapter_options(),
					       'activities' => amc_get_activity_options(),
					       'default_chapter' => get_option('amc_default_chapter'),
					       'default_activities' => get_option('amc_default_activity_types'),
					       'default_limit' => get_option('amc_default_limit'),
					       'keywords' => '',
					       'audiences' => amc_get_audience_options()
					       ]
					      );
			   /* amc_debug('render_callback: ' . json_encode($attrs));*/
			   
			   if (! isset($attrs['chapter'],$attrs['activityTypes'],$attrs['events'])) {
			     return('<p style="color: red;">No chapter, activity or event count?<br>' .
				    json_encode($attrs) . '</p>');
			   }

    			   $chapter = $attrs['chapter'];
			   $act = $attrs['activityTypes'];
			   $limit = $attrs['events'];
			   $keywords = isset($attrs['keywords']) ? $attrs['keywords'] : "";
			   $audiences = isset($attrs['audience']) ?$attrs['audience'] : "";
			   $length = isset($attrs['length']) ? intval($attrs['length']) : 30;

			   return amc_do_activities_shortcode($chapter, $act, intval($limit), $keywords, $audiences, $length);
			 }
			 ]);
  });

/* ---- ADMIN ---- */
add_action('admin_init', function () {
    register_setting('amc_activities_settings', 'amc_default_chapter',
		     [
		      'type' => 'string',
		      'sanitize_callback' => 'sanitize_text_field',
		      'default' => 'AMC_DEFAULT_CHAPTER'
		      ]
		     );
    register_setting('amc_activities_settings','amc_default_activity_types',
		     [
		      'type' => 'array',
		      'default' => ["Garbage"]
		      ]
		     );
    register_setting('amc_activities_settings', 'amc_default_limit',
		     [ 'type' => 'integer', 'default' => 25 ]
		     );
    register_setting('amc_activities_settings', 'amc_cache_ttl',
		     [ 'type' => 'integer', 'default' => AMC_CACHE_TTL_DEFAULT ]
		     );
    register_setting('amc_activities_settings', 'amc_debug_mode',
		     [ 'type' => 'boolean', 'default' => false ]
		     );
    register_setting('amc_activities_settings', 'amc_cache_keys',
		     [ 'type' => 'array', 'default' => array() ]
		     );

});

add_action('admin_menu', function () {
    add_options_page(
        'AMC Activities',
        'AMC Activities',
        'manage_options',
        'amc-activities',
        'amc_render_settings_page'
    );
});

function amc_render_settings_page() {
    $chapters = amc_get_chapter_map();
    $activities = amc_get_activity_list();
    $current_chapter = get_option('amc_default_chapter', "");
    $current_activities = get_option('amc_default_activity_types', array());  /* Array, automatically serialized */
    $debug_mode = get_option('amc_debug_mode', false);

    if (! is_array($current_activities)) {
      $current_activities = array();
    }
    ?>
    <div class="wrap">
       <h1>AMC Activities Settings</h1>
       <p>
       Defaults when no chapter, activity type or limit is used in a shortcode or block
       <p>

       <?php
       /*
            echo "<pre>";
            echo "amc_default_chapter: " . $current_chapter . "<br>";
	    echo "amc_default_activity_types: ";
	    foreach ($current_activities as $value) {
	      echo $value . "|";
	    }
	    echo "<br>amc_debug_mode: " . print_r($debug_mode, true) ;
	    $keys = get_option('amc_cache_keys', array());
	    if (!is_array($keys)) {
	      $keys = array();
	    }
	    echo "<br>Cache keys: ";
	    foreach ($keys as $key) {
	      echo $key . ", ";
	    }
	    echo "<br></pre>";
       */
	   ?>
       <form method="post" action="options.php">
          <?php settings_fields('amc_activities_settings'); ?>

          <table class="form-table" role="presentation">
              <tr>
                 <th scope="row">
                     <label for="amc_default_chapter">
                         Default Chapter
                     </label>
                 </th>
                 <td>
                     <select name="amc_default_chapter" id="amc_default_chapter">
                         <option value="">— Select a chapter —</option>
                         <?php foreach ($chapters as $name => $id): ?>
                             <option value="<?php echo esc_attr($name); ?>"
                                 <?php selected($current_chapter, $name); ?>>
                                 <?php echo esc_html($name); ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
		 </td>
	        </tr>
		<tr>
		<th scope="row">
		      <label for="amc_default_activity_types">
			     Activity Types
		      </label>
		</th>
			       
		<td>
			<select name="amc_default_activity_types[]" id="amc_default_activity_types" multiple>
			       <option value="">- Select one or more activities -</option>
			       <?php
			       foreach ($activities as $value) {
			         echo '<option value="' . esc_attr( $value ) . '" ' . selected( true, in_array( $value, $current_activities ), false ) . '>' . esc_html ( $value) . '</option>';
			       }
                               ?>
                        </select>
                  </td>
	        </tr>
   	        <tr>
		<th scope="row">
		      <label for="amc_default_limit">
			      # of activities to display </label>
				</th>
				<td><input type="number" name="amc_default_limit" value="<?php echo esc_attr(get_option('amc_default_limit',10)); ?>"></td>
		</tr>
		</table>
		<p>Advanced options<p>
            Cache TTL <input type="number" name="amc_cache_ttl" value="<?php echo esc_attr(get_option('amc_cache_ttl',AMC_CACHE_TTL_DEFAULT)); ?>"><br>
            Debug <input type="checkbox" name="amc_debug_mode" value="1" <?php checked(get_option('amc_debug_mode'),1); ?>><br>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
     $action_url = admin_url( 'admin-post.php?action=amc_cache_flush&_wpnonce=' . wp_create_nonce( 'amc-cache-flush-nonce' ) );
     echo '<a href="' . esc_url( $action_url ) . '" class="button button-primary">Clear Cache</a>';

}

add_action( 'admin_post_amc_cache_flush', 'amc_cache_flush_request' );

function amc_cache_flush_request() {
    // Verify nonce for security
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'amc-cache-flush-nonce' ) ) {
        wp_die( 'Security check failed' );
    }

    // --- Your custom PHP function logic goes here ---
    // e.g., update database, generate a file, etc.
    // Make sure the user has the correct capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.' );
    }
    amc_cache_flush();
    // After the action, redirect the user back to the admin page
    wp_redirect( admin_url( 'options-general.php?page=amc-activities' ) ); // Redirect to posts list (change as needed)
    exit;
}
