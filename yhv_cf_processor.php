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

add_filter( 'caldera_forms_render_get_field', function( $field ) {
  if(in_array($field['ID'], ['fld_9596113', 'fld_2531943'])){
    $field['config']['default'] = $_COOKIE['volunteer_cid'];
  }
  return $field;
});

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
      'cid' => 'fld_9596113',
      'files' => [
        'tb_test' => 'fld_6340504',
      ],
    ];
  }
  elseif ($form['ID'] == 'CF5f8ebef61a6bd') {
    // Police Check Form.
    $fields = [
      'activity_date' => 'fld_2728792',
      'cid' => 'fld_2531943',
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
  if ($form['ID'] == 'CF5f63138ba9942') {
    $options = [];
    $data['sequential'] = 1;
    $call = wpcmrf_api('Contact', 'get', $data, $options, WP_CMRF_ID);
    $cid = $call->getReply()['values'][0]['id'];
    if (!empty($cid)) {
      $params = [
        'cid' => $cid,
        'email' => $data['email'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
      ];
      wpcmrf_api('Contact', 'createwpuser', $params, $options, WP_CMRF_ID);
    }
    if (empty($data['files'])) {
      return;
    }
    if ($profiles[WP_CMRF_ID]['connector'] == 'curl') {
      $url = $parsedUrl['scheme'] . "://" . $parsedUrl['host'] . '/fileupload.php';
      $dataToSend = [];
      $dataToSend['fileparams'] = json_encode($data);
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToSend);
      curl_exec($ch);
    }
    elseif (!empty($cid)) {
      $params = [
        'cid' => $cid,
        'tb_test' => $data['files']['tb_test'],
        'police_check' => $data['files']['police_check'],
        'first_aid' => $data['files']['first_aid'],
      ];
      $call = wpcmrf_api('FormProcessor', 'verification_files', $params, $options, WP_CMRF_ID);
      $call->getReply();
    }
  }
  if (in_array($form['ID'], ['CF5f8ebe3f3f889', 'CF5f8ebef61a6bd'])) {
    if (empty($data['files'])) {
      return;
    }
    // We support only remote forms for verification.
    if ($profiles[WP_CMRF_ID]['connector'] == 'curl') {
      $url = $parsedUrl['scheme'] . "://" . $parsedUrl['host'] . '/verification.php';
      $dataToSend = [];
      if ($form['ID'] == 'CF5f8ebe3f3f889') {
        // TB Screening
        $data['type'] = 'tb_test_verification';
      }
      else {
        $data['type'] = 'police_check_verification';
      }
      $dataToSend['fileparams'] = json_encode($data);
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToSend);
      curl_exec($ch);
    }
/*    else {
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
    }*/
  }

}, 10, 3 );

add_filter( 'wp_nav_menu_items', 'yhv_loginout_menu_link', 10, 2 );
function yhv_loginout_menu_link( $items, $args ) {
  if (get_site_url() == 'https://yhvolunteer.jmaconsulting.biz' && $args->theme_location == 'primary') {
    if (!empty($_COOKIE['volunteer_cid'])) {
      $items .= '<li class="right"><a href="' . get_site_url() . '/volunteer-login?action=logout">'. __("Volunteer Log Out 義工登出") .'</a></li>';
    } else {
      $items .= '<li class="right"><a href="' . get_site_url() . '/volunteer-login">'. __("Volunteer Log In 義工登入") .'</a></li>';
    }
  }
  return $items;
}

add_filter( 'caldera_forms_magic_summary_should_use_label', '__return_true' );
