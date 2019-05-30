<?php
use WilokeListingTools\MetaBoxes\Listing as ListingMetaBox;
use WilokeListingTools\Framework\Helpers\SetSettings;

$aSocialNetworks = array(
	'facebook', 'twitter', 'google-plus', 'tumblr', 'vk', 'odnoklassniki', 'youtube', 'vimeo', 'rutube', 'dribbble', 'instagram', 'flickr', 'pinterest', 'medium', 'tripadvisor', 'wikipedia', 'stumbleupon', 'livejournal', 'linkedin', 'skype', 'bloglovin', 'whatsapp', 'soundcloud'
);

/*
 * Plugin Name: Wilcity Bulk Import
 * Plugin URI: https://wilcity.com
 * Author: Wiloke
 * Author URI: https://wilcity.com
 * Description: Migrating from another theme to Wilcity
 * Version: 1.0
 */

include plugin_dir_path(__FILE__) . 'rapid-addon.php';

function wilcityCleanImageFileName($fileName){
	$aFileExtension = array('jpg', 'jpeg', 'png', 'gif', 'svg');
	foreach ($aFileExtension as $extension){
		if ( strpos($fileName, $extension) !== false ){
			$aParse = explode('.'.$extension, $fileName);
			return $aParse[0] . '.' . $extension;
		}
	}

	return $fileName;
}

if ( !function_exists('wilcityMigrationInsertImage') ){
	function wilcityMigrationInsertImage($imgSrc){
		$wp_upload_dir = wp_upload_dir();
		$filename = basename($imgSrc);
		$filename = wilcityCleanImageFileName($filename);
		$filetype = wp_check_filetype( $filename, null );

		if ( is_file($wp_upload_dir['path'] . '/' . $filename) ){
			global $wpdb;
			$postTitle = preg_replace( '/\.[^.]+$/', '', $filename );

			$postID = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_title=%s and post_mime_type=%s",
					$postTitle, $filetype['type']
				)
			);

			if ( wp_get_attachment_image_url($postID) ){
				return array(
					'id'    => $postID,
					'url'   => $wp_upload_dir['url'] . '/' . $filename
				);
			}
		}

		$ch = curl_init ($imgSrc);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
		$raw=curl_exec($ch);
		curl_close ($ch);
		$fp = fopen($wp_upload_dir['path'] . '/' . $filename,'x');
		$writeStatus = fwrite($fp, $raw);
		fclose($fp);

		if ( $writeStatus === false ){
			return false;
		}

		// Get the path to the upload directory.
		// Prepare an array of post data for the attachment.
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);
		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $wp_upload_dir['path'] . '/' . $filename);

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$imagenew = get_post( $attach_id );
		if ( !empty($imagenew) ){
			return array(
				'id' => $attach_id,
				'url' => $wp_upload_dir['url'] . '/' . $filename
			);
		}
		$fullsizepath = get_attached_file( $imagenew->ID );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return array(
			'id'    => $attach_id,
			'url'   => $wp_upload_dir['url'] . '/' . $filename
		);
	}
}

function wilcityTimezoneToString($time){
	if ( strpos($time,'GMT') !== false ){
		return $time;
	}

	$time = str_replace(array('UTC', '+'), array('', ''), $time);

	if ( empty($time) ){
		return 'UTC';
	}

	$utc_offset = intval($time*3600);

	if ( $timezone = timezone_name_from_abbr( '', $utc_offset ) ) {
		return $timezone;
	}

	// last try, guess timezone string manually
	foreach ( timezone_abbreviations_list() as $abbr ) {
		foreach ( $abbr as $city ) {
			if ( (bool) date( 'I' ) === (bool) $city['dst'] && $city['timezone_id'] && intval( $city['offset'] ) === $utc_offset ) {
				return $city['timezone_id'];
			}
		}
	}

	// fallback to UTC
	return 'UTC';
}


$wilcityAddon = new RapidAddon('Migrating To Wilcity', 'wilcity_migrating_to_wilcity');

//$wilcityAddon->add_field('wilcity_migrate_from', 'Migrate From', 'text');

$wilcityAddon->add_field('wilcity_logo', 'Logo', 'text');
$wilcityAddon->add_field('wilcity_tagline', 'Tagline', 'text');
$wilcityAddon->add_field('wilcity_toggle_business_status', 'Toggle Business Status', 'text');
$wilcityAddon->add_field('wilcity_business_hours', 'Business Hours (If you migrate from Listify, please put it here)', 'text');
$wilcityAddon->add_field('wilcity_business_normal_hours', 'Business Hours Normal Format Monday 06:00 AM - 08:00 PM', 'text');
$wilcityAddon->add_field('wilcity_listing_claim', 'Listing Claim Status', 'text');

$wilcityAddon->add_field('wilcity_price_range', 'Price Segment (Cheap / Moderate / Expensive / Ultra Hight)', 'text');
$wilcityAddon->add_field('wilcity_price_range_minimum', 'Price Range - Minimum Price', 'text');
$wilcityAddon->add_field('wilcity_price_range_maximum', 'Price Range - Maximum Price', 'text');
$wilcityAddon->add_field('wilcity_single_price', 'Single Price', 'text');
$wilcityAddon->add_field('wilcity_featured_image', 'Featured Image URL', 'text');
$wilcityAddon->add_field('wilcity_cover_image', 'Cover Image URL', 'text');
$wilcityAddon->add_field('wilcity_gallery', 'Gallery Image Urls', 'text');
$wilcityAddon->add_field('wilcity_timezone', 'Timezone', 'text');
$wilcityAddon->add_field('wilcity_email', 'Email', 'text');
$wilcityAddon->add_field('wilcity_phone', 'Phone', 'text');
$wilcityAddon->add_field('wilcity_website', 'Website', 'text');
$wilcityAddon->add_field('wilcity_address', 'Address', 'text');
$wilcityAddon->add_field('wilcity_lat', 'Latitude', 'text');
$wilcityAddon->add_field('wilcity_lng', 'Longitude', 'text');
$wilcityAddon->add_field('wilcity_lat_lng', 'Latitude & Longitude (Some themes do not separated Lat and Lng, so you can use this field)', 'text');

$wilcityAddon->add_field('wilcity_location', 'Wilcity Location', 'text');

$wilcityAddon->add_field('wilcity_video_url', 'Video URL', 'text');
$wilcityAddon->add_field('wilcity_expiration', 'Listing Expiry Date', 'text');
$wilcityAddon->add_field('wilcity_ltpro_options', 'Listing Pro Options', 'text');

//$wilcityAddon->add_field('wilcity_event_timezone', 'Event Timezone', 'text');
$wilcityAddon->add_field('wilcity_event_frequency', 'Event Frequency (occurs_once/daily/weekly). Leave empty mean occurs_once', 'text');
$wilcityAddon->add_field('wilcity_event_belongs_to', 'Event Parent', 'text');
$wilcityAddon->add_field('wilcity_event_specify_day', 'Specify day', 'text');
$wilcityAddon->add_field('wilcity_event_start_at', 'Event Opening At (EG: 12:00:00 AM)', 'text');
$wilcityAddon->add_field('wilcity_event_start_on', 'Event Start On (EG: 2018/11/30)', 'text');
$wilcityAddon->add_field('wilcity_event_end_at', 'Event Close At (EG: 12:00:00 AM)', 'text');
$wilcityAddon->add_field('wilcity_event_end_on', 'Event Close On (EG: 2018/11/30)', 'text');


$aDayOfWeeks = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
$aDayOfWeeksShort = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');

foreach ($aSocialNetworks as $socialNetwork){
	$wilcityAddon->add_field('wilcity_social_media_'.$socialNetwork, ucfirst($socialNetwork) .' URL', 'text');
}

function wilokeBuildBH($time){
	return date('H:i:s', strtotime($time));
}

function wilcityDetermineDay($rawDay, $aData=array()){
	global $aDayOfWeeks, $aDayOfWeeksShort;

	foreach ($aDayOfWeeks as $key => $day ){
		if ( strpos($rawDay, $day) !== false || strpos($rawDay, $aDayOfWeeksShort[$key]) !== false ){
			unset($aDayOfWeeks[$key]);
			unset($aDayOfWeeksShort[$key]);

			$bh = trim(str_replace($day, '', $rawDay));
			$rawDay = strtolower($rawDay);
			if ( strpos($rawDay, 'close') !== false ){
				return array(
					'info' => array(
						'start' => '',
						'close' => '',
						'isClose' => 'yes'
					),
					'day' => $day
				);
			}else{
				$aParsed = explode(apply_filters('wilcity-bulk-import/explode-hour-clue', '-'), $bh);

				return array(
					'info' => array(
						'start' => wilokeBuildBH(trim($aParsed[0])),
						'close' => wilokeBuildBH(trim($aParsed[1])),
					),
					'day' => $day
				);
			}
		}else{
			return array(
				'info' => array(
					'start' => '',
					'close' => '',
					'isClose' => 'yes'
				),
				'day' => $day
			);
		}
	}

	return array(
		'day' => array_shift($aDayOfWeeks),
		'info' => false
	);
}

function wilcityParseNormalBusinessHour($aParseBusinessHours, $aData=array()){
	global $aDayOfWeeks, $aDayOfWeeksShort;

	$aBusinessHours = array();
	foreach ($aParseBusinessHours as $rawVal){
		$aParsed = wilcityDetermineDay($rawVal, $aData);
		$aBusinessHours[$aParsed['day']] = $aParsed['info'];
	}

	$aDayOfWeeks = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
	$aDayOfWeeksShort = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');

	return $aBusinessHours;
}

function wilcity_migrating_to_wilcity($postID, $aData, $importOptions, $aListing){
	global $wilcityAddon, $aSocialNetworks;
	$aThemeOptions = Wiloke::getThemeOptions(true);

	$aFields = array(
		'wilcity_logo',
		'wilcity_toggle_business_status',
		'wilcity_business_hours',
		'wilcity_business_normal_hours',
		'wilcity_listing_claim',
		'wilcity_price_range',
		'wilcity_price_range_minimum',
		'wilcity_price_range_maximum',
		'wilcity_single_price',
		'wilcity_timezone',
		'wilcity_email',
		'wilcity_phone',
		'wilcity_website',
		'wilcity_lat',
		'wilcity_lng',
		'wilcity_lat_lng',
		'wilcity_address',
		'wilcity_location',
		'wilcity_video_url',
		'wilcity_featured_image',
		'wilcity_cover_image',
		'wilcity_gallery',
		'wilcity_expiration',
		'wilcity_ltpro_options',
		'wilcity_event_frequency',
		'wilcity_event_specify_day',
		'wilcity_event_start_at',
		'wilcity_event_start_on',
		'wilcity_event_belongs_to',
		'wilcity_event_end_at',
		'wilcity_event_end_on'
	);

	foreach ($aSocialNetworks as $socialNetwork){
		$aFields[] = 'wilcity_social_media_'.$socialNetwork;
	}

	$aDaysOfWeeks = wilokeListingToolsRepository()->get('general:aDayOfWeek');
	$aDaysOfWeekKeys = array_keys($aDaysOfWeeks);

	$aBusinessHours = array();
	$aAddress = array();

	$aEventData = array();
	foreach ( $aFields as $field ) {
		if ( empty( $aListing['ID'] ) || $wilcityAddon->can_update_meta( $field, $importOptions ) ) {
			$data = $aData[$field];
			$aParseData = maybe_unserialize($data);
			$aPriceSettings = null;

			switch ($field){
				case 'wilcity_event_frequency':
					$aEventData['frequency'] = empty($aData[$field]) ? 'occurs_once' : trim($aData[$field]);
					break;
				case 'wilcity_event_belongs_to':
					$aEventData['parentID'] = $aData[$field];
					break;
				case 'wilcity_event_start_at':
					$aEventData['start_at'] = trim($aData[$field]);
					break;
				case 'wilcity_event_start_on':
					$aEventData['start_on'] = trim($aData[$field]);
					break;
				case 'wilcity_event_end_at':
					$aEventData['end_at'] = trim($aData[$field]);
					break;
				case 'wilcity_event_specify_day':
					$aEventData['specify_day'] = empty($aData[$field]) ? 'always' : trim($aData[$field]);
					break;
				case 'wilcity_event_end_on':
					$aEventData['end_on'] = trim($aData[$field]);

					if ( !empty($aEventData['start_at']) ){
						$startsOn = strtotime($aEventData['start_on'] . ' ' . $aEventData['start_at']);
					}else{
						$startsOn = strtotime($aEventData['start_on']);
					}

					if ( !empty($aEventData['end_at']) ){
						$endsOn = strtotime($aEventData['end_on'] . ' ' . $aEventData['end_at']);
					}else{
						$endsOn = strtotime($aEventData['end_on']);
					}

					if ( empty($timezone) ){
						$timezone = get_option('timezone_string');
					}

					$startsOn = \WilokeListingTools\Framework\Helpers\Time::mysqlDateTime($startsOn);
					$endsOn = \WilokeListingTools\Framework\Helpers\Time::mysqlDateTime($endsOn);

					$aPrepares = array(
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s'
					);

					$aUpdateEvent['objectID']         = $postID;
					$aUpdateEvent['parentID']         = $aEventData['parentID'];
					$aUpdateEvent['frequency']        = $aEventData['frequency'];
					$aUpdateEvent['address']          = $aAddress['address'];
					$aUpdateEvent['lat']              = sanitize_text_field($aAddress['lat']);
					$aUpdateEvent['lng']              = sanitize_text_field($aAddress['lng']);
					$aUpdateEvent['starts']           = $startsOn;
					$aUpdateEvent['endsOn']           = $endsOn;
					$aUpdateEvent['openingAt']        = '';
					$aUpdateEvent['closedAt']         = '';

					if ( $aEventData['frequency'] == 'weekly' ){
						$aUpdateEvent['specifyDays'] = $aEventData['specify_day'];
						$aPrepares[] = '%s';
					}

					$status = \WilokeListingTools\Models\EventModel::updateEventData($postID, array(
						'values'    => $aUpdateEvent,
						'prepares'  => $aPrepares
					));

					break;
				case 'wilcity_logo':
					$aLogo = wilcityMigrationInsertImage($aParseData);

					if ( $aLogo ){
						SetSettings::setPostMeta($postID, 'logo', $aLogo['url']);
						SetSettings::setPostMeta($postID, 'logo_id', $aLogo['id']);
					}
					break;
				case 'wilcity_tagline':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'tagline', aParseData);
					}
					break;
				case 'wilcity_toggle_business_status':
					if ( !empty($aParseData) ){
						if ( $aParseData == 'enable' ){
							$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						}else if ( $aParseData == 'disable' ){
							$aBusinessHours['hourMode'] = 'no_hours_available';
						}
					}
					break;
				case 'wilcity_business_hours':
					if ( empty($aParseData) ){
						$aBusinessHours['hourMode'] = 'no_hours_available';
					}else{
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						$aBusinessHours['businessHours'] = array();
						$order = 0;
						foreach ($aParseData as $aItem){
							$aDay = array();
							if ( isset($aItem['start']) ){
								if ( $aItem['start'] == 'Closed' || $aItem['end'] == 'Closed' ){
									$aDay['isOpen'] = 'no';
									$aDay['operating_times']['firstOpenHour'] = '';
									$aDay['operating_times']['firstCloseHour'] = '';
								}else{
									$aDay['isOpen'] = 'yes';
									$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aDay['start']);
									$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aDay['end']);
								}
							}else if ( is_array($aItem) ) {
								if ( isset($aItem[0]['open']) ){
									if ( $aItem[0]['open'] == 'Closed' || $aItem[0]['close'] == 'Closed' ){
										$aDay['isOpen'] = 'no';
										$aDay['operating_times']['firstOpenHour'] = '';
										$aDay['operating_times']['firstCloseHour'] = '';
									}else{
										$aDay['isOpen'] = 'yes';
										$aDay['operating_times']['firstOpenHour'] =  wilokeBuildBH($aItem[0]['open']);
										$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem[0]['close']);

										if ( isset($aItem[1]['open']) && isset($aItem[1]['close']) && $aItem[1]['open'] != 'Closed' && $aItem[1]['close'] != 'Closed' ){
											$aDay['operating_times']['secondOpenHour']  = wilokeBuildBH($aItem[1]['open']);
											$aDay['operating_times']['secondCloseHour'] = wilokeBuildBH($aItem[1]['close']);
										}
									}
								}
							}
							$aBusinessHours['businessHours'][$aDaysOfWeekKeys[$order]] = $aDay;
							$order++;
						}
						ListingMetaBox::saveBusinessHours($postID, $aBusinessHours);
					}
					break;
				case 'wilcity_business_normal_hours':
					if ( empty($aData['wilcity_business_hours']) ){
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						$aBusinessHours['businessHours'] = array();
						$aRawBH = explode(',', $aData['wilcity_business_normal_hours']);
						$aParsedBusinessHours = wilcityParseNormalBusinessHour($aRawBH, $aData);
						$order = 0;
						foreach( $aParsedBusinessHours as $dayOfWeek => $aBHInfo ){
							if ( !$aBusinessHours ){
								$aDay['isOpen'] = 'no';
								$aDay['operating_times']['firstOpenHour'] = '';
								$aDay['operating_times']['firstCloseHour'] = '';
							}else{
								$aDay['isOpen'] = isset($aBHInfo['isClose']) ? 'no' :  'yes';
								$aDay['operating_times']['firstOpenHour'] = $aBHInfo['start'];
								$aDay['operating_times']['firstCloseHour'] = $aBHInfo['close'];
							}

							$aBusinessHours['businessHours'][$aDaysOfWeekKeys[$order]] = $aDay;
							$order++;
						}

						ListingMetaBox::saveBusinessHours($postID, $aBusinessHours);
						$aBusinessHours = array();
					}
					break;
				case 'wilcity_listing_claim':
					if ( $aParseData == 1 || $aParseData == 'claimed' ){
						SetSettings::setPostMeta($postID, 'claim_status', 'claimed');
					}else{
						SetSettings::setPostMeta($postID, 'claim_status', 'not_claim');
					}
					break;
				case 'wilcity_price_range':
					$aConvertPriceStatus = array(
						'notsay'         => '',
						'inexpensive'    => 'cheap',
						'moderate'       => 'moderate',
						'pricey'         => 'expensive',
						'ultra_high_end' => 'ultra_high',
					);
					if ( isset($aConvertPriceStatus[$field]) ){
						$priceRange = $aConvertPriceStatus[$field];
					}else{
						$priceRange = $aParseData;
					}

					SetSettings::setPostMeta($postID, 'price_range', $priceRange);
					break;
				case 'wilcity_price_range_minimum':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'minimum_price', $aParseData);
					}
					break;
				case 'wilcity_price_range_maximum':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'maximum_price', $aParseData);
					}
					break;
				case 'wilcity_single_price':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'single_price', $aParseData);
					}
					break;
				case 'wilcity_timezone':
					if ( !empty($aParseData) ){
						$timezone = wilcityTimezoneToString($aParseData);
						SetSettings::setPostMeta($postID, 'timezone', $timezone);
					}
					break;
				case 'wilcity_email':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'email', $aParseData);
					}
					break;
				case 'wilcity_phone':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'phone', $aParseData);
					}
					break;
				case 'wilcity_website':
					if ( !empty($aParseData) ){
						SetSettings::setPostMeta($postID, 'website', $aParseData);
					}
					break;
				case 'wilcity_lat':
					$aAddress['lat'] = $aParseData;
					break;
				case 'wilcity_lng':
					$aAddress['lng'] = $aParseData;
					if ( !empty($aAddress['lat']) && !empty($aAddress['lng']) ){
						if ( empty($aData['wilcity_address'])  ){
							$geocodeFromLatLong = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?latlng='.trim($aAddress['lat']).','.trim($aAddress['lng']).'&key='.$aThemeOptions['general_google_api']);

							$oOutput = json_decode($geocodeFromLatLong);
							if ( $oOutput->status == 'OK' ){
								$aAddress['address'] = $oOutput->results[1]->formatted_address;
							}
						}

						if ( !empty($aAddress['address']) ){
							//ListingMetaBox::saveData($postID, $aAddress);
						}
					}
					break;
				case 'wilcity_address':
					$aAddress['address'] = $aParseData;
					if ( ( empty($aAddress['lat']) && empty($aAddress['lng']) ) && empty($aAddress['wilcity_lat_lng']) ){
						if ( !empty($aAddress['address']) ){
							$geocode = file_get_contents("https://maps.google.com/maps/api/geocode/json?address=".urlencode($aAddress['address'])."&key=".trim($aThemeOptions['general_google_api']));
							$oGeocode = json_decode($geocode);
							if ( $oGeocode->status == 'OK' ){
								$aAddress['lat'] = $oGeocode->results[0]->geometry->location->lat;
								$aAddress['lng'] = $oGeocode->results[0]->geometry->location->lng;
								//ListingMetaBox::saveData($postID, $aAddress);
							}
						}
					}
					break;
				case 'wilcity_ltpro_options':
					if ( !empty($aParseData) ){
						if ( isset($aParseData['tagline_text']) ){
							SetSettings::setPostMeta($postID, 'tagline', $aParseData['tagline_text']);
						}

						if (!empty($aParseData['gAddress']) ){
							$aAddress['address'] = $aParseData['gAddress'];
							$aAddress['lat'] = $aParseData['latitude'];
							$aAddress['lng'] = $aParseData['longitude'];
							//ListingMetaBox::saveData($postID, $aAddress);
						}

						if ( !empty($aParseData['phone']) ){
							SetSettings::setPostMeta($postID, 'phone', $aParseData['phone']);
						}

						if ( !empty($aParseData['email']) ){
							SetSettings::setPostMeta($postID, 'email', $aParseData['email']);
						}

						if ( !empty($aParseData['website']) ){
							SetSettings::setPostMeta($postID, 'website', $aParseData['website']);
						}

						foreach ($aSocialNetworks as $social){
							if ( $social == 'google-plus' ){
								$socialKey = 'google_plus';
							}else{
								$socialKey = $social;
							}

							$aSocialUpdated = \WilokeListingTools\Framework\Helpers\GetSettings::getSocialNetworks($postID);
							$aSocialUpdated[$social] = $aParseData[$socialKey];
							SetSettings::setPostMeta($postID, 'social_networks', $aSocialUpdated);
						}

						if ( !empty($aParseData['video']) ){
							$aVideos = array();
							if ( is_array($aParseData) ){
								foreach ($aParseData as $order => $url){
									$aVideos[$order]['src'] = $url;
									$aVideos[$order]['thumbnail'] = '';
								}
							}else{
								$aVideosToArrays = explode(',', $aParseData);
								foreach ($aVideosToArrays as $order => $url){
									$aVideos[$order]['src'] = $url;
									$aVideos[$order]['thumbnail'] = '';
								}
							}
							SetSettings::setPostMeta($postID, 'video_srcs', $aVideos);
						}

						SetSettings::setPostMeta($postID, 'price_range', $aParseData['price_range']);
						SetSettings::setPostMeta($postID, 'minimum_price', $aParseData['list_price']);
						SetSettings::setPostMeta($postID, 'maximum_price', $aParseData['list_price_to']);

						if ( !empty($aParseData['business_logo']) ){
							$aLogo = wilcityMigrationInsertImage($aParseData['business_logo']);
							if ( $aLogo ){
								SetSettings::setPostMeta($postID, 'logo', $aLogo['url']);
								SetSettings::setPostMeta($postID, 'logo_id', $aLogo['id']);
							}
						}

						if ( !empty($aParseData['business_hours']) ){
							$aBusinessHours['hourMode'] = 'open_for_selected_hours';
							$aBusinessHours['businessHours'] = array();
							foreach ($aParseData['business_hours'] as $dayOfWeek => $aItem){
								$dayOfWeek = strtolower($dayOfWeek);
								$aDay = array();
								if ( count($aItem['open']) > 1 ){
									$aDay['isOpen'] = 'yes';
									$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aItem['open'][0]);
									$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem['close'][0]);
								}else{
									$aDay['isOpen'] = 'yes';
									$aDay['operating_times']['firstOpenHour'] =  wilokeBuildBH($aItem['open'][0]);
									$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem['close'][0]);

									if ( isset($aItem[1]['open']) ){
										$aDay['operating_times']['secondOpenHour']  = wilokeBuildBH($aItem['open'][1]);
										$aDay['operating_times']['secondCloseHour'] = wilokeBuildBH($aItem['close'][1]);
									}
								}
								$aBusinessHours['businessHours'][$dayOfWeek] = $aDay;
							}
							ListingMetaBox::saveBusinessHours($postID, $aBusinessHours);
						}else{
							$aBusinessHours['hourMode'] = 'no_hours_available';
						}

					}
					break;
				case 'wilcity_lat_lng':
					if ( !empty($aParseData) && ( empty($aAddress['address']) || empty($aAddress['lat']) || empty($aAddress['lng']) ) ){
						if ( is_array($aParseData) ){
							if ( isset($aParseData['lat']) ){
								$aAddress['lat'] = $aParseData['lat'];
								$aAddress['lat'] = $aParseData['lng'];
							}else if ( isset($aParseData[0]) ){
								$aAddress['lat'] = $aParseData[0];
								$aAddress['lat'] = $aParseData[1];
							}
						}else{
							$aLatLng = explode(',', $aParseData);
							$aAddress['lat'] = $aLatLng[0];
							$aAddress['lat'] = $aLatLng[1];
						}

						// if ( !empty($aAddress['address']) && !empty($aAddress['lat']) && !empty($aAddress['lat']) ){
						// 	ListingMetaBox::saveData($postID, $aAddress);
						// }
					}
					break;
				case 'wilcity_location':
					if ( !empty($aParseData) ){
						if ( !empty($aParseData['lat']) && !empty($aParseData['lng']) && !empty($aParseData['address']) ){
							ListingMetaBox::saveData($postID, $aParseData);
							SetSettings::setPostMeta($postID, 'location', $aParseData);
						}
					}
					break;
				case 'wilcity_video_url':
					if ( !empty($aParseData) ){
						$aVideos = array();
						if ( is_array($aParseData) ){
							foreach ($aParseData as $order => $url){
								$aVideos[$order]['src'] = $url;
								$aVideos[$order]['thumbnail'] = '';
							}
						}else{
							$aVideosToArrays = explode(',', $aParseData);
							foreach ($aVideosToArrays as $order => $url){
								$aVideos[$order]['src'] = $url;
								$aVideos[$order]['thumbnail'] = '';
							}
						}
						SetSettings::setPostMeta($postID, 'video_srcs', $aVideos);
					}
					break;
				case 'wilcity_featured_image':
					if ( !empty($aParseData) ){
						$aAttachment = wilcityMigrationInsertImage($aParseData);

						if ( $aAttachment ){
							set_post_thumbnail($postID, $aAttachment['id']);
						}
					}
					break;
				case 'wilcity_cover_image':
					if ( !empty($aParseData) ){
						$aAttachment = wilcityMigrationInsertImage($aParseData);
						if ( $aAttachment ){
							SetSettings::setPostMeta($postID, 'cover_image', $aAttachment['url']);
							SetSettings::setPostMeta($postID, 'cover_image_id', $aAttachment['id']);
						}
					}
					break;
				case 'wilcity_gallery':
					if ( !empty($aParseData) ){
						$aDownloadedGallery = array();
						if ( is_array($aParseData) ){
							foreach ($aParseData as $imgSrc){
								$aAttachment = wilcityMigrationInsertImage($imgSrc);
								if ( $aAttachment ){
									$aDownloadedGallery[$aAttachment['id']] = $aAttachment['url'];
								}
							}
						}else{
							$aGalleryToArray = explode(',', $aParseData);
							foreach ($aGalleryToArray as $imgSrc){
								$aAttachment = wilcityMigrationInsertImage($imgSrc);
								if ( $aAttachment ){
									$aDownloadedGallery[$aAttachment['id']] = $aAttachment['url'];
								}
							}
						}

						if ( !empty($aDownloadedGallery) ){
							SetSettings::setPostMeta($postID, 'gallery', $aDownloadedGallery);
						}
					}
					break;
				case 'wilcity_expiration':
					if ( !empty($aParseData) ){
						$aParseData = strtotime($aParseData);
						SetSettings::setPostMeta($postID, 'post_expiry', $aParseData);
						do_action('wilcity/focus-post-expiration', $postID);
					}
					break;
				default:
					if ( strpos($field, 'wilcity_social_media_') !== false ){
						if ( !empty($aParseData) ){
							$socialKey = str_replace('wilcity_social_media_', '', $field);
							$aSocialUpdated = \WilokeListingTools\Framework\Helpers\GetSettings::getSocialNetworks($postID);
							$aSocialUpdated[$socialKey] = $aParseData;
							SetSettings::setPostMeta($postID, 'social_networks', $aSocialUpdated);
						}
					}
					break;
			}
		}
	}
	
	if( !empty($aAddress) ) {
		ListingMetaBox::saveData($postID, $aAddress);
		SetSettings::setPostMeta($postID, 'location', $aAddress);
	}
}

$wilcityAddon->set_import_function('wilcity_migrating_to_wilcity');

$wilcityAddon->run(
	array(
		'themes'  => array('WilCity')
	)
);
