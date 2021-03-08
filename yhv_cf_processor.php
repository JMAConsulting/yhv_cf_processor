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
  if (!empty($_POST) && $_POST['action'] == 'cf_process_ajax_submit') {
    return $field;
  }
  if(in_array($field['ID'], ['fld_9596113', 'fld_2531943']) && !empty($_COOKIE['volunteer_cid'])){
    $field['config']['default'] = $_COOKIE['volunteer_cid'];
  }
  if (!empty($_COOKIE['volunteer_cid'])) {
    $params = ['cid' => $_COOKIE['volunteer_cid']];
    $options = [];
    $contact = wpcmrf_api('Contact', 'getvolunteer', $params, $options, WP_CMRF_ID);
    $contact = $contact->getReply();
    $calderaFields = [
      'first_name' => 'first_name',
      'last_name' => 'last_name',
      'Chinese_Name' => 'chinese_name',
      'gender' => 'gender',
      'Age_18' => 'age_18_',
      'street_address' => 'street_address',
      'city' => 'city',
      'postal_code' => 'postal_code',
      'state_province_name' => 'province',
      'mobile' => 'mobile',
      'residence' => 'residence',
      'office' => 'office',
      'email' => 'email',
      'emergency_first_name' => 'emergency_contact_first_name',
      'emergency_last_name' => 'emergency_contact_last_name',
      'emergency_relationship' => 'relationship',
      'emergency_phone' => 'emergency_contact_phone',
      'Area_of_Education_' => 'area_of_education',
      'Other_Areas_of_Education' => 'other_area_of_education',
      'Profession_checkbox' => 'professions',
      'Other_profession' => 'other_profession',
      'Car_' => 'driving_license',
      'How_many_years_of_driving_experience_do_you_have_in_Ontario_' => 'driving_class',
    ];
    // Render slugs for timetable.
    for ($i = 1; $i <= 6; $i++) {
      for ($j = 1; $j <= 7; $j++) {
        $calderaFields[$j . '_' . $i] = $j . '_' . $i;
      }
    }
    foreach ($calderaFields as $customField => $calderaField) {
      if ($field['slug'] == $calderaField && !empty($contact['values'][$customField])) {
        $field['config']['default'] = $contact['values'][$customField];
      }
    }
    if ($field['ID'] == 'fld_467987') {
	    $field['config']['default'] = $params['cid'];
    }
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
      'dates' => [
        'tb_test' => 'fld_4812158',
        'police_check' => 'fld_5883240',
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
        'police_check_reimbursement' => 'fld_7243612',
      ],
    ];
  }

  foreach ($fields as $key => $value) {
    if ($key != 'files' && $key != 'dates') {
      $data[$key] = Caldera_Forms::get_field_data( $value, $form );
    }
    else {
      foreach($fields['files'] as $k => $v) {
        $data['files'][$k] = Caldera_Forms::get_field_data( $v, $form );
      }
      foreach($fields['dates'] as $d => $t) {
	$data['dates'][$d] = Caldera_Forms::get_field_data( $t, $form );
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
    if (empty($data['files']) || empty($data['dates'])) {
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
      // Create the activities here.
      $params = [
        'cid' => $cid,
        'tb_test' => $data['files']['tb_test'],
        'police_check' => $data['files']['police_check'],
        'first_aid' => $data['files']['first_aid'],
      ];
      if (!empty($data['dates']['tb_test'])) {
        $params['tb_test_date'] = date('Y-m-d', strtotime($data['dates']['tb_test']));
      }
      if (!empty($data['dates']['police_check'])) {
        $params['police_check_date'] = date('Y-m-d', strtotime($data['dates']['police_check']));
      }
      $call = wpcmrf_api('FormProcessor', 'volunteer_activity', $params, $options, WP_CMRF_ID);
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
      $items .= '<li class="right"><a href="' . get_site_url() . '/volunteer-login?action=logout">'. __("Volunteer Log Out") . '<br/>' . __("義工登出") .'</a></li>';
    } else {
      $items .= '<li class="right"><a href="' . get_site_url() . '/volunteer-login">'. __("Volunteer Log In") . '<br/>' . __("義工登入") .'</a></li>';
    }
  }
  return $items;
}

function yhv_exclude_menu_items( $items, $menu, $args ) {
    if (!empty($_COOKIE['volunteer_cid'])) {	    
	    foreach ( $items as $key => $item ) {
        if ( $item->object_id == 16 ) unset( $items[$key] );
    }
    }

    return $items;
}

add_filter( 'wp_get_nav_menu_items', 'yhv_exclude_menu_items', null, 3 );

add_filter( 'caldera_forms_magic_summary_should_use_label', '__return_true' );

add_filter( 'caldera_forms_field_attributes', function($attrs){
  $attrs[ 'data-parsley-error-message' ] = 'This value is required 此為必填項.';

return $attrs;
}, 10);

add_filter('caldera_forms_get_form_processors', 'yhv_email_cf_validator_processor');

/**
 * Add a custom processor for field validation
 *
 * @uses 'yhv_email_cf_validator_processor'
 *
 * @param array $processors Processor configs
 *
 * @return array
 */
function yhv_email_cf_validator_processor($processors){
  $processors['yhv_email_cf_validator'] = array(
    'name' => __('YHV Email Validator', 'yhv_cf_validator' ),
    'description' => '',
    'pre_processor' => 'yhv_email_validator',
    'template' => dirname(__FILE__) . '/config.php'
  );

  return $processors;
}

/**
 * Run field validation
 *
 * @param array $config Processor config
 * @param array $form Form config
 *
 * @return array|void Error array if needed, else void.
 */
function yhv_email_validator( array $config, array $form ){

  //Processor data object
  $data = new Caldera_Forms_Processor_Get_Data( $config, $form, yhv_email_cf_validator_fields() );

  //Value of field to be validated
  $value = $data->get_value( 'email' );

  //if not valid, return an error
  if(yhv_email_cf_validator_is_valid( $value )){

    //get ID of field to put error on
    $fields = $data->get_fields();
    $field_id = $fields[ 'email' ][ 'config_field' ];

    //Get label of field to use in error message above form
    $field = $form[ 'fields' ][ $field_id ];
    $label = $field[ 'label' ];

    //this is error data to send back
    return array(
      'type' => 'error',
      //this message will be shown above form
      'note' => __('The email address you have used already exists. Please click the previous button to navigate to the first page and change it.'),
      //Add error messages for any form field
      'fields' => array(
        //This error message will be shown below the field that we are validating
        $field_id => __( 'Email address already exists. Please use a different one', 'yhv_cf_validator' )
      )
    );
  }
}

/**
 * Check if email exists in WordPress.
 *
 * @return bool
 */
function yhv_email_cf_validator_is_valid( $value ){
  $options = [];
  $params = ['email' => $value];
  $call = wpcmrf_api('Contact', 'validateemail', $params, $options, WP_CMRF_ID);
  return $call->getReply()['values'];
}

/**
 * Processor fields
 *
 * @return array
 */
function yhv_email_cf_validator_fields(){
  return array(
    array(
      'id' => 'email',
      'type' => 'email',
      'required' => true,
      'magic' => true,
      'label' => __( 'Volunteer Email field', 'yhv_cf_validator' )
    ),
  );
}
