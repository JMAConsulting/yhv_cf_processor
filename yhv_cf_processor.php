<?php
define('WP_CMRF_ID', 1);
/*
Plugin Name: YHV Caldera Form Processor
Plugin URI: https://jmaconsulting.biz
Description: Plugin to handle file transmissions and variable management for Caldera Forms.
Version: 1.0
Author: Edsel Lopez
License: GPLv2 or later
*/

/**
 * Get a field value and send to remote API
 */
add_action( 'caldera_forms_submit_complete', function( $form, $referrer, $process_id ) {
  $profiles = \CMRF\Wordpress\Core::singleton()->getConnectionProfiles();
  $parsedUrl = parse_url($profiles[WP_CMRF_ID]['url']);
  if (!in_array($form['ID'], ['CF5f63138ba9942', 'CF5f8ebe3f3f889', 'CF5f8ebef61a6bd'])) {
    return;
  }
  $data = [];

  if ($form['ID'] == 'CF5f63138ba9942') {
    // Volunteer Application Form.
    $fields = [
      'first_name' => 'fld_6402306',
      'last_name' => 'fld_2148379',
      'email' => 'fld_5566226',
      'files' => [
        'tb_test' => 'fld_6875471',
        'police_check' => 'fld_1303524',
        'first_aid' => 'fld_7859383',
      ],
    ];
  }
  elseif ($form['ID'] == 'CF5f8ebe3f3f889') {
    // TB Screening Form.
    $fields = [
      'activity_date' => 'fld_3478308',
      'files' => [
        'tb_test' => 'fld_6340504',
      ],
    ];
  }
  elseif ($form['ID'] == 'CF5f8ebef61a6bd') {
    // Police Check Form.
    $fields = [
      'activity_date' => 'fld_2728792',
      'files' => [
        'police_check' => 'fld_1855544',
      ],
    ];
  }

  foreach ($fields as $key => $value) {
    if ($key != 'files') {
      $data[$key] = Caldera_Forms::get_field_data( $value, $form );
    }
    else {
      foreach($fields['files'] as $k => $v) {
        $data['files'][$k] = Caldera_Forms::get_field_data( $v, $form );
      }
    }
  }
  if (empty($data['files'])) {
    return;
  }
  if ($form['ID'] == 'CF5f63138ba9942') {
    if ($profiles[WP_CMRF_ID]['connector'] == 'curl') {
      $url = $parsedUrl['scheme'] . "://" . $parsedUrl['host'] . '/fileupload.php';
      $data['fileparams'] = $data;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_exec($ch);
    } else {
      $options = [];
      $params = [
        'cid' => $_COOKIE['volunteer_cid'],
        'tb_test' => $data['files']['tb_test'],
        'police_check' => $data['files']['police_check'],
        'first_aid' => $data['files']['first_aid'],
      ];
      $call = wpcmrf_api('FormProcessor', 'verification_files', $params, $options, WP_CMRF_ID);
      $call->getReply();
    }
  }
  if (in_array($form['ID'], ['CF5f8ebe3f3f889', 'CF5f8ebef61a6bd'])) {
    if ($profiles[WP_CMRF_ID]['connector'] == 'curl') {
      $url = $parsedUrl['scheme'] . "://" . $parsedUrl['host'] . '/verification.php';
      $data['fileparams'] = $data;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_exec($ch);
    } else {
      $options = [];
      $params = [
        'cid' => $_COOKIE['volunteer_cid'],
        'activity_date' => date('Ymd', strtotime($data['activity_date'])),
      ];
      if ($form['ID'] == 'CF5f8ebe3f3f889') {
        $params['tb_test'] = $data['files']['tb_test'];
        $call = wpcmrf_api('FormProcessor', 'tb_test_verification', $params, $options, WP_CMRF_ID);
      }
      else {
        $params['police_check'] = $data['files']['police_check'];
        $call = wpcmrf_api('FormProcessor', 'police_check_verfication', $params, $options, WP_CMRF_ID);
      }
      $call->getReply();
    }
  }

}, 10, 3 );

add_filter( 'wp_nav_menu_items', 'yhv_loginout_menu_link', 10, 2 );
function yhv_loginout_menu_link( $items, $args ) {
  if ($args->theme_location == 'primary') {
    if (!empty($_COOKIE['volunteer_cid'])) {
      $items .= '<li class="right"><a href="' . get_site_url() . '/volunteer-login?action=logout">'. __("Log Out") .'</a></li>';
    } else {
      $items .= '<li class="right"><a href="' . get_site_url() . '/volunteer-login">'. __("Log In") .'</a></li>';
    }
  }
  return $items;
}