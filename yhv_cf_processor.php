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
  if ('fld_9596113' == $field['ID']) {
    $field['config']['default'] = $_COOKIE['volunteer_cid'];
  }
  if ('fld_2531943' == $field['ID']) {
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
  $url = $parsedUrl['scheme'] . "://" . $parsedUrl['host'] . '/fileupload.php';
  if( 'CF5f63138ba9942' != $form[ 'ID' ] ) {
    return;
  }
  $data = [];

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
  if ($profiles[WP_CMRF_ID]['connector'] == 'curl') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    curl_exec($ch);
  }
  else {
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

}, 10, 3 );
