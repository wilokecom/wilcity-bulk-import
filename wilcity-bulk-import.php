<?php
/*
 * Plugin Name: Wilcity Bulk Import
 * Plugin URI: https://wilcity.com
 * Author: Wiloke
 * Author URI: https://wilcity.com
 * Description: Migrating from another theme to Wilcity
 * Version: 1.6
 */

use WilokeListingTools\Framework\Helpers\GetSettings;
use WilokeListingTools\MetaBoxes\Listing as ListingMetaBox;
use WilokeListingTools\Framework\Helpers\SetSettings;

$aSocialNetworks = [
	'facebook',
	'twitter',
	'google-plus',
	'tumblr',
	'vk',
	'odnoklassniki',
	'youtube',
	'vimeo',
	'rutube',
	'dribbble',
	'instagram',
	'flickr',
	'pinterest',
	'medium',
	'tripadvisor',
	'wikipedia',
	'stumbleupon',
	'livejournal',
	'linkedin',
	'skype',
	'bloglovin',
	'whatsapp',
	'soundcloud'
];

include plugin_dir_path(__FILE__) . 'rapid-addon.php';

function wilcityCleanImageFileName($fileName)
{
	$aFileExtension = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
	foreach ($aFileExtension as $extension) {
		if (strpos($fileName, $extension) !== false) {
			$aParse = explode('.' . $extension, $fileName);

			return $aParse[0] . '.' . $extension;
		}
	}

	return $fileName;
}

if (!function_exists('wilcityMigrationInsertImage')) {
	function wilcityMigrationInsertImage($imgSrc)
	{
		if (empty($imgSrc)) {
			return false;
		}
		$imgSrc = trim($imgSrc);

		$wp_upload_dir = wp_upload_dir();
		$filename = basename($imgSrc);
		$filename = wilcityCleanImageFileName($filename);
		$filetype = wp_check_filetype($filename, null);

		if (is_file($wp_upload_dir['path'] . '/' . $filename)) {
			global $wpdb;
			$postTitle = preg_replace('/\.[^.]+$/', '', $filename);

			$postID = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_title=%s and post_mime_type=%s",
					$postTitle, $filetype['type']
				)
			);

			if ($url = wp_get_attachment_image_url($postID)) {
				return [
					'id'  => $postID,
					'url' => $url
				];
			}
		}

		$tmpFile = download_url($imgSrc);
		if ($tmpFile === false) {
			return false;
		}
		copy($tmpFile, $wp_upload_dir['path'] . '/' . $filename);
		@unlink($tmpFile);

		// Get the path to the upload directory.
		// Prepare an array of post data for the attachment.
		$aAttachment = [
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
			'post_content'   => '',
			'post_status'    => 'inherit'
		];
		// Insert the attachment.
		$attach_id = wp_insert_attachment($aAttachment, $wp_upload_dir['path'] . '/' . $filename);
		$aAttachData = wp_generate_attachment_metadata($attach_id, $filename);

		if ($aAttachData) {
			wp_update_attachment_metadata($attach_id, $aAttachData);
		}

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$oNewImage = get_post($attach_id);

		if (!empty($oNewImage)) {
			return [
				'id'  => $attach_id,
				'url' => $wp_upload_dir['url'] . '/' . $filename
			];
		}

		return [
			'id'  => $attach_id,
			'url' => $wp_upload_dir['url'] . '/' . $filename
		];
	}
}

function wilcityTimezoneToString($time)
{
	if (strpos($time, 'GMT') !== false) {
		return $time;
	}

	$time = str_replace(['UTC', '+'], ['', ''], $time);

	if (empty($time)) {
		return 'UTC';
	}

	$utc_offset = intval($time * 3600);

	if ($timezone = timezone_name_from_abbr('', $utc_offset)) {
		return $timezone;
	}

	// last try, guess timezone string manually
	foreach (timezone_abbreviations_list() as $abbr) {
		foreach ($abbr as $city) {
			if ((bool)date('I') === (bool)$city['dst'] && $city['timezone_id'] &&
				intval($city['offset']) === $utc_offset) {
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
$wilcityAddon->add_field('wilcity_listify_business_hours',
	'Business Hours (If you migrate from Listify, please put it here)',
	'text');
$wilcityAddon->add_field('wilcity_business_normal_hours',
	'Business Hours Normal Format Monday 06:00 AM - 08:00 PM. If you are using shiftwork, please use this structure Monday 06:00 – 10:00|13:00 - 23:00',
	'text');
$wilcityAddon->add_field('wilcity_listing_pro_business_hours', 'ListingPro Business Hour', 'text');
$wilcityAddon->add_field('wilcity_wp_job_hours', 'WP Jobs Business Hour',
	'text');
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
$wilcityAddon->add_field('wilcity_lat_lng',
	'Latitude & Longitude (Some themes do not separated Lat and Lng, so you can use this field)', 'text');
//$wilcityAddon->add_field('wilcity_restaurant_menu_title', 'Restaurant Menu Title', 'text');
//$wilcityAddon->add_field('wilcity_restaurant_menu_desc', 'Restaurant Menu Description', 'text');
$wilcityAddon->add_field('wilcity_restaurant_menu_items', 'Restaurant Menu Items', 'text');

$wilcityAddon->add_field('wilcity_location', 'Wilcity Location', 'text');

$wilcityAddon->add_field('wilcity_video_url', 'Video URL', 'text');
$wilcityAddon->add_field('wilcity_expiration', 'Listing Expiry Date', 'text');
$wilcityAddon->add_field('wilcity_ltpro_options', 'Listing Pro Options', 'text');

$wilcityAddon->add_field('wilcity_button_link', 'Button Link', 'text');
$wilcityAddon->add_field('wilcity_button_icon', 'Button Icon', 'text');
$wilcityAddon->add_field('wilcity_button_name', 'Button Name', 'text');

//$wilcityAddon->add_field('wilcity_event_timezone', 'Event Timezone', 'text');
$wilcityAddon->add_field('wilcity_event_frequency',
	'Event Frequency (occurs_once/daily/weekly). Leave empty mean occurs_once', 'text');
$wilcityAddon->add_field('wilcity_event_belongs_to', 'Event Parent', 'text');
$wilcityAddon->add_field('wilcity_event_specify_day', 'Specify day', 'text');
$wilcityAddon->add_field('wilcity_event_start_at', 'Event Opening At (EG: 12:00:00 AM)', 'text');
$wilcityAddon->add_field('wilcity_event_start_on', 'Event Start On (EG: 2018/11/30)', 'text');
$wilcityAddon->add_field('wilcity_event_end_at', 'Event Close At (EG: 12:00:00 AM)', 'text');
$wilcityAddon->add_field('wilcity_event_end_on', 'Event Close On (EG: 2018/11/30)', 'text');

$aDayOfWeeks = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$aDayOfWeeksShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

foreach ($aSocialNetworks as $socialNetwork) {
	$wilcityAddon->add_field('wilcity_social_media_' . $socialNetwork, ucfirst($socialNetwork) . ' URL', 'text');
}

function wilokeBuildBH($time)
{
	return date('H:i:s', strtotime($time));
}

function wilcityDetermineDay($rawDay, $aData = [])
{
	$rawDay = str_replace(
		[
			'Monday:',
			'Tuesday:',
			'Wednesday:',
			'Thursday:',
			'Friday:',
			'Saturday:',
			'Sunday:',
		],
		[
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
			'Sunday',
		],
		$rawDay
	);

	global $aDayOfWeeks, $aDayOfWeeksShort;

	foreach ($aDayOfWeeks as $key => $day) {
		if (strpos($rawDay, $day) !== false || strpos($rawDay, $aDayOfWeeksShort[$key]) !== false) {
			unset($aDayOfWeeks[$key]);
			unset($aDayOfWeeksShort[$key]);

			$bh = trim(str_replace($day, '', $rawDay));
			$rawDay = strtolower($rawDay);

			if (strpos($rawDay, 'close') !== false) {
				return [
					'info' => [
						'start'   => '',
						'close'   => '',
						'isClose' => 'yes'
					],
					'day'  => $day
				];
			} else {
				$aParseBusinessHourTable = explode('|', $bh);
				$aInfo = [];

				if (isset($aParseBusinessHourTable[0])) {
					$aParsed = explode(
						apply_filters('wilcity-bulk-import/explode-hour-clue', '-'),
						$aParseBusinessHourTable[0]
					);

					$aInfo['first'] = [
						'start' => wilokeBuildBH(trim($aParsed[0])),
						'close' => wilokeBuildBH(trim($aParsed[1]))
					];
				}

				if (isset($aParseBusinessHourTable[1])) {
					$aParsed = explode(
						apply_filters('wilcity-bulk-import/explode-hour-clue', '-'),
						$aParseBusinessHourTable[1]
					);

					$aInfo['second'] = [
						'start' => wilokeBuildBH(trim($aParsed[0])),
						'close' => wilokeBuildBH(trim($aParsed[1]))
					];
				}

				return [
					'info' => $aInfo,
					'day'  => $day
				];
			}
		}
	}

	return [
		'day'  => array_shift($aDayOfWeeks),
		'info' => false
	];
}

function wilcityParseNormalBusinessHour($aParseBusinessHours, $aData = [])
{
	global $aDayOfWeeks, $aDayOfWeeksShort;

	$aBusinessHours = [];
	foreach ($aParseBusinessHours as $rawVal) {
		$aParsed = wilcityDetermineDay($rawVal, $aData);
		$aBusinessHours[$aParsed['day']] = $aParsed['info'];
	}

	if (count($aBusinessHours) != 7) {
		foreach ($aParseBusinessHours as $rawDay) {
			foreach ($aDayOfWeeks as $key => $day) {
				if (strpos($rawDay, $day) === false && strpos($rawDay, $aDayOfWeeksShort[$key]) === false) {
					$aBusinessHours[$day] = false;
				}
			}
		}
	}

	$aDayOfWeeks = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	$aDayOfWeeksShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

	return $aBusinessHours;
}

function convertStringToFormatBusinessHour($str)
{
	$aDayofHour = array_map('trim', explode(',', $str));
	$aDayOfWeeks = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	foreach ($aDayofHour as $key => $dayofHour) {

		$_dayofHour = strtolower($dayofHour);
		if ($_dayofHour == 'removed') {
			$aDayofHour[$key] = $aDayOfWeeks[$key] . ' removed';
		} else if ($_dayofHour == 'closed' || empty($_dayofHour)) {
			$aDayofHour[$key] = $aDayOfWeeks[$key] . ' closed';
		} else if ($_dayofHour == '24 hours') {
			$aDayofHour[$key] = $aDayOfWeeks[$key] . ' 12:00AM–11:45PM';
		} else {
			$aDayofHour[$key] = $aDayOfWeeks[$key] . ' ' . $dayofHour;
		}
	}

	return implode(',', $aDayofHour);
}

function wilcity_migrating_to_wilcity($postID, $aData, $importOptions, $aListing)
{
	global $wilcityAddon, $aSocialNetworks;
	$aThemeOptions = Wiloke::getThemeOptions(true);

	$aFields = [
		'wilcity_logo',
		'wilcity_tagline',
		'wilcity_toggle_business_status',
		'wilcity_listify_business_hours',
		'wilcity_listing_pro_business_hours',
		'wilcity_business_normal_hours',
		'wilcity_wp_job_hours',
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
		'wilcity_event_end_on',
		'wilcity_button_link',
		'wilcity_button_icon',
		'wilcity_button_name',
		'wilcity_restaurant_menu_title',
		'wilcity_restaurant_menu_desc',
		'wilcity_restaurant_menu_items'
	];

	foreach ($aSocialNetworks as $socialNetwork) {
		$aFields[] = 'wilcity_social_media_' . $socialNetwork;
	}

	$aDaysOfWeeks = [
		'monday'    => esc_html__('Monday', 'wiloke-listing-tools'),
		'tuesday'   => esc_html__('Tuesday', 'wiloke-listing-tools'),
		'wednesday' => esc_html__('Wednesday', 'wiloke-listing-tools'),
		'thursday'  => esc_html__('Thursday', 'wiloke-listing-tools'),
		'friday'    => esc_html__('Friday', 'wiloke-listing-tools'),
		'saturday'  => esc_html__('Saturday', 'wiloke-listing-tools'),
		'sunday'    => esc_html__('Sunday', 'wiloke-listing-tools')
	];

	$aDaysOfWeekKeys = array_keys($aDaysOfWeeks);

	$aBusinessHours = [];
	$aAddress = [];
	$aRestaurantMenus = [];

	$aEventData = [];
	if (empty($aListing['ID']) && !empty($postID)) {
		$aListing['ID'] = $postID;
	}

	foreach ($aFields as $field) {
		if (empty($aListing['ID']) || $wilcityAddon->can_update_meta($field, $importOptions)) {
			$data = $aData[$field];
			$aParseData = maybe_unserialize($data);
			$aPriceSettings = null;

			switch ($field) {
				case 'wilcity_button_link':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'button_link', $aParseData);
					}
					break;
				case 'wilcity_button_icon':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'button_icon', $aParseData);
					}
					break;
				case 'wilcity_button_name':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'button_name', $aParseData);
					}
					break;
				case 'wilcity_event_frequency':
					$aEventData['frequency'] = empty($aParseData) ? 'occurs_once' : trim($aParseData);
					break;
				case 'wilcity_event_belongs_to':
					$aEventData['parentID'] = $aParseData;
					break;
				case 'wilcity_event_start_at':
					$aEventData['start_at'] = trim($aParseData);
					break;
				case 'wilcity_event_start_on':
					$aEventData['start_on'] = trim($aParseData);
					break;
				case 'wilcity_event_end_at':
					$aEventData['end_at'] = trim($aParseData);
					break;
				case 'wilcity_event_end_on':
					$aEventData['end_on'] = trim($aParseData);
					break;
				case 'wilcity_event_specify_day':
					$aEventData['specify_day'] = empty($aParseData) ? 'always' : trim($aParseData);
					break;
				case 'wilcity_logo':
					$aLogo = wilcityMigrationInsertImage($aParseData);

					if ($aLogo) {
						SetSettings::setPostMeta($aListing['ID'], 'logo', $aLogo['url']);
						SetSettings::setPostMeta($aListing['ID'], 'logo_id', $aLogo['id']);
					}
					break;
				case 'wilcity_tagline':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'tagline', $aParseData);
					}
					break;
				case 'wilcity_toggle_business_status':
					if (!empty($aParseData)) {
						if ($aParseData == 'enable') {
							$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						} else if ($aParseData == 'disable') {
							$aBusinessHours['hourMode'] = 'no_hours_available';
						}
					} else {
						$aBusinessHours['hourMode'] = 'no_hours_available';
					}
					break;
				case 'wilcity_listify_business_hours':
				case 'wilcity_wp_job_hours':
					if (!empty($aParseData)) {
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						$aBusinessHours['businessHours'] = [];
						$order = 0;
						foreach ($aParseData as $aItem) {
							$aDay = [];
							if (isset($aItem['start'])) {
								if ($aItem['start'] == 'Closed' || $aItem['end'] == 'Closed') {
									$aDay['isOpen'] = 'no';
									$aDay['operating_times']['firstOpenHour'] = '';
									$aDay['operating_times']['firstCloseHour'] = '';
								} else {
									$aDay['isOpen'] = 'yes';
									$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aDay['start']);
									$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aDay['end']);
								}
							} else if (is_array($aItem)) {
								if (isset($aItem[0]['open'])) {
									if ($aItem[0]['open'] == 'Closed' || $aItem[0]['close'] == 'Closed') {
										$aDay['isOpen'] = 'no';
										$aDay['operating_times']['firstOpenHour'] = '';
										$aDay['operating_times']['firstCloseHour'] = '';
									} else {
										$aDay['isOpen'] = 'yes';
										$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aItem[0]['open']);
										$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem[0]['close']);

										if (isset($aItem[1]['open']) && isset($aItem[1]['close']) &&
											$aItem[1]['open'] != 'Closed' && $aItem[1]['close'] != 'Closed') {
											$aDay['operating_times']['secondOpenHour']
												= wilokeBuildBH($aItem[1]['open']);
											$aDay['operating_times']['secondCloseHour']
												= wilokeBuildBH($aItem[1]['close']);
										}
									}
								}
							}
							$aBusinessHours['businessHours'][$aDaysOfWeekKeys[$order]] = $aDay;
							$order++;
						}
						ListingMetaBox::saveBusinessHours($aListing['ID'], $aBusinessHours);
					}
					break;
				case 'wilcity_listing_pro_business_hours':
					if (!empty($aData['wilcity_listing_pro_business_hours'])) {
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						$aBusinessHours['businessHours'] = [];
						$aRawBH = explode('|', trim($aData['wilcity_listing_pro_business_hours'], '|'));
						$aParsedBusinessHours = wilcityParseNormalBusinessHour($aRawBH, $aData);
						$order = 0;
						foreach ($aParsedBusinessHours as $dayOfWeek => $aBHInfo) {
							if (!$aBusinessHours) {
								$aDay['isOpen'] = 'no';
								$aDay['operating_times']['firstOpenHour'] = '';
								$aDay['operating_times']['firstCloseHour'] = '';
							} else {
								$aDay['isOpen'] = isset($aBHInfo['isClose']) ? 'no' : 'yes';

								if (isset($aBHInfo['first'])) {
									$aDay['operating_times']['firstOpenHour'] = $aBHInfo['first']['start'];
									$aDay['operating_times']['firstCloseHour'] = $aBHInfo['first']['close'];

									if (isset($aBHInfo['second'])) {
										$aDay['operating_times']['secondOpenHour'] = $aBHInfo['second']['start'];
										$aDay['operating_times']['secondCloseHour'] = $aBHInfo['second']['close'];
									}
								} else {
									$aDay['operating_times']['firstOpenHour'] = $aBHInfo['start'];
									$aDay['operating_times']['firstCloseHour'] = $aBHInfo['close'];
								}
							}
							$aBusinessHours['businessHours'][$aDaysOfWeekKeys[$order]] = $aDay;
							$order++;
						}

						ListingMetaBox::saveBusinessHours($aListing['ID'], $aBusinessHours);
					}
					break;
				case 'wilcity_business_normal_hours':
					if (!empty($aData['wilcity_business_normal_hours'])) {
						$aBusinessHours['hourMode'] = 'open_for_selected_hours';
						$aBusinessHours['businessHours'] = [];
						$aRawBH = explode(',', trim($aData['wilcity_business_normal_hours'],
							','));
						$aParsedBusinessHours = wilcityParseNormalBusinessHour($aRawBH, $aData);
						$order = 0;
						foreach ($aParsedBusinessHours as $dayOfWeek => $aBHInfo) {
							if (!$aBusinessHours) {
								$aDay['isOpen'] = 'no';
								$aDay['operating_times']['firstOpenHour'] = '';
								$aDay['operating_times']['firstCloseHour'] = '';
							} else {
								$aDay['isOpen'] = isset($aBHInfo['isClose']) ? 'no' : 'yes';

								if (isset($aBHInfo['first'])) {
									$aDay['operating_times']['firstOpenHour'] = $aBHInfo['first']['start'];
									$aDay['operating_times']['firstCloseHour'] = $aBHInfo['first']['close'];

									if (isset($aBHInfo['second'])) {
										$aDay['operating_times']['secondOpenHour'] = $aBHInfo['second']['start'];
										$aDay['operating_times']['secondCloseHour'] = $aBHInfo['second']['close'];
									}
								} else {
									$aDay['operating_times']['firstOpenHour'] = $aBHInfo['start'];
									$aDay['operating_times']['firstCloseHour'] = $aBHInfo['close'];
								}
							}
							$aBusinessHours['businessHours'][$aDaysOfWeekKeys[$order]] = $aDay;
							$order++;
						}

						ListingMetaBox::saveBusinessHours($aListing['ID'], $aBusinessHours);
					}
					break;
				case 'wilcity_listing_claim':
					if ($aParseData == 1 || $aParseData == 'claimed') {
						SetSettings::setPostMeta($aListing['ID'], 'claim_status', 'claimed');
					} else {
						SetSettings::setPostMeta($aListing['ID'], 'claim_status', 'not_claim');
					}
					break;
				case 'wilcity_price_range':
					$aConvertPriceStatus = [
						'notsay'         => '',
						'inexpensive'    => 'cheap',
						'moderate'       => 'moderate',
						'pricey'         => 'expensive',
						'ultra_high_end' => 'ultra_high',
					];
					if (isset($aConvertPriceStatus[$field])) {
						$priceRange = $aConvertPriceStatus[$field];
					} else {
						$priceRange = $aParseData;
					}

					SetSettings::setPostMeta($aListing['ID'], 'price_range', $priceRange);
					break;
				case 'wilcity_price_range_minimum':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'minimum_price', $aParseData);
					}
					break;
				case 'wilcity_price_range_maximum':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'maximum_price', $aParseData);
					}
					break;
				case 'wilcity_single_price':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'single_price', $aParseData);
					}
					break;
				case 'wilcity_timezone':
					if (!empty($aParseData)) {
						$timezone = wilcityTimezoneToString($aParseData);
						SetSettings::setPostMeta($aListing['ID'], 'timezone', $timezone);
					}
					break;
				case 'wilcity_email':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'email', $aParseData);
					}
					break;
				case 'wilcity_phone':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'phone', $aParseData);
					}
					break;
				case 'wilcity_website':
					if (!empty($aParseData)) {
						SetSettings::setPostMeta($aListing['ID'], 'website', $aParseData);
					}
					break;
				case 'wilcity_lat':
					$aAddress['lat'] = $aParseData;
					break;
				case 'wilcity_lng':
					$aAddress['lng'] = $aParseData;
					if (!empty($aAddress['lat']) && !empty($aAddress['lng'])) {
						if (empty($aData['wilcity_address'])) {
							$geocodeFromLatLong
								= file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?latlng=' .
								trim($aAddress['lat']) . ',' . trim($aAddress['lng']) . '&key=' .
								$aThemeOptions['general_google_api']);

							$oOutput = json_decode($geocodeFromLatLong);
							if ($oOutput->status == 'OK') {
								$aAddress['address'] = $oOutput->results[1]->formatted_address;
							}
						}

						//                        if (!empty($aAddress['address'])) {
						//                            ListingMetaBox::saveData($postID, $aAddress);
						//                        }
					}
					break;
				case 'wilcity_address':
					$aAddress['address'] = $aParseData;
					if ((empty($aAddress['lat']) && empty($aAddress['lng'])) && empty($aAddress['wilcity_lat_lng'])) {
						if (!empty($aAddress['address'])) {
							$geocode = file_get_contents("https://maps.google.com/maps/api/geocode/json?address=" .
								urlencode($aAddress['address']) . "&key=" .
								trim($aThemeOptions['general_google_api']));
							$oGeocode = json_decode($geocode);
							if ($oGeocode->status == 'OK') {
								$aAddress['lat'] = $oGeocode->results[0]->geometry->location->lat;
								$aAddress['lng'] = $oGeocode->results[0]->geometry->location->lng;
								//ListingMetaBox::saveData($postID, $aAddress);
							}
						}
					}
					break;
				case 'wilcity_ltpro_options':
					if (!empty($aParseData)) {
						if (isset($aParseData['tagline_text'])) {
							SetSettings::setPostMeta($aListing['ID'], 'tagline', $aParseData['tagline_text']);
						}

						if (!empty($aParseData['gAddress'])) {
							$aAddress['address'] = $aParseData['gAddress'];
							$aAddress['lat'] = $aParseData['latitude'];
							$aAddress['lng'] = $aParseData['longitude'];
							//ListingMetaBox::saveData($postID, $aAddress);
						}

						if (!empty($aParseData['phone'])) {
							SetSettings::setPostMeta($aListing['ID'], 'phone', $aParseData['phone']);
						}

						if (!empty($aParseData['email'])) {
							SetSettings::setPostMeta($aListing['ID'], 'email', $aParseData['email']);
						}

						if (!empty($aParseData['website'])) {
							SetSettings::setPostMeta($aListing['ID'], 'website', $aParseData['website']);
						}

						foreach ($aSocialNetworks as $social) {
							if ($social == 'google-plus') {
								$socialKey = 'google_plus';
							} else {
								$socialKey = $social;
							}

							$aSocialUpdated
								= \WilokeListingTools\Framework\Helpers\GetSettings::getSocialNetworks($aListing['ID']);
							$aSocialUpdated[$social] = $aParseData[$socialKey];
							SetSettings::setPostMeta($aListing['ID'], 'social_networks', $aSocialUpdated);
						}

						if (!empty($aParseData['video'])) {
							$aVideos = [];
							if (is_array($aParseData)) {
								foreach ($aParseData as $order => $url) {
									$aVideos[$order]['src'] = $url;
									$aVideos[$order]['thumbnail'] = '';
								}
							} else {
								$aVideosToArrays = explode(',', $aParseData);
								foreach ($aVideosToArrays as $order => $url) {
									$aVideos[$order]['src'] = $url;
									$aVideos[$order]['thumbnail'] = '';
								}
							}
							SetSettings::setPostMeta($aListing['ID'], 'video_srcs', $aVideos);
						}

						SetSettings::setPostMeta($aListing['ID'], 'price_range', $aParseData['price_range']);
						SetSettings::setPostMeta($aListing['ID'], 'minimum_price', $aParseData['list_price']);
						SetSettings::setPostMeta($aListing['ID'], 'maximum_price', $aParseData['list_price_to']);

						if (!empty($aParseData['business_logo'])) {
							$aLogo = wilcityMigrationInsertImage($aParseData['business_logo']);
							if ($aLogo) {
								SetSettings::setPostMeta($aListing['ID'], 'logo', $aLogo['url']);
								SetSettings::setPostMeta($aListing['ID'], 'logo_id', $aLogo['id']);
							}
						}

						if (!empty($aParseData['business_hours'])) {
							$aBusinessHours['hourMode'] = 'open_for_selected_hours';
							$aBusinessHours['businessHours'] = [];
							foreach ($aParseData['business_hours'] as $dayOfWeek => $aItem) {
								$dayOfWeek = strtolower($dayOfWeek);
								$aDay = [];
								if (count($aItem['open']) > 1) {
									$aDay['isOpen'] = 'yes';
									$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aItem['open'][0]);
									$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem['close'][0]);
								} else {
									$aDay['isOpen'] = 'yes';
									$aDay['operating_times']['firstOpenHour'] = wilokeBuildBH($aItem['open'][0]);
									$aDay['operating_times']['firstCloseHour'] = wilokeBuildBH($aItem['close'][0]);

									if (isset($aItem[1]['open'])) {
										$aDay['operating_times']['secondOpenHour'] = wilokeBuildBH($aItem['open'][1]);
										$aDay['operating_times']['secondCloseHour'] = wilokeBuildBH($aItem['close'][1]);
									}
								}
								$aBusinessHours['businessHours'][$dayOfWeek] = $aDay;
							}
							ListingMetaBox::saveBusinessHours($aListing['ID'], $aBusinessHours);
						}

					}
					break;
				case 'wilcity_lat_lng':
					if (!empty($aParseData) &&
						(empty($aAddress['address']) || empty($aAddress['lat']) || empty($aAddress['lng']))) {
						if (is_array($aParseData)) {
							if (isset($aParseData['lat'])) {
								$aAddress['lat'] = $aParseData['lat'];
								$aAddress['lng'] = $aParseData['lng'];
							} else if (isset($aParseData[0])) {
								$aAddress['lat'] = $aParseData[0];
								$aAddress['lng'] = $aParseData[1];
							}
						} else {
							$aLatLng = explode(',', $aParseData);
							$aAddress['lat'] = $aLatLng[0];
							$aAddress['lng'] = $aLatLng[1];
						}
					}
					break;
				case 'wilcity_location':
					if (!empty($aParseData)) {
						if (!empty($aParseData['lat']) && !empty($aParseData['lng']) &&
							!empty($aParseData['address'])) {
							ListingMetaBox::saveData($aListing['ID'], $aParseData);
							SetSettings::setPostMeta($aListing['ID'], 'location', $aParseData);
						}
					}
					break;
				case 'wilcity_video_url':
					if (!empty($aParseData)) {
						$aVideos = [];
						if (is_array($aParseData)) {
							foreach ($aParseData as $order => $url) {
								$aVideos[$order]['src'] = $url;
								$aVideos[$order]['thumbnail'] = '';
							}
						} else {
							$aVideosToArrays = explode(',', $aParseData);
							foreach ($aVideosToArrays as $order => $url) {
								$aVideos[$order]['src'] = $url;
								$aVideos[$order]['thumbnail'] = '';
							}
						}
						SetSettings::setPostMeta($aListing['ID'], 'video_srcs', $aVideos);
					}
					break;
				case 'wilcity_featured_image':
					if (!empty($aParseData)) {
						$aAttachment = wilcityMigrationInsertImage($aParseData);

						if ($aAttachment) {
							set_post_thumbnail($aListing['ID'], $aAttachment['id']);
						}
					}
					break;
				case 'wilcity_cover_image':
					if (!empty($aParseData)) {
						$aAttachment = wilcityMigrationInsertImage($aParseData);
						if ($aAttachment) {
							SetSettings::setPostMeta($aListing['ID'], 'cover_image', $aAttachment['url']);
							SetSettings::setPostMeta($aListing['ID'], 'cover_image_id', $aAttachment['id']);
						}
					}
					break;
				case 'wilcity_gallery':
					if (!empty($aParseData)) {
						$aDownloadedGallery = [];
						if (is_array($aParseData)) {
							foreach ($aParseData as $imgSrc) {
								$aAttachment = wilcityMigrationInsertImage($imgSrc);
								if ($aAttachment) {
									$aDownloadedGallery[$aAttachment['id']] = $aAttachment['url'];
								}
							}
						} else {
							if (strpos($aParseData, ',') !== false) {
								$aGalleryToArray = explode(',', $aParseData);
							} else {
								$aGalleryToArray = explode('|', $aParseData);
							}
							foreach ($aGalleryToArray as $imgSrc) {
								$aAttachment = wilcityMigrationInsertImage($imgSrc);
								if ($aAttachment) {
									$aDownloadedGallery[$aAttachment['id']] = $aAttachment['url'];
								}
							}
						}

						if (!empty($aDownloadedGallery)) {
							SetSettings::setPostMeta($aListing['ID'], 'gallery', $aDownloadedGallery);
						}
					}
					break;
				case 'wilcity_expiration':
					if (!empty($aParseData)) {
						$aParseData = strtotime($aParseData);
						SetSettings::setPostMeta($aListing['ID'], 'post_expiry', $aParseData);
						do_action('wilcity/focus-post-expiration', $aListing['ID']);
					}
					break;
				case 'wilcity_restaurant_menu_items':
					$aItems = is_array($aParseData) ? $aParseData : explode('|', $aParseData);
					foreach ($aItems as $order => $aItem) {
						$aItem = maybe_unserialize($aItem);

						$title = isset($aItem['title']) ? $aItem['title'] : '';
						$desc = isset($aItem['description']) ? $aItem['description'] : '';
						$icon = isset($aItem['icon']) ? $aItem['icon'] : '';
						$group = isset($aItem['group']) ? $aItem['group'] : '';

						SetSettings::setPostMeta($aListing['ID'], 'group_title_' . $order, $title);
						SetSettings::setPostMeta($aListing['ID'], 'group_description_' . $order, $desc);
						SetSettings::setPostMeta($aListing['ID'], 'group_icon_' . $order, $icon);
						//                        array ( 0 => array ( 'gallery' => array ( 13235 => 'http://127.0.0.1:8888/wilcity.com/wp-content/uploads/2020/01/yui5vfkhuzs-9.jpg', 13234 => 'http://127.0.0.1:8888/wilcity.com/wp-content/uploads/2020/01/9svnen8xpti-9.jpg', 13233 => 'http://127.0.0.1:8888/wilcity.com/wp-content/uploads/2020/01/kzcnvr-vdqu-9.jpg', ), 'title' => '123', 'description' => '456', 'price' => '12', 'link_to' => '#', 'is_open_new_window' => 'yes', ), )
						// Note that gallery can like this gallery => https://image1,https://image2
						$group = maybe_unserialize($group);
						if (is_array($group)) {
							foreach ($group as $groupOrder => $aGroupItem) {
								if (isset($aGroupItem['gallery']) && is_string($aGroupItem['gallery'])) {
									$aRawGallery = explode(',', $aGroupItem['gallery']);
									$aParsedGallery = [];
									foreach ($aRawGallery as $imgSrc) {
										$aAttachment = wilcityMigrationInsertImage($imgSrc);
										if ($aAttachment) {
											$aParsedGallery[$aAttachment['id']] = $aAttachment['url'];
										}
									}
									$aGroupItem['gallery'] = $aParsedGallery;
									$group[$groupOrder] = $aGroupItem;
								}

								SetSettings::setPostMeta(
									$aListing['ID'], 'restaurant_menu_group_' . $groupOrder,
									$group
								);
							}
						}
					}
					break;
				default:
					if (strpos($field, 'wilcity_social_media_') !== false) {
						if (!empty($aParseData)) {
							$socialKey = str_replace('wilcity_social_media_', '', $field);
							$aSocialUpdated = GetSettings::getSocialNetworks($aListing['ID']);
							$aSocialUpdated = empty($aSocialUpdated) || !is_array($aSocialUpdated) ? [] :
								$aSocialUpdated;
							$aSocialUpdated[$socialKey] = $aParseData;
							SetSettings::setPostMeta($aListing['ID'], 'social_networks', $aSocialUpdated);
						}
					}
					break;
					//                case 'wilcity_restaurant_menu_title':
					////                    $aRestaurantMenus['']
					//                    break;
					//                case 'wilcity_restaurant_menu_desc':
					break;
			}
		}
	}

	//No hours available
	if (empty($aBusinessHours) || $aBusinessHours['hourMode'] == '') {
		SetSettings::setPostMeta($aListing['ID'], 'hourMode', 'no_hours_available');
	}

	//Event Data
	if (!empty($aEventData) && !empty($aEventData['start_on']) && !empty($aEventData['end_on'])) {
		$aUpdateEvent = [];
		$aUpdateEvent['objectID'] = absint($aListing['ID']);
		$aUpdateEvent['parentID'] = absint($aEventData['parentID']);
		$aUpdateEvent['frequency'] = $aEventData['frequency'];
		$aUpdateEvent['address'] = $aAddress['address'];
		$aUpdateEvent['lat'] = sanitize_text_field($aAddress['lat']);
		$aUpdateEvent['lng'] = sanitize_text_field($aAddress['lng']);

		$aUpdateEvent['starts'] = date('m/d/Y', strtotime($aEventData['start_on']));
		$aUpdateEvent['endsOn'] = date('m/d/Y', strtotime($aEventData['end_on']));

		$timeFormat = get_option('time_format');
		$aUpdateEvent['openingAt'] = strtoupper(date($timeFormat, strtotime($aEventData['start_at'])));
		$aUpdateEvent['closedAt'] = strtoupper(date($timeFormat, strtotime($aEventData['end_at'])));

		$aPrepares = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
		if ($aEventData['frequency'] == 'weekly') {
			$aUpdateEvent['specifyDays'] = $aEventData['specify_day'];
			$aPrepares[] = '%s';
		}

		$status = \WilokeListingTools\Models\EventModel::updateEventData($aListing['ID'], [
			'values'   => $aUpdateEvent,
			'prepares' => $aPrepares
		]);
	}

	//Listing location
	if (!empty($aAddress)) {
		ListingMetaBox::saveData($aListing['ID'], $aAddress);
		SetSettings::setPostMeta($aListing['ID'], 'location', $aAddress);
	}
}

$wilcityAddon->set_import_function('wilcity_migrating_to_wilcity');

$wilcityAddon->run(
	[
		'themes' => ['WilCity']
	]
);
